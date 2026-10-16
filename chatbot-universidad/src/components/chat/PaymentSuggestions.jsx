import DemoBadge from "./DemoBadge";
import "./PaymentSuggestions.css";

/**
 * Tarjeta de pagos del estudiante renderizada dentro del chat.
 *
 * - Si hay datos personalizados (estudiante autenticado): muestra un resumen
 *   (pagado/pendiente y hasta 5 últimos movimientos) con un botón para ver
 *   todos los recibos.
 * - Si no hay datos personalizados: muestra un botón para abrir el módulo de
 *   pagos e iniciar sesión si no lo está.
 */
function PaymentSuggestions({ data, onOpenPagos, onLogin }) {
  const personalized = Boolean(data?.personalized && data?.payments);
  const payments = data?.payments || {};

  if (personalized) {
    return (
      <div className="payment-suggest">
        <div className="payment-suggest-head">🧾 Mis pagos</div>

        {payments?.student?.name ? (
          <p className="payment-suggest-sub">
            {payments.student.name} — Carné {payments.student.carnet}
            {payments.student.career ? ` · ${payments.student.career}` : ""}
          </p>
        ) : null}

        {payments?.summary && (
          <div className="payment-suggest-summary">
            <div className="payment-suggest-summary-item">
              <span className="payment-suggest-summary-label">Pagado</span>
              <strong className="payment-suggest-summary-value">
                C$ {Number(payments.summary.total_paid || 0).toLocaleString("es-NI", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}
              </strong>
            </div>
            <div className="payment-suggest-summary-item">
              <span className="payment-suggest-summary-label">Pendiente</span>
              <strong className="payment-suggest-summary-value pending">
                C$ {Number(payments.summary.total_pending || 0).toLocaleString("es-NI", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}
              </strong>
            </div>
          </div>
        )}

        {payments?.items && payments.items.length > 0 && (
          <ul className="payment-suggest-items">
            {payments.items.map((item) => (
              <li className="payment-suggest-item" key={item.id}>
                <div className="payment-suggest-item-line">
                  <span className="payment-suggest-item-concept">
                    {item.concept || "Concepto"}
                  </span>
                  <span className="payment-suggest-item-status">{item.status}</span>
                </div>
                <div className="payment-suggest-item-line meta">
                  <span>{item.period || "—"}</span>
                  <span>
                    C$ {Number(item.amount || 0).toLocaleString("es-NI", {
                      minimumFractionDigits: 2,
                      maximumFractionDigits: 2,
                    })}
                  </span>
                </div>
              </li>
            ))}
          </ul>
        )}

        <div className="payment-suggest-actions">
          <button
            type="button"
            className="payment-suggest-btn"
            onClick={() => onOpenPagos?.()}
          >
            Ver todos mis recibos →
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="payment-suggest">
      <button
        type="button"
        className="payment-suggest-btn"
        onClick={() => onOpenPagos?.()}
      >
        <span className="payment-suggest-icon" aria-hidden="true">
          🧾
        </span>
        <span className="payment-suggest-info">
          <span className="payment-suggest-title">Consultar mis pagos</span>
          <span className="payment-suggest-sub">
            Inicia sesión con tu carné para ver tus recibos de inscripción y
            mensualidades
          </span>
          <span className="payment-suggest-cta">Abrir pagos del estudiante →</span>
        </span>
      </button>

      <button
        type="button"
        className="payment-suggest-link"
        onClick={() => onLogin?.()}
      >
        🎓 Iniciar sesión con carné
      </button>
    </div>
  );
}

export default PaymentSuggestions;
