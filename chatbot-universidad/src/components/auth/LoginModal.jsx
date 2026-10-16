import { useEffect, useState } from "react";
import { useAuth } from "../../hooks/useAuth";
import "./LoginModal.css";

/**
 * Inicio de sesión por código de carné.
 *
 * Se usa para acceder al horario personalizado (y, a futuro, a otra
 * información académica propia). El prototipo no usa contraseña: basta el
 * carné del estudiante de demostración.
 */
function LoginModal({ onClose, onSuccess }) {
  const { login } = useAuth();
  const [carnet, setCarnet] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "Escape") onClose?.();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    const value = carnet.trim();

    if (!value) {
      setError("Ingresa el código de tu carné para iniciar sesión.");
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const result = await login(value);
      onSuccess?.(result);
      onClose?.();
    } catch (err) {
      setError(err.message || "No se pudo iniciar sesión.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-overlay" onClick={() => onClose?.()}>
      <div
        className="login-modal"
        role="dialog"
        aria-modal="true"
        aria-label="Iniciar sesión con carné"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="login-modal-header">
          <h3>Iniciar sesión</h3>
          <button
            type="button"
            className="login-modal-close"
            onClick={() => onClose?.()}
            aria-label="Cerrar"
          >
            ✕
          </button>
        </header>

        <p className="login-modal-text">
          Ingresa el código de tu carné para ver tu horario personalizado.
          La consulta personal requiere sesión; el horario general no.
        </p>

        <form className="login-form" onSubmit={handleSubmit}>
          <input
            className="login-input"
            type="text"
            value={carnet}
            onChange={(e) => setCarnet(e.target.value)}
            placeholder="Código de carné (ej.: 2024-MED-001)"
            aria-label="Código de carné"
            autoComplete="off"
            spellCheck="false"
            autoFocus
          />
          <button className="login-submit" type="submit" disabled={loading}>
            {loading ? "Ingresando…" : "Ingresar"}
          </button>
        </form>

        {error && <div className="login-error">{error}</div>}

        <p className="login-hint">
          Modo demostración: prueba con <code>2024-MED-001</code>,{" "}
          <code>2023-ISI-001</code> o <code>2024-ADM-001</code>.
        </p>
      </div>
    </div>
  );
}

export default LoginModal;
