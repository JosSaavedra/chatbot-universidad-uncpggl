import CareerIcon from "../carreras/careerIcons";
import DemoBadge from "./DemoBadge";
import "./CareerSuggestions.css";

const PENDING = "Información pendiente de publicación";
const MAX_VISIBLE = 4;

const hasText = (value) => typeof value === "string" && value.trim().length > 0;

const formatDuration = (years) =>
  years || years === 0
    ? `${years} ${years === 1 ? "año" : "años"}`
    : PENDING;

const formatMoney = (value) => {
  const n = Number(value);
  if (!Number.isFinite(n)) return null;
  return `C$${n.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
};

/**
 * Tarjetas de carreras renderizadas dentro del chat a partir de datos
 * estructurados enviados por Laravel (no HTML generado por la IA).
 *
 * Muestra los costos de inscripción y mensualidad (datos de demostración). No
 * incluye accesos al módulo de pagos: los costos de una carrera son información
 * pública y no deben confundirse con los pagos personales del estudiante.
 */
function CareerSuggestions({ data, onOpenCarreras }) {
  const careers = Array.isArray(data?.careers) ? data.careers : [];
  if (careers.length === 0) return null;

  const visible = careers.slice(0, MAX_VISIBLE);
  const remaining = careers.length - visible.length;

  const hasFees = visible.some(
    (c) => c.enrollment_fee != null || c.monthly_fee != null
  );

  return (
    <div className="career-suggest">
      <div className="career-suggest-head">
        {careers.length === 1 ? "Carrera" : `${careers.length} carreras`}
      </div>

      <div className="career-suggest-list">
        {visible.map((career) => {
          const enrollment = formatMoney(career.enrollment_fee);
          const monthly = formatMoney(career.monthly_fee);

          return (
            <button
              key={career.id}
              type="button"
              className="career-suggest-item"
              onClick={() => onOpenCarreras?.(career.id)}
            >
              <span className="career-suggest-icon">
                <CareerIcon career={career} />
              </span>
              <span className="career-suggest-info">
                <span className="career-suggest-name">{career.name}</span>
                <span className="career-suggest-meta">
                  {formatDuration(career.duration_years)}
                  {hasText(career.faculty) ? ` · ${career.faculty}` : ""}
                </span>
                {(enrollment || monthly) && (
                  <span className="career-suggest-cost">
                    {enrollment && (
                      <span>
                        <strong>Inscripción:</strong> {enrollment}
                      </span>
                    )}
                    {monthly && (
                      <span>
                        <strong>Mensualidad:</strong> {monthly}
                      </span>
                    )}
                  </span>
                )}
                <span className="career-suggest-cta">Ver detalles →</span>
              </span>
            </button>
          );
        })}
      </div>

      {remaining > 0 && (
        <button
          type="button"
          className="career-suggest-all"
          onClick={() => onOpenCarreras?.()}
        >
          Ver todas las carreras ({careers.length})
        </button>
      )}

      <div className="career-suggest-foot">
        {hasFees && <DemoBadge />}
      </div>
    </div>
  );
}

export default CareerSuggestions;
