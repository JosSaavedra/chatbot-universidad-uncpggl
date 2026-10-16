import "./QuickReplies.css";

const quickReplies = [
  { label: "📋 Ver carreras", action: "carreras" },
  { label: "🕐 Mi horario", action: "horario" },
  { label: "🧾 Pagos del estudiante", action: "pagos" },
  { label: "📍 Sedes y facultades", action: "oferta", tab: "sede" },
  { label: "💰 Precios", text: "¿Cuánto cuesta la inscripción y mensualidad?" },
  { label: "🕐 Horario de atención", text: "¿Cuál es el horario de atención de la universidad?" },
  { label: "📆 Calendario", action: "calendar" },
];

function QuickReplies({ onSelect, onOpenCalendar, onOpenOferta, onOpenCarreras, onOpenPagos, onOpenHorario, visible }) {
  if (!visible) return null;

  return (
    <div className="quick-replies">
      <p className="quick-replies-title">Preguntas frecuentes:</p>
      <div className="quick-replies-grid">
        {quickReplies.map((qr, i) => (
          <button
            key={i}
            className="quick-reply-btn"
            onClick={() => {
              if (qr.action === "calendar") {
                onOpenCalendar?.();
              } else if (qr.action === "oferta") {
                onOpenOferta?.(qr.tab);
              } else if (qr.action === "carreras") {
                onOpenCarreras?.();
              } else if (qr.action === "pagos") {
                onOpenPagos?.();
              } else if (qr.action === "horario") {
                onOpenHorario?.();
              } else {
                onSelect(qr.text);
              }
            }}
          >
            {qr.label}
          </button>
        ))}
      </div>
    </div>
  );
}

export default QuickReplies;
