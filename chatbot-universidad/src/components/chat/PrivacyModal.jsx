import { useEffect } from "react";
import "./PrivacyModal.css";

/**
 * Aviso de privacidad del prototipo.
 *
 * La nota extensa que antes se mostraba siempre bajo la barra de escritura se
 * movió a este modal, accesible desde el enlace discreto "Privacidad y datos
 * de demostración", para no recargar la vista del chat.
 */
function PrivacyModal({ onClose }) {
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "Escape") onClose?.();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  return (
    <div className="privacy-overlay" onClick={() => onClose?.()}>
      <div
        className="privacy-modal"
        role="dialog"
        aria-modal="true"
        aria-label="Privacidad y datos de demostración"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="privacy-modal-header">
          <h3>Privacidad y datos de demostración</h3>
          <button
            type="button"
            className="privacy-modal-close"
            onClick={() => onClose?.()}
            aria-label="Cerrar"
          >
            ✕
          </button>
        </header>

        <div className="privacy-modal-body">
          <p>
            <strong>🔒 Prototipo de demostración.</strong> Parte de la
            información mostrada (carreras, horarios, admisión y calendario)
            puede ser simulada o de ejemplo.
          </p>
          <p>
            <strong>Procesamiento con IA.</strong> Tus mensajes se envían a un
            servicio de IA (Groq) para redactar la respuesta.
          </p>
          <p>
            <strong>Datos sensibles.</strong> No ingreses contraseñas, datos
            bancarios ni otra información personal sensible.
          </p>
          <p>
            <strong>Historial.</strong> La conversación se conserva durante la
            sesión para mostrar el historial y puedes borrarla con «Nueva
            conversación».
          </p>
        </div>
      </div>
    </div>
  );
}

export default PrivacyModal;
