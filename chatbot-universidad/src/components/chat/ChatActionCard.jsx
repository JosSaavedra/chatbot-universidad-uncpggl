import DemoBadge from "./DemoBadge";
import "./ChatActionCard.css";

/**
 * Tarjeta de acción del chat para las intenciones de matrícula/inscripción.
 *
 * Se renderiza a partir de datos estructurados enviados por Laravel (no HTML
 * generado por la IA) y cubre tres tipos:
 *   - "calendar":           botón para abrir el calendario académico.
 *   - "admission":          lista de requisitos y documentos de admisión.
 *   - "matricula_options":  opciones para elegir (precios, fechas, requisitos).
 */
function ChatActionCard({ data, onOpenCalendar, onSelect }) {
  if (!data?.type) return null;

  if (data.type === "calendar") {
    return (
      <div className="chat-action">
        <button
          type="button"
          className="chat-action-main"
          onClick={() => onOpenCalendar?.()}
        >
          <span className="chat-action-icon" aria-hidden="true">
            📅
          </span>
          <span className="chat-action-info">
            <span className="chat-action-title">Calendario académico</span>
            <span className="chat-action-sub">
              Consulta las fechas de matrícula, inscripción, exámenes y más
            </span>
            <span className="chat-action-cta">Abrir calendario académico →</span>
          </span>
        </button>
      </div>
    );
  }

  if (data.type === "admission") {
    const admission = data.admission || {};
    const sections = Array.isArray(admission.sections) ? admission.sections : [];
    const showCalendar = sections.some((s) => s.show_calendar);

    return (
      <div className="chat-action">
        <div className="chat-action-panel">
          <div className="chat-action-panel-head">
            <span className="chat-action-icon" aria-hidden="true">
              📄
            </span>
            <span className="chat-action-title">
              {admission.title || "Información de admisión"}
              {admission.career_name ? ` · ${admission.career_name}` : ""}
            </span>
          </div>

          {admission.intro ? (
            <p className="chat-action-intro">{admission.intro}</p>
          ) : null}

          {sections.map((section, i) => (
            <div key={i} className="chat-action-section">
              <span className="chat-action-section-title">{section.heading}</span>
              <ul className="chat-action-list">
                {(section.items || []).map((item, j) => (
                  <li key={j}>{item}</li>
                ))}
              </ul>
            </div>
          ))}

          {showCalendar && (
            <button
              type="button"
              className="chat-action-calendar"
              onClick={() => onOpenCalendar?.()}
            >
              <span aria-hidden="true">📅</span> Consultar calendario
            </button>
          )}

          {admission.note ? (
            <p className="chat-action-note">ℹ️ {admission.note}</p>
          ) : null}

          {admission.demo ? <DemoBadge /> : null}
        </div>
      </div>
    );
  }

  if (data.type === "matricula_options") {
    const options = Array.isArray(data.options) ? data.options : [];

    return (
      <div className="chat-action">
        <div className="chat-action-panel">
          <span className="chat-action-title">
            ¿Qué deseas saber sobre la matrícula?
          </span>
          <div className="chat-action-options">
            {options.map((option, i) => (
              <button
                key={i}
                type="button"
                className="chat-action-option"
                onClick={() => onSelect?.(option.message)}
              >
                {option.label}
              </button>
            ))}
          </div>
        </div>
      </div>
    );
  }

  return null;
}

export default ChatActionCard;
