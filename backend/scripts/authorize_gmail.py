"""Autoriza un remitente de Gmail con un cliente OAuth de escritorio."""

import argparse
import base64
import hashlib
import http.client
import http.server
import json
import os
from pathlib import Path
import secrets
import time
from urllib.parse import parse_qs, urlencode, urlsplit
import webbrowser


SCOPE = "https://www.googleapis.com/auth/gmail.send"
REPO = Path(__file__).resolve().parents[2]


class OAuthCallback(http.server.BaseHTTPRequestHandler):
    def log_message(self, *_args):
        pass

    def do_GET(self):
        route = urlsplit(self.path)
        data = parse_qs(route.query)
        state = data.get("state", [""])[0]
        valid = (
            route.path == "/"
            and self.headers.get("Host") == f"127.0.0.1:{self.server.server_port}"
            and state.isascii()
            and secrets.compare_digest(state, self.server.oauth_state)
        )
        if valid:
            self.server.oauth_result = data
        self.send_response(200 if valid else 400)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Referrer-Policy", "no-referrer")
        self.end_headers()
        self.wfile.write("Vuelve a la terminal para ver el resultado.".encode() if valid else b"Solicitud invalida.")


def authorize(client_id, from_address):
    verifier = secrets.token_urlsafe(64)
    challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).decode().rstrip("=")
    with http.server.ThreadingHTTPServer(("127.0.0.1", 0), OAuthCallback) as server:
        server.oauth_state = secrets.token_urlsafe(32)
        server.oauth_result = None
        server.timeout = 1
        redirect = f"http://127.0.0.1:{server.server_port}/"
        query = urlencode({
            "client_id": client_id, "redirect_uri": redirect, "response_type": "code",
            "scope": SCOPE, "access_type": "offline", "prompt": "consent select_account",
            "login_hint": from_address, "state": server.oauth_state,
            "code_challenge": challenge, "code_challenge_method": "S256",
        })
        if not webbrowser.open("https://accounts.google.com/o/oauth2/v2/auth?" + query):
            raise SystemExit("No se pudo abrir el navegador predeterminado.")
        deadline = time.monotonic() + 300
        while server.oauth_result is None and time.monotonic() < deadline:
            server.handle_request()
        result = server.oauth_result or {}
    if "error" in result or not result.get("code"):
        raise SystemExit("Autorización cancelada, rechazada o vencida.")
    return result["code"][0], redirect, verifier


def refresh_token(client_id, client_secret, code, redirect, verifier):
    body = urlencode({
        "client_id": client_id, "client_secret": client_secret, "code": code,
        "redirect_uri": redirect, "code_verifier": verifier, "grant_type": "authorization_code",
    })
    connection = http.client.HTTPSConnection("oauth2.googleapis.com", timeout=15)
    try:
        connection.request("POST", "/token", body, {"Content-Type": "application/x-www-form-urlencoded"})
        response = connection.getresponse()
        data = json.loads(response.read(65536))
        if response.status != 200 or SCOPE not in data.get("scope", "").split():
            raise SystemExit("Google rechazó el canje o no concedió gmail.send.")
        token = data.get("refresh_token")
        if not isinstance(token, str) or not token:
            raise SystemExit("Google no devolvió un refresh token.")
        return token
    finally:
        connection.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--client", required=True, type=Path, help="JSON privado del cliente Desktop")
    parser.add_argument("--output", required=True, type=Path, help="Archivo .env privado nuevo")
    parser.add_argument("--from-address", required=True, help="Cuenta Gmail que debes seleccionar en Google")
    args = parser.parse_args()
    source, target = args.client.resolve(), args.output.resolve()
    if source.is_relative_to(REPO) or target.is_relative_to(REPO):
        parser.error("Usa una carpeta privada fuera del repositorio para ambos archivos.")
    if args.output.is_symlink() or target.exists() or not target.parent.is_dir():
        parser.error("Elige un archivo nuevo dentro de una carpeta privada existente.")
    sender = args.from_address.strip()
    if "@" not in sender or any(char.isspace() or char in "'\\" for char in sender):
        parser.error("Indica la dirección Gmail del remitente.")
    try:
        client = json.loads(source.read_text(encoding="utf-8-sig"))["installed"]
        client_id, secret = client["client_id"], client["client_secret"]
        if not all(isinstance(value, str) and value for value in (client_id, secret)):
            raise ValueError
    except (OSError, ValueError, KeyError, TypeError):
        raise SystemExit("No se pudo leer un JSON válido de cliente OAuth de escritorio.") from None
    try:
        code, redirect, verifier = authorize(client_id, sender)
        token = refresh_token(client_id, secret, code, redirect, verifier)
        values = {
            "GMAIL_OAUTH_CLIENT_ID": client_id, "GMAIL_OAUTH_CLIENT_SECRET": secret,
            "GMAIL_OAUTH_REFRESH_TOKEN": token, "MAIL_FROM_ADDRESS": sender,
        }
        if any(any(char in value for char in "'\\\r\n\0") for value in values.values()):
            raise ValueError
        fd = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, "w", encoding="utf-8") as output:
            for key, value in values.items():
                output.write(f"{key}='{value}'\n")
    except (OSError, ValueError, http.client.HTTPException):
        raise SystemExit("No se pudo completar el canje OAuth o guardar el archivo privado.") from None
    print("Autorización guardada en el archivo privado indicado.")


if __name__ == "__main__":
    main()
