import "./QuickReplies.css";

const quickReplies = [
  { label: "📋 Ver carreras", text: "¿Qué carreras ofrece la universidad?" },
  { label: "💰 Precios", text: "¿Cuánto cuesta la inscripción y mensualidad?" },
  { label: "📅 Horarios", text: "¿Cuáles son los horarios generales?" },
  { label: "📆 Calendario", text: "¿Cuál es el calendario académico y las fechas de inscripción?" },
  { label: "🏛️ Facultades", text: "¿Qué facultades tiene la universidad?" },
  { label: "🎉 Eventos", text: "¿Cuáles son los próximos eventos universitarios?" },
];

function QuickReplies({ onSelect, visible }) {
  if (!visible) return null;

  return (
    <div className="quick-replies">
      <p className="quick-replies-title">Preguntas frecuentes:</p>
      <div className="quick-replies-grid">
        {quickReplies.map((qr, i) => (
          <button
            key={i}
            className="quick-reply-btn"
            onClick={() => onSelect(qr.text)}
          >
            {qr.label}
          </button>
        ))}
      </div>
    </div>
  );
}

export default QuickReplies;
