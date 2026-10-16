import "./GradesSuggestions.css";

/**
 * Tarjeta de calificaciones renderizada dentro del chat.
 *
 * Abre el módulo "Mis notas". Si el estudiante no tiene sesión, el propio
 * módulo muestra la pantalla de identificación (nunca se consideran las notas
 * accesibles solo por escribir un carné en el chat).
 */
function GradesSuggestions({ data, onOpenNotas }) {
  const personalized = Boolean(data?.personalized);

  return (
    <div className="grades-suggest">
      <button
        type="button"
        className="grades-suggest-btn"
        onClick={() => onOpenNotas?.()}
      >
        <span className="grades-suggest-icon" aria-hidden="true">
          📝
        </span>
        <span className="grades-suggest-info">
          <span className="grades-suggest-title">Ver mis notas</span>
          <span className="grades-suggest-sub">
            {personalized
              ? "Revisa tus notas por período, parciales y estado académico"
              : "Inicia sesión con tu carné para ver tus calificaciones"}
          </span>
          <span className="grades-suggest-cta">Abrir Mis notas →</span>
        </span>
      </button>
    </div>
  );
}

export default GradesSuggestions;
