"""E2E de navegador: QR + Google OIDC con un proveedor simulado local.

No usa Gmail real ni credenciales de Google Cloud. Levanta un servidor HTTP
que sustituye los endpoints de autorización/token y enlaza el verificador de
prueba ya cubierto por GoogleOidcVerifierTest con RS256 real. Verifica la
experiencia del teléfono y de la computadora, incluido el regreso al chatbot.
"""

from __future__ import annotations

import http.server
import json
import os
import re
import shutil
import socketserver
import subprocess
import sys
import threading
import time
import urllib.parse
import urllib.request
from pathlib import Path

from playwright.sync_api import sync_playwright

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

ROOT = Path(__file__).resolve().parents[1]
BACKEND = ROOT
FRONTEND = ROOT.parent / "chatbot-universidad"
WORK = BACKEND / "storage" / "framework" / "testing" / "browser-qr"
CARNET = "2024-MED-001"
CLIENT_ID = "browser-test.apps.googleusercontent.com"
LARAVEL = "http://127.0.0.1:8443"
CALLBACK = f"{LARAVEL}/auth/google/callback"
GOOGLE = "http://127.0.0.1:9443"
PHP = shutil.which("php") or "php"
NPM = shutil.which("npm") or "npm"


class GoogleHandler(http.server.BaseHTTPRequestHandler):
    """Google mínimo: emite un `code` y un ID Token reconocible por el fake."""

    def log_message(self, *_args: object) -> None:
        return

    def _send(self, payload: dict, status: int = 200) -> None:
        raw = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def do_GET(self) -> None:
        parsed = urllib.parse.urlparse(self.path)
        if parsed.path != "/auth":
            self.send_error(404)
            return
        query = urllib.parse.parse_qs(parsed.query)
        assert query.get("response_type") == ["code"], query
        assert query.get("scope") == ["openid email profile"], query
        assert query.get("code_challenge_method") == ["S256"], query
        assert query.get("state") and query.get("nonce"), query
        target = CALLBACK + "?" + urllib.parse.urlencode(
            {
                "code": "browser-code-" + str(int(time.time_ns())),
                "state": query["state"][0],
            }
        )
        self.send_response(302)
        self.send_header("Location", target)
        self.end_headers()

    def do_POST(self) -> None:
        if urllib.parse.urlparse(self.path).path != "/token":
            self.send_error(404)
            return
        length = int(self.headers.get("Content-Length", "0"))
        fields = urllib.parse.parse_qs(self.rfile.read(length).decode())
        assert fields.get("grant_type") == ["authorization_code"], fields
        assert fields.get("code_verifier"), fields
        self._send(
            {
                "access_token": "browser-access",
                "token_type": "Bearer",
                "expires_in": 3600,
                "id_token": "google-jose",
            }
        )


class GoogleServer(threading.Thread):
    def __init__(self) -> None:
        super().__init__(daemon=True)
        self.httpd = socketserver.ThreadingTCPServer(("127.0.0.1", 9443), GoogleHandler)
        self.httpd.allow_reuse_address = True

    def run(self) -> None:
        self.httpd.serve_forever()

    def stop(self) -> None:
        self.httpd.shutdown()
        self.httpd.server_close()


def run(cmd: list[str], cwd: Path, env: dict[str, str]) -> subprocess.CompletedProcess:
    return subprocess.run(cmd, cwd=cwd, env=env, check=True, capture_output=True, text=True)


def wait_for(url: str) -> None:
    last: Exception | None = None
    for _ in range(80):
        try:
            urllib.request.urlopen(url, timeout=1).close()
            return
        except Exception as error:  # noqa: BLE001 - servidor todavía arrancando
            last = error
            time.sleep(0.5)
    raise RuntimeError(f"No respondió {url}: {last}")


def stop_process(process: subprocess.Popen) -> None:
    if process.poll() is not None:
        return
    if os.name == "nt":
        subprocess.run(["taskkill", "/PID", str(process.pid), "/T", "/F"], capture_output=True)
    else:
        process.terminate()
    try:
        process.wait(timeout=10)
    except subprocess.TimeoutExpired:
        process.kill()


def tail(path: Path, lines: int = 30) -> str:
    if not path.exists():
        return "(sin log)"
    return "\n".join(path.read_text(encoding="utf-8", errors="replace").splitlines()[-lines:])


def main() -> None:
    WORK.mkdir(parents=True, exist_ok=True)
    db = WORK / "browser.sqlite"
    db.unlink(missing_ok=True)

    links = [
        {
            "owner": "jose",
            "email": "owner-one@example.com",
            "carnets": "2024-MED-001,2023-ISI-001",
        },
        {
            "owner": "cesar",
            "email": "owner-two@example.com",
            "carnets": "2024-ADM-001,2022-ICI-001",
        },
        {
            "owner": "rodiver",
            "email": "owner-three@example.com",
            "carnets": "2025-ENF-001,2023-MED-002",
        },
    ]
    env = os.environ.copy()
    env.update(
        {
            "APP_ENV": "local",
            "APP_DEBUG": "true",
            "DB_CONNECTION": "sqlite",
            "DB_DATABASE": str(db),
            "CACHE_STORE": "array",
            "SESSION_DRIVER": "file",
            "QUEUE_CONNECTION": "sync",
            "APP_URL": LARAVEL,
            "QR_AUTH_URL": "",
            "GOOGLE_CLIENT_ID": CLIENT_ID,
            "GOOGLE_CLIENT_SECRET": "browser-secret",
            "GOOGLE_REDIRECT_URI": CALLBACK,
            "GOOGLE_AUTH_URL": f"{GOOGLE}/auth",
            "GOOGLE_TOKEN_URL": f"{GOOGLE}/token",
            "GOOGLE_JWKS_URL": f"{GOOGLE}/jwks",
            "GOOGLE_ACCOUNT_LINKS": json.dumps(links),
            "QR_AUTH_TEST_OIDC_VERIFIER": r"Tests\Support\FakeGoogleOidcVerifier",
            "STUDENT_ACCESS_MODE": "institutional",
        }
    )
    env["VITE_API_URL"] = f"{LARAVEL}/api"

    google = GoogleServer()
    google.start()
    run([PHP, "artisan", "config:clear", "--no-interaction"], BACKEND, env)
    run([PHP, "artisan", "migrate", "--force", "--no-interaction"], BACKEND, env)
    run([PHP, "artisan", "qr-google:sync-links", "--no-interaction"], BACKEND, env)
    run([PHP, "artisan", "db:seed", "--force", "--no-interaction"], BACKEND, env)

    server_log = WORK / "server.log"
    frontend_log = WORK / "frontend.log"
    server = subprocess.Popen(
        [
            PHP, "artisan", "serve", "--host=127.0.0.1", "--port=8443",
            "--no-reload", "--no-interaction",
        ],
        cwd=BACKEND,
        env=env,
        stdout=server_log.open("w", encoding="utf-8"),
        stderr=subprocess.STDOUT,
    )
    frontend = subprocess.Popen(
        [
            NPM, "run", "dev", "--",
            "--mode", "browser-qr",
            "--host", "127.0.0.1", "--port", "5178", "--strictPort",
        ],
        cwd=FRONTEND,
        env=env,
        stdout=frontend_log.open("w", encoding="utf-8"),
        stderr=subprocess.STDOUT,
    )

    try:
        wait_for(f"{LARAVEL}/api/auth/mode")
        wait_for("http://127.0.0.1:5178/")

        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True)
            desktop = browser.new_context(ignore_https_errors=True, viewport={"width": 1280, "height": 900})
            phone = browser.new_context(
                ignore_https_errors=True,
                viewport={"width": 390, "height": 844},
                is_mobile=True,
                has_touch=True,
            )
            page = desktop.new_page()
            mobile = phone.new_page()
            errors: list[str] = []
            console: list[str] = []
            page.on("pageerror", lambda error: errors.append(str(error)))
            mobile.on("pageerror", lambda error: errors.append(str(error)))
            page.on("console", lambda message: console.append(f"{message.type}: {message.text}"))

            captured: dict[str, object] = {}

            def capture(response: object) -> None:
                if (
                    response.url.endswith("/api/qr-auth/request")
                    and response.request.method == "POST"
                    and response.status == 202
                ):
                    captured.update(response.json())

            page.on("response", capture)

            try:
                page.goto("http://127.0.0.1:5178/", wait_until="networkidle")
                page.get_by_role("button", name="Iniciar sesión").click()
                dialog = page.get_by_role("dialog")
                local_id = dialog.get_by_role("textbox", name="Carné o correo")
                for identifier in ("2024-CON-001", "estudiante07@example.com"):
                    local_id.fill(identifier)
                    assert local_id.evaluate("(input) => input.checkValidity()"), identifier
                dialog.get_by_role("button", name="Usar QR con Google").click()
                dialog.locator("input.login-input").first.fill(CARNET)
                dialog.get_by_role("button", name="Ingresar con QR").click()
                dialog.locator(".qr-status").wait_for(timeout=15000)

                assert captured, "React no llamó a /api/qr-auth/request"
                qr_url = str(captured["qr_url"])
                assert re.fullmatch(rf"{re.escape(LARAVEL)}/acceso/qr/[a-f0-9]{{64}}", qr_url)
                nonce = str(captured["client_nonce"])
                code = str(captured["confirm_code"])
                assert re.fullmatch(r"[0-9]{6}", code), code
                assert re.fullmatch(r"[a-f0-9]{64}", nonce), nonce
                assert dialog.locator("img[src^='data:image']").count() == 1, "Falta el QR"
                assert dialog.locator("[data-testid='confirmation-code']").inner_text() == code

                assert dialog.locator(".qr-carnet strong").inner_text() == CARNET
                page.reload(wait_until="domcontentloaded")
                page.get_by_role("button", name="Iniciar sesión").click()
                dialog.locator(".qr-carnet strong").wait_for()
                assert dialog.locator(".qr-carnet strong").inner_text() == CARNET
                assert dialog.locator("[data-testid='confirmation-code']").inner_text() == code

                # ---- Teléfono: QR -> Google simulado -> revisión -> confirmación ----
                scan_response = mobile.goto(qr_url, wait_until="domcontentloaded")
                assert scan_response.headers.get("referrer-policy") == "no-referrer"
                cache_control = scan_response.headers.get("cache-control", "")
                assert "private" in cache_control and "no-store" in cache_control, cache_control
                mobile.get_by_role("heading", name="Solicitud de acceso QR").wait_for()
                mobile.get_by_text(CARNET, exact=False).first.wait_for()
                assert not re.search(rf"\b{code}\b", mobile.content())
                mobile.get_by_role("link", name="Continuar con Google").click()
                mobile.get_by_role("heading", name="Confirma la solicitud").wait_for(timeout=15000)
                assert not re.search(rf"\b{code}\b", mobile.content())
                mobile.locator("#confirm_code").fill(code)
                mobile.get_by_role("button", name="Autorizar acceso").click()
                mobile.get_by_text("Acceso autorizado", exact=False).wait_for(timeout=10000)

                # ---- Computadora: poll, canje de token, regreso al chatbot ----
                page.get_by_role("dialog").wait_for(state="detached", timeout=15000)
                page.get_by_title("Sesión activa").wait_for(timeout=10000)
                assert CARNET in page.get_by_title("Sesión activa").inner_text()
                assert not errors, errors
                page.screenshot(path=str(WORK / "desktop-autorizado.png"), full_page=True)
                mobile.screenshot(path=str(WORK / "telefono-autorizado.png"), full_page=True)
                print("E2E QR + Google: aprobado en el teléfono y sesión abierta en la computadora.")
            except Exception:
                print(f"Diagnóstico: QR generado={bool(captured)}, ruta móvil={urllib.parse.urlsplit(mobile.url).path}")
                print(f"Consola: {console[-10:]}")
                print(f"Errores de página: {errors}")
                raise
            finally:
                browser.close()
    finally:
        stop_process(frontend)
        stop_process(server)
        google.stop()

    print(f"Logs: {server_log} | {frontend_log}")


if __name__ == "__main__":
    try:
        main()
    except Exception as error:  # noqa: BLE001 - reporte final legible
        print(f"E2E falló: {error}")
        print("--- backend ---")
        print(tail(WORK / "server.log"))
        print("--- frontend ---")
        print(tail(WORK / "frontend.log"))
        raise
