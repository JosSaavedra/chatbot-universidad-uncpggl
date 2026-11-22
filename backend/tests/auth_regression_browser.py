"""Regresión HTTP: los seis carnés protegidos sólo usan QR + Google.

Comprueba que las vías antiguas (carné, contraseña, passkeys, aprobación por
correo) ya no emiten sesión y que la única ruta disponible es QR. No depende
de Google real ni de un servidor de correo.
"""
import json
import os
import re
import shutil
import socket
import subprocess
import tempfile
import time
from contextlib import ExitStack
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen


def free_port():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def stop(process):
    process.terminate()
    process.wait(timeout=15)


def main():
    backend = Path(__file__).resolve().parents[1]
    php = subprocess.check_output([shutil.which("php"), "-r", "echo PHP_BINARY;"]).decode().strip()
    accounts = [
        {"owner": "owner-one", "carnets": "2024-MED-001,2023-ISI-001", "email": "owner-one@example.test"},
        {"owner": "owner-two", "carnets": "2024-ADM-001,2022-ICI-001", "email": "owner-two@example.test"},
        {"owner": "owner-three", "carnets": "2025-ENF-001,2023-MED-002", "email": "owner-three@example.test"},
    ]
    protected = {
        "2024-MED-001": "estudiante01@example.com",
        "2023-ISI-001": "estudiante02@example.com",
        "2024-ADM-001": "estudiante03@example.com",
        "2022-ICI-001": "estudiante04@example.com",
        "2025-ENF-001": "estudiante05@example.com",
        "2023-MED-002": "estudiante06@example.com",
    }
    personal_emails = [account["email"] for account in accounts]

    with tempfile.TemporaryDirectory() as tmp, ExitStack() as stack:
        db = Path(tmp) / "test.sqlite"
        db.touch()
        port = free_port()
        origin = f"http://127.0.0.1:{port}"
        api = f"{origin}/api"
        env = dict(
            os.environ,
            DB_CONNECTION="sqlite", DB_DATABASE=str(db), DB_URL="",
            APP_CONFIG_CACHE=str(Path(tmp) / "config.php"), APP_ENV="testing",
            CACHE_STORE="database", SESSION_DRIVER="database", QUEUE_CONNECTION="database",
            LOG_CHANNEL="stderr", APP_DEBUG="false", STUDENT_ACCESS_MODE="demo",
            QR_AUTH_URL=origin, QR_AUTH_TTL_MINUTES="10",
            GOOGLE_CLIENT_ID="regression.apps.googleusercontent.com",
            GOOGLE_CLIENT_SECRET="regression-secret",
            GOOGLE_REDIRECT_URI=f"{origin}/auth/google/callback",
            GOOGLE_ACCOUNT_LINKS=json.dumps(accounts),
        )
        subprocess.check_call([php, "artisan", "migrate", "--force"], cwd=backend, env=env)
        subprocess.check_call([php, "artisan", "qr-google:sync-links"], cwd=backend, env=env)
        subprocess.check_call([php, "artisan", "db:seed", "--force"], cwd=backend, env=env)

        server = subprocess.Popen(
            [php, "-S", f"127.0.0.1:{port}", "public/index.php"],
            cwd=backend, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        stack.callback(stop, server)

        for _ in range(40):
            try:
                with socket.create_connection(("127.0.0.1", port), timeout=0.5):
                    break
            except OSError:
                time.sleep(0.1)

        def call(method, path, payload=None):
            data = json.dumps(payload).encode() if payload is not None else None
            request = Request(api + path, data=data, method=method, headers={
                "Content-Type": "application/json", "Accept": "application/json",
            })
            try:
                with urlopen(request) as response:
                    return response.status, json.loads(response.read().decode() or "{}")
            except HTTPError as error:
                body = error.read().decode(errors="replace")
                try:
                    return error.code, json.loads(body or "{}")
                except json.JSONDecodeError:
                    return error.code, {}

        status, mode = call("GET", "/auth/mode")
        assert status == 200, mode
        assert mode["mode"] == "demo", mode
        assert "qr_required" not in mode, mode

        for carnet, email in protected.items():
            status, _ = call("POST", "/login/carnet", {"carnet": carnet})
            assert status == 404, (carnet, "carné", status)

            status, _ = call("POST", "/login", {"email": email, "password": "password123"})
            assert status == 422, (carnet, "contraseña", status)

            for path in ["/passkeys/login/options", "/passkeys/login/verify",
                         "/passkeys/register/options", "/passkeys/register/verify"]:
                status, _ = call("POST", path, {"carnet": carnet})
                assert status == 404, (carnet, path, status)

            status, _ = call("POST", "/login-approvals/request", {"carnet": carnet})
            assert status == 404, (carnet, "aprobación por correo", status)

            status, qr = call("POST", "/qr-auth/request", {"carnet": carnet})
            assert status == 202, (carnet, "QR", status, qr)
            assert re.fullmatch(r"[a-f0-9]{32}", qr["request_id"]), qr
            assert re.fullmatch(r"\d{6}", qr["confirm_code"]), qr
            assert qr["qr_url"].startswith(f"{origin}/acceso/qr/"), qr
            assert not any(secret in json.dumps(qr) for secret in personal_emails), qr

            removed = subprocess.run([php, "artisan", "passkey:activar", carnet],
                                     cwd=backend, env=env, capture_output=True)
            assert removed.returncode != 0, carnet
            print(carnet, "OK: sólo QR + Google; vías antiguas cerradas")


if __name__ == "__main__":
    main()
