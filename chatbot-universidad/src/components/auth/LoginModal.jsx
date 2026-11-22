import { useEffect, useRef, useState } from "react";
import QRCode from "qrcode";
import { acceptSession, getAccessMode, loginByCarnet } from "../../services/authService";
import {
  cancelQr,
  exchangeQr,
  getQrStatus,
  isAbsoluteQrUrl,
  requestQr,
} from "../../services/qrAuthService";
import "./LoginModal.css";

const QR_KEY = "uncpggl_qr";

function secondsLeft(expiresAt) {
  const seconds = Math.ceil((Date.parse(expiresAt) - Date.now()) / 1000);
  return Number.isFinite(seconds) && seconds > 0 ? seconds : 0;
}

/**
 * Solicitudes protegidas: QR + Google OIDC.
 * Acceso local: carné (demo) o correo (institucional) + contraseña.
 * No hay passkeys ni aprobación por correo.
 */
function LoginModal({ onClose, onSuccess }) {
  const [carnet, setCarnet] = useState("");
  const [password, setPassword] = useState("");
  const [mode, setMode] = useState(null);
  const [useQr, setUseQr] = useState(false);
  const [request, setRequest] = useState(null);
  const [remoteStatus, setRemoteStatus] = useState("pending");
  const [qrImage, setQrImage] = useState("");
  const [remaining, setRemaining] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [notice, setNotice] = useState("");
  const callbacks = useRef({ onClose, onSuccess });
  const failures = useRef(0);

  callbacks.current = { onClose, onSuccess };

  const demo = mode === "demo";
  const localLogin = !useQr;
  const waiting = Boolean(request);

  useEffect(() => {
    let active = true;
    getAccessMode()
      .then((value) => {
        if (!active) return;
        setMode(value.mode);
      })
      .catch(() => {
        if (active) setError("No se pudo consultar el modo de acceso. Vuelve a abrir el formulario.");
      });
    try {
      const pending = sessionStorage.getItem(QR_KEY);
      if (pending) {
        const saved = JSON.parse(pending);
        if (isAbsoluteQrUrl(saved?.qr_url) && typeof saved.carnet === "string" && saved.carnet.trim()) {
          setRequest(saved);
        } else {
          sessionStorage.removeItem(QR_KEY);
          setUseQr(true);
        }
      }
    } catch { /* Sin sesión, sólo se pierde la espera. */ }
    return () => { active = false; };
  }, []);

  useEffect(() => {
    const onKey = (event) => {
      if (event.key === "Escape") callbacks.current.onClose?.();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  useEffect(() => {
    if (!request?.qr_url) {
      setQrImage("");
      return undefined;
    }
    let active = true;
    QRCode.toDataURL(request.qr_url, {
      width: 240,
      margin: 1,
      color: { dark: "#0f172a", light: "#ffffff" },
    }).then((url) => {
      if (active) setQrImage(url);
    }).catch(() => {
      if (active) setError("No se pudo generar el código QR. Intenta de nuevo.");
    });
    return () => { active = false; };
  }, [request?.qr_url]);

  useEffect(() => {
    if (!request) return undefined;
    const tick = () => setRemaining(secondsLeft(request.expires_at));
    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [request]);

  useEffect(() => {
    if (!request) return undefined;
    let active = true;
    let timer;

    const clear = () => {
      try { sessionStorage.removeItem(QR_KEY); } catch { /* Sólo memoria. */ }
      setRequest(null);
      setQrImage("");
    };

    const poll = async () => {
      try {
        const data = await getQrStatus(request.request_id, request.client_nonce);
        if (!active) return;
        failures.current = 0;
        setRemoteStatus(data.status);
        setNotice("");

        if (data.status === "approved") {
          const session = await exchangeQr(request.request_id, request.client_nonce);
          if (!active) return;
          acceptSession(session);
          clear();
          setNotice("Acceso confirmado. Abriendo tu sesión…");
          callbacks.current.onSuccess?.(session);
          callbacks.current.onClose?.();
          return;
        }

        if (["rejected", "cancelled", "expired", "consumed"].includes(data.status)) {
          const messages = {
            rejected: "Acceso rechazado desde el teléfono. No se creó ninguna sesión.",
            cancelled: "Solicitud cancelada. Genera un QR nuevo si deseas continuar.",
            expired: "El código QR caducó. Genera uno nuevo.",
            consumed: "Esta solicitud ya se utilizó. Genera un QR nuevo.",
          };
          setError(messages[data.status]);
          clear();
          return;
        }

        timer = setTimeout(poll, 2000);
      } catch (err) {
        if (!active) return;
        failures.current += 1;
        if (failures.current <= 3) {
          setNotice("Se perdió la conexión. Reintentando…");
          timer = setTimeout(poll, 3000);
        } else {
          setError(err.message || "No se pudo comprobar la autorización. Revisa tu conexión.");
          setNotice("");
          clear();
        }
      }
    };

    poll();
    return () => { active = false; clearTimeout(timer); };
  }, [request]);

  const resetQr = () => {
    setError(null);
    setNotice("");
    setRemoteStatus("pending");
    setRemaining(0);
    setQrImage("");
    try { sessionStorage.removeItem(QR_KEY); } catch { /* Sólo memoria. */ }
    setRequest(null);
  };

  const handleCancel = async () => {
    if (!request) return;
    setLoading(true);
    try {
      await cancelQr(request.request_id, request.client_nonce);
      resetQr();
      setNotice("Solicitud cancelada correctamente.");
    } catch (err) {
      setError(err.message || "No se pudo cancelar la solicitud.");
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    const value = carnet.trim();

    if (!value) {
      setError("Ingresa el código de tu carné para iniciar sesión.");
      return;
    }

    if (localLogin && !password) {
      setError("Ingresa tu contraseña para iniciar sesión.");
      return;
    }

    // La contraseña sale del estado del formulario antes de la petición y
    // nunca se conserva en memoria ni se guarda en el navegador.
    const submittedPassword = password;
    setPassword("");

    setLoading(true);
    setError(null);
    setNotice("");

    try {
      if (useQr) {
        const created = await requestQr(value);
        try { sessionStorage.setItem(QR_KEY, JSON.stringify(created)); } catch { /* Sólo memoria. */ }
        setRemoteStatus("pending");
        setRequest(created);
        setNotice("Escanea el QR con tu teléfono y confirma con tu cuenta Google.");
        return;
      }
      const result = await loginByCarnet(value, submittedPassword);
      callbacks.current.onSuccess?.(result);
      callbacks.current.onClose?.();
    } catch (err) {
      setError(err.message || "No se pudo iniciar sesión.");
    } finally {
      setLoading(false);
    }
  };

  const description = waiting
    ? "Esperando autorización. Abre el QR en tu teléfono, entra con Google y escribe el número de confirmación."
    : !mode
      ? "Consultando el modo de acceso…"
      : useQr
        ? "Ingresa tu carné para verificar tu identidad con Google desde el teléfono."
        : demo
          ? "Ingresa tu carné o correo de demostración y tu contraseña."
          : "Ingresa con el carné o correo y la contraseña de tu cuenta.";

  const statusLabel = remoteStatus === "google_authenticated"
    ? "Identidad verificada · esperando tu confirmación"
    : "Esperando autorización";

  return (
    <div className="login-overlay" onClick={() => callbacks.current.onClose?.()}>
      <div
        className="login-modal"
        role="dialog"
        aria-modal="true"
        aria-label="Iniciar sesión"
        onClick={(event) => event.stopPropagation()}
      >
        <header className="login-modal-header">
          <h3>Iniciar sesión</h3>
          <button
            type="button"
            className="login-modal-close"
            onClick={() => callbacks.current.onClose?.()}
            aria-label="Cerrar"
          >
            ✕
          </button>
        </header>

        <p className="login-modal-text">{description}</p>

        {mode && !waiting && <form className="login-form" onSubmit={handleSubmit} style={{ flexDirection: "column" }}>
          <input
            className="login-input"
            type="text"
            required
            value={carnet}
            onChange={(event) => setCarnet(event.target.value)}
            placeholder={useQr ? "Código de carné" : "Carné o correo"}
            aria-label={useQr ? "Código de carné" : "Carné o correo"}
            autoComplete="off"
            spellCheck="false"
            autoFocus
          />
          {localLogin && <input
            className="login-input"
            type="password"
            required
            autoComplete="current-password"
            aria-label="Contraseña"
            placeholder="Contraseña"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />}
          <button className="login-submit" type="submit" disabled={loading}>
            {loading
              ? "Verificando…"
              : useQr
                ? "Ingresar con QR"
                : "Ingresar"}
          </button>
        </form>}

        {waiting && <div className="qr-panel">
          <div className="qr-head">
            <span className="qr-status">{statusLabel}</span>
            <span className="qr-timer">{Math.floor(remaining / 60)}:{String(remaining % 60).padStart(2, "0")}</span>
          </div>
          <div className="qr-image-wrap">
            {qrImage ? <img src={qrImage} alt="Código QR para autorizar el acceso" /> : <span>Generando QR…</span>}
          </div>
          <p className="qr-code-label">Número de confirmación</p>
          <div className="qr-code" data-testid="confirmation-code">{request.confirm_code}</div>
          <p className="qr-carnet">Carné: <strong>{request.carnet}</strong></p>
          <button className="qr-cancel" type="button" onClick={handleCancel} disabled={loading}>
            Cancelar solicitud
          </button>
        </div>}

        {error && <div className="login-error">{error}</div>}
        {notice && <p role="status">{notice}</p>}
        {error && request === null && useQr && (
          <button className="qr-cancel" type="button" onClick={resetQr}>Generar QR nuevo</button>
        )}

        {mode && !waiting && (
          <button className="qr-cancel" type="button" disabled={loading} onClick={() => {
            setUseQr(!useQr);
            setPassword("");
            setError(null);
          }}>
            {useQr ? "Ingresar con contraseña" : "Usar QR con Google"}
          </button>
        )}

        {demo && <p className="login-hint">
          Para la demo tradicional prueba con <code>2024-CON-001</code> y la contraseña establecida por la administración. Las cuentas protegidas usan QR con Google.
        </p>}
      </div>
    </div>
  );
}

export default LoginModal;
