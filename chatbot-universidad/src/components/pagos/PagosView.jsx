import { useEffect, useMemo, useState } from "react";
import { consultarPagos, consultarRecibo } from "../../services/pagosService";
import { getToken } from "../../services/authService";
import { useAuth } from "../../hooks/useAuth";
import { escapeHtml, printInPopup, renderDocument } from "../../utils/pdfDocument";
import "./PagosView.css";

const CONCEPT_LABELS = {
  inscripcion: "Inscripción",
  mensualidad: "Mensualidad",
  matricula: "Matrícula",
  otros: "Otros",
};

const STATUS_LABELS = {
  pagado: "Pagado",
  pendiente: "Pendiente",
  vencido: "Vencido",
};

const STATUS_ORDER = { vencido: 0, pendiente: 1, pagado: 2 };

const FILTERS = [
  { id: "todos", label: "Todos" },
  { id: "inscripcion", label: "Inscripción" },
  { id: "mensualidad", label: "Mensualidades" },
  { id: "pagado", label: "Pagados" },
  { id: "pendiente", label: "Pendientes" },
];

const formatMoney = (amount) => {
  const value = Number(amount) || 0;
  return `C$ ${value.toLocaleString("es-NI", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
};

const formatDate = (value) => {
  if (!value) return null;
  const parsed = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(parsed.getTime())) return String(value);
  return parsed.toLocaleDateString("es-NI", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

function PagosView({ onBack, onLogin }) {
  const { isAuthed, token } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [result, setResult] = useState(null);
  const [filter, setFilter] = useState("todos");
  const [receipt, setReceipt] = useState(null);
  const [printError, setPrintError] = useState("");

  useEffect(() => {
    if (!receipt) return undefined;
    const onKey = (e) => {
      if (e.key === "Escape") setReceipt(null);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [receipt]);

  useEffect(() => {
    if (!isAuthed || !token) return undefined;
    let active = true;

    const load = async () => {
      setLoading(true);
      setError(null);
      setResult(null);
      setReceipt(null);

      try {
        const data = await consultarPagos(token);
        if (active) {
          setResult(data);
          setFilter("todos");
        }
      } catch (err) {
        if (active) {
          setError(err.message || "No se pudieron consultar los pagos.");
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    };

    load();
    return () => {
      active = false;
    };
  }, [isAuthed, token]);

  const ordered = useMemo(() => {
    const payments = result?.payments || [];
    return [...payments].sort((a, b) => {
      const byStatus =
        (STATUS_ORDER[a.status] ?? 3) - (STATUS_ORDER[b.status] ?? 3);
      if (byStatus !== 0) return byStatus;
      return String(b.due_date || "").localeCompare(String(a.due_date || ""));
    });
  }, [result]);

  const filtered = useMemo(() => {
    return ordered.filter((p) => {
      if (filter === "todos") return true;
      if (filter === "pagado") return p.status === "pagado";
      if (filter === "pendiente") return p.status !== "pagado";
      return p.concept === filter;
    });
  }, [ordered, filter]);

  const student = result?.student;
  const fieldsFor = (receipt, student) => [
    ["Nº de recibo", receipt.receipt_number],
    ["Estudiante", student?.name],
    ["Carné", student?.carnet],
    ["Carrera", student?.career],
    ["Concepto", CONCEPT_LABELS[receipt.concept] || receipt.concept],
    ["Descripción", receipt.description],
    ["Período", receipt.period],
    ["Método de pago", receipt.payment_method],
    ["Estado", STATUS_LABELS[receipt.status] || receipt.status],
    ["Fecha de pago", receipt.paid_at ? formatDate(receipt.paid_at) : "No pagado"],
    ["Fecha límite", formatDate(receipt.due_date)],
    ["Referencia", receipt.reference],
  ];
  const fields = receipt ? fieldsFor(receipt, student) : [];

  const printReceipt = async () => {
    const popup = window.open("", "_blank", "width=820,height=960");
    try {
      setPrintError("");
      if (!popup) throw new Error("Permite las ventanas emergentes para imprimir.");
      const { payment, student: owner } = await consultarRecibo(receipt.id, token);
      if (getToken() !== token) throw new Error("La sesión ha cambiado.");
      const title = `Recibo ${payment.receipt_number || payment.id}`;
      const rows = [...fieldsFor(payment, owner), ["Monto", formatMoney(payment.amount)]]
        .map(([label, value]) => `<tr><th scope="row">${escapeHtml(label)}</th><td>${escapeHtml(value || "—")}</td></tr>`).join("");
      printInPopup(renderDocument({
        title,
        eyebrow: "Universidad Nacional Comandante Padre Gaspar García Laviana",
        body: `<table>${rows}</table>`,
        footerLeft: "Documento de demostración",
      }), title, popup);
    } catch (err) {
      popup?.close();
      setPrintError(err.message);
    }
  };
  const summary = result?.summary;
  const initials = (student?.name || "?")
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join("")
    .toUpperCase();

  return (
    <div className="pagos-view">
      <div className="pagos-toolbar">
        {onBack && (
          <button className="pagos-back-btn" onClick={onBack}>
            ← Volver al asistente
          </button>
        )}
      </div>

      <section className="pagos-hero">
        <h2 className="pagos-hero-title">Pagos y recibos del estudiante</h2>
        <p className="pagos-hero-text">
          Consulta el estado de tus pagos de inscripción y mensualidades. Por
          seguridad, esta información requiere que inicies sesión con tu carné.
        </p>

        {!isAuthed ? (
          <button
            className="pagos-search-btn"
            type="button"
            onClick={() => onLogin?.()}
          >
            🎓 Iniciar sesión con carné
          </button>
        ) : null}

        {isAuthed && loading ? (
          <p className="pagos-hero-text">Cargando tus pagos…</p>
        ) : null}

        {error && <div className="pagos-error">{error}</div>}
      </section>

      {result && (
        <>
          <section className="pagos-student">
            <div className="pagos-student-avatar" aria-hidden="true">
              {initials}
            </div>
            <div className="pagos-student-info">
              <h3>{student?.name || "Estudiante"}</h3>
              <p>{student?.career || "Carrera no registrada"}</p>
              <div className="pagos-student-meta">
                <span className="pagos-chip">Carné {student?.carnet}</span>
                {student?.year_of_study ? (
                  <span className="pagos-chip">
                    {student.year_of_study}º año
                  </span>
                ) : null}
                {student?.faculty ? (
                  <span className="pagos-chip">{student.faculty}</span>
                ) : null}
              </div>
            </div>
          </section>

          <section className="pagos-summary">
            <div className="pagos-summary-card paid">
              <span className="pagos-summary-label">Total pagado</span>
              <strong className="pagos-summary-value">
                {formatMoney(summary?.total_paid)}
              </strong>
              <span className="pagos-summary-sub">
                {summary?.paid_count || 0}{" "}
                {(summary?.paid_count || 0) === 1 ? "recibo" : "recibos"}
              </span>
            </div>
            <div className="pagos-summary-card pending">
              <span className="pagos-summary-label">Saldo pendiente</span>
              <strong className="pagos-summary-value">
                {formatMoney(summary?.total_pending)}
              </strong>
              <span className="pagos-summary-sub">
                {summary?.pending_count || 0}{" "}
                {(summary?.pending_count || 0) === 1 ? "recibo" : "recibos"}
              </span>
            </div>
            <div className="pagos-summary-card neutral">
              <span className="pagos-summary-label">Total facturado</span>
              <strong className="pagos-summary-value">
                {formatMoney(summary?.total || summary?.total_paid + summary?.total_pending)}
              </strong>
              <span className="pagos-summary-sub">
                {summary?.count || 0}{" "}
                {(summary?.count || 0) === 1 ? "recibo" : "recibos"}
              </span>
            </div>
          </section>

          <div className="pagos-filters" role="tablist" aria-label="Filtrar recibos">
            {FILTERS.map((f) => (
              <button
                key={f.id}
                type="button"
                role="tab"
                aria-selected={filter === f.id}
                className={`pagos-filter${filter === f.id ? " active" : ""}`}
                onClick={() => setFilter(f.id)}
              >
                {f.label}
              </button>
            ))}
          </div>

          <div className="pagos-list">
            {filtered.length === 0 ? (
              <p className="pagos-empty">No hay recibos para este filtro.</p>
            ) : (
              filtered.map((p) => (
                <button
                  key={p.id}
                  type="button"
                  className="pago-card"
                   onClick={() => { setPrintError(""); setReceipt(p); }}
                >
                  <span className={`pago-status-dot ${p.status}`} aria-hidden="true" />

                  <span className="pago-main">
                    <span className="pago-concept">
                      {CONCEPT_LABELS[p.concept] || p.concept}
                    </span>
                    <span className="pago-desc">{p.description}</span>
                    <span className="pago-meta">
                      {p.receipt_number}
                      {" · "}
                      {p.paid_at
                        ? `Pagado el ${formatDate(p.paid_at)}`
                        : `Vence ${formatDate(p.due_date) || "—"}`}
                    </span>
                  </span>

                  <span className="pago-side">
                    <span className="pago-amount">{formatMoney(p.amount)}</span>
                    <span className={`pago-badge ${p.status}`}>
                      {STATUS_LABELS[p.status] || p.status}
                    </span>
                    <span className="pago-see">Ver recibo</span>
                  </span>
                </button>
              ))
            )}
          </div>
        </>
      )}

      {receipt && (
        <div className="pago-modal-overlay" onClick={() => setReceipt(null)}>
          <div
            className="pago-modal"
            role="dialog"
            aria-modal="true"
            aria-label={`Recibo ${receipt.receipt_number}`}
            onClick={(e) => e.stopPropagation()}
          >
            <header className="pago-modal-header">
              <h3>Recibo de pago</h3>
              <button
                className="pago-modal-close"
                onClick={() => setReceipt(null)}
                aria-label="Cerrar recibo"
              >
                ✕
              </button>
            </header>

            <div className="pago-modal-body">
              <div className="pago-modal-brand">
                <strong>UNCPGGL</strong>
                <span>
                  Universidad Nacional Comandante Padre Gaspar García Laviana
                </span>
              </div>

              <dl className="pago-modal-fields">
                {fields.map(([label, value]) => (
                  <div key={label}>
                    <dt>{label}</dt>
                    <dd>{label === "Estado"
                      ? <span className={`pago-badge ${receipt.status}`}>{value}</span>
                      : value || "—"}</dd>
                  </div>
                ))}
              </dl>

              <div className="pago-modal-total">
                <span>Monto</span>
                <strong>{formatMoney(receipt.amount)}</strong>
              </div>
              <button type="button" className="pago-print-btn" onClick={printReceipt}>
                Imprimir o guardar como PDF
              </button>
              {printError && <p role="alert">{printError}</p>}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default PagosView;
