import DemoBadge from "./DemoBadge";
import {
  dayShort,
  timeRange,
  scheduleMeta,
  emptyScheduleMessage,
  selectionFromSchedule,
} from "../../utils/scheduleDisplay";
import "./ScheduleCard.css";

/**
 * Tarjeta de horario dentro del chat.
 *
 * Se renderiza con datos estructurados de Laravel (no HTML de la IA) y cubre
 * dos casos:
 *   - "schedule":        horario resuelto (personal o general por carrera/año/grupo).
 *   - "schedule_picker": no hay datos suficientes; se ofrece el modo demostración
 *                        y el inicio de sesión con carné.
 */
function ScheduleCard({ data, onOpenHorario, onLogin }) {
  if (!data?.type) return null;

  if (data.type === "schedule_picker") {
    const picker = data.schedule_picker || {};
    const selection = {
      careerId: picker.career_id ?? null,
      year: picker.year ?? null,
      group: picker.group ?? null,
    };

    return (
      <div className="schedule-card">
        <div className="schedule-card-head">
          <span className="schedule-card-icon" aria-hidden="true">
            🕐
          </span>
          <span className="schedule-card-title">Horarios de clases</span>
        </div>

        <p className="schedule-card-intro">
          Puedes consultar el horario general de una carrera en el modo
          demostración, o iniciar sesión con tu carné para ver tu horario
          personalizado.
        </p>

        <div className="schedule-card-actions">
          <button
            type="button"
            className="schedule-btn primary"
            onClick={() => onOpenHorario?.(selection)}
          >
            Ver horarios (modo demostración)
          </button>
          <button
            type="button"
            className="schedule-btn"
            onClick={() => onLogin?.()}
          >
            🎓 Iniciar sesión con carné
          </button>
        </div>

        <DemoBadge />
      </div>
    );
  }

  // data.type === "schedule"
  const schedule = data.schedule || {};
  const query = schedule.query || {};
  const classes = Array.isArray(schedule.focused) ? schedule.focused : [];
  const current = schedule.current_class;
  const next = schedule.next_class;
  const isEmpty = schedule.focused_empty || classes.length === 0;

  const title = schedule.personalized
    ? "Mi horario de clases"
    : "Horario de clases";
  const meta = scheduleMeta(schedule);

  const selection = selectionFromSchedule(schedule);

  return (
    <div className="schedule-card">
      <div className="schedule-card-head">
        <span className="schedule-card-icon" aria-hidden="true">
          🕐
        </span>
        <span className="schedule-card-heading">
          <span className="schedule-card-title">{title}</span>
          {meta ? <span className="schedule-card-sub">{meta}</span> : null}
        </span>
      </div>

      {current ? (
        <div className="schedule-highlight current">
          <span className="schedule-highlight-tag">En curso</span>
          <span className="schedule-highlight-subject">
            {current.subject}
          </span>
          <span className="schedule-highlight-meta">
            🕐 {timeRange(current)} · 📍 {current.classroom || "por asignar"}
            {current.building ? ` · ${current.building}` : ""}
            {current.teacher ? ` · 👨‍🏫 ${current.teacher}` : ""}
          </span>
        </div>
      ) : null}

      {next ? (
        <div className="schedule-highlight next">
          <span className="schedule-highlight-tag">
            Próxima clase · {dayShort(next.day)}
          </span>
          <span className="schedule-highlight-subject">{next.subject}</span>
          <span className="schedule-highlight-meta">
            🕐 {timeRange(next)} · 📍 {next.classroom || "por asignar"}
            {next.building ? ` · ${next.building}` : ""}
            {next.teacher ? ` · 👨‍🏫 ${next.teacher}` : ""}
          </span>
        </div>
      ) : null}

      {isEmpty ? (
        <p className="schedule-card-empty">{emptyScheduleMessage(query)}</p>
      ) : (
        <ul className="schedule-card-list">
          {classes.map((c) => (
            <li
              key={c.id}
              className={`schedule-item${c.is_current ? " is-current" : ""}`}
            >
              <span className="schedule-item-when">
                {dayShort(c.day)} · {timeRange(c)}
              </span>
              <span className="schedule-item-subject">{c.subject}</span>
              <span className="schedule-item-meta">
                📍 {c.classroom || "por asignar"}
                {c.building ? ` · ${c.building}` : ""}
                {c.teacher ? ` · 👨‍🏫 ${c.teacher}` : ""}
              </span>
            </li>
          ))}
        </ul>
      )}

      <div className="schedule-card-actions">
        <button
          type="button"
          className="schedule-btn primary"
          onClick={() => onOpenHorario?.(selection)}
        >
          Ver horario completo
        </button>
        {!schedule.personalized ? (
          <button
            type="button"
            className="schedule-btn"
            onClick={() => onLogin?.()}
          >
            🎓 Mi horario (iniciar sesión)
          </button>
        ) : null}
      </div>

      <DemoBadge />
    </div>
  );
}

export default ScheduleCard;
