import { useEffect, useMemo, useState } from "react";
import { fetchMyGrades } from "../../services/gradesService";
import { useAuth } from "../../hooks/useAuth";
import DemoBadge from "../chat/DemoBadge";
import {
  computePeriodSummary,
  countLabel,
  formatGrade,
  gradeStatusKey,
  gradeStatusLabel,
} from "../../utils/grades";
import { exportGradesPdf } from "../../utils/gradesPdfExport";
import "./GradesView.css";

const GRADE_FIELDS = [
  { key: "first_partial", label: "I parcial" },
  { key: "second_partial", label: "II parcial" },
  { key: "final_exam", label: "Examen final" },
  { key: "final_grade", label: "Definitiva" },
];

function GradesView({ onBack, onLogin }) {
  const { isAuthed, token } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [expired, setExpired] = useState(false);
  const [result, setResult] = useState(null);
  const [periodFilter, setPeriodFilter] = useState("all");
  const [exportError, setExportError] = useState(null);

  useEffect(() => {
    if (!isAuthed || !token) {
      setResult(null);
      setError(null);
      setExpired(false);
      return undefined;
    }

    let active = true;

    const load = async () => {
      setLoading(true);
      setError(null);
      setExpired(false);
      setResult(null);

      try {
        const data = await fetchMyGrades(token);
        if (active) {
          setResult(data);
          setPeriodFilter("all");
        }
      } catch (err) {
        if (active) {
          setError(err.message || "No se pudieron consultar tus notas.");
          setExpired(err.status === 401);
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

  const student = result?.student;
  const summary = result?.summary;
  const periods = useMemo(() => result?.periods || [], [result]);

  const shownPeriods = useMemo(
    () =>
      periodFilter === "all"
        ? periods
        : periods.filter((period) => period.period === periodFilter),
    [periods, periodFilter]
  );

  const initials = (student?.name || "?")
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join("")
    .toUpperCase();

  const hasGrades = periods.length > 0;
  const canExport =
    isAuthed && !loading && !expired && !error && Boolean(result) && hasGrades;

  // Mensaje claro cuando la exportación no está disponible.
  let exportHint = "";
  if (!isAuthed) {
    exportHint = "Inicia sesión con tu carné para exportar.";
  } else if (loading) {
    exportHint = "Cargando tus calificaciones…";
  } else if (expired) {
    exportHint = "Tu sesión expiró. Inicia sesión nuevamente para exportar.";
  } else if (error) {
    exportHint = "No se pudieron cargar tus notas. No es posible exportar.";
  } else if (!result || !hasGrades) {
    exportHint = "No hay calificaciones para exportar.";
  } else if (periodFilter === "all") {
    exportHint =
      periods.length > 1
        ? "Se exportarán todos los períodos."
        : `Se exportará el período ${periods[0].period}.`;
  } else {
    exportHint = `Se exportará el período ${periodFilter}.`;
  }

  const handleExportPdf = () => {
    setExportError(null);
    try {
      exportGradesPdf(result, { period: periodFilter });
    } catch (e) {
      setExportError(e.message || "No se pudo generar el PDF.");
    }
  };

  return (
    <div className="notas-view">
      <div className="notas-toolbar">
        {onBack && (
          <button className="notas-back-btn" onClick={onBack}>
            ← Volver al asistente
          </button>
        )}
      </div>

      <section className="notas-hero">
        <h2 className="notas-hero-title">Calificaciones del estudiante</h2>
        <p className="notas-hero-text">
          Consulta tus asignaturas, notas parciales, examen final y estado
          académico por período. Por seguridad, esta información requiere que
          inicies sesión con tu carné.
        </p>
        <div className="notas-hero-badge">
          <DemoBadge />
        </div>

        {!isAuthed ? (
          <button
            className="notas-search-btn"
            type="button"
            onClick={() => onLogin?.()}
          >
            🎓 Iniciar sesión con carné
          </button>
        ) : null}

        {isAuthed && loading ? (
          <p className="notas-hero-text">Cargando tus calificaciones…</p>
        ) : null}

        {expired ? (
          <div className="notas-error">
            Tu sesión expiró o no es válida. Vuelve a iniciar sesión para ver tus
            calificaciones.
          </div>
        ) : error ? (
          <div className="notas-error">{error}</div>
        ) : null}
      </section>

      {isAuthed && (
        <section className="notas-actions">
          <button
            type="button"
            className="notas-export-btn"
            onClick={handleExportPdf}
            disabled={!canExport}
            title={exportHint}
          >
            ⬇️ Exportar PDF
          </button>
          <span className="notas-export-hint">{exportHint}</span>
        </section>
      )}

      {exportError ? (
        <div className="notas-error" role="alert">
          {exportError}
        </div>
      ) : null}

      {isAuthed && result && (
        <>
          <section className="notas-student">
            <div className="notas-student-avatar" aria-hidden="true">
              {initials}
            </div>
            <div className="notas-student-info">
              <h3>{student?.name || "Estudiante"}</h3>
              <p>{student?.career || "Carrera no registrada"}</p>
              <div className="notas-student-meta">
                <span className="notas-chip">Carné {student?.carnet}</span>
                {student?.year_of_study ? (
                  <span className="notas-chip">
                    {student.year_of_study}º año
                  </span>
                ) : null}
                {student?.faculty ? (
                  <span className="notas-chip">{student.faculty}</span>
                ) : null}
              </div>
            </div>
          </section>

          <section className="notas-summary">
            <div className="notas-summary-card approved">
              <span className="notas-summary-label">Aprobadas</span>
              <strong className="notas-summary-value">
                {summary?.approved ?? 0}
              </strong>
              <span className="notas-summary-sub">
                {countLabel(summary?.total_subjects ?? 0, "asignatura", "asignaturas")} en total
              </span>
            </div>
            <div className="notas-summary-card failed">
              <span className="notas-summary-label">Reprobadas</span>
              <strong className="notas-summary-value">
                {summary?.failed ?? 0}
              </strong>
              <span className="notas-summary-sub">
                Promedio{" "}
                {summary?.average === null || summary?.average === undefined
                  ? "—"
                  : formatGrade(summary.average)}
              </span>
            </div>
            <div className="notas-summary-card pending">
              <span className="notas-summary-label">Pendientes</span>
              <strong className="notas-summary-value">
                {summary?.pending ?? 0}
              </strong>
              <span className="notas-summary-sub">Aún sin nota definitiva</span>
            </div>
          </section>

          {periods.length === 0 ? (
            <p className="notas-empty">
              Aún no tienes calificaciones registradas en el sistema.
            </p>
          ) : (
            <>
              {periods.length > 1 && (
                <div className="notas-filters" role="tablist" aria-label="Filtrar por período">
                  <button
                    type="button"
                    className={`notas-filter${periodFilter === "all" ? " active" : ""}`}
                    onClick={() => setPeriodFilter("all")}
                  >
                    Todos
                  </button>
                  {periods.map((period) => (
                    <button
                      key={period.period}
                      type="button"
                      className={`notas-filter${periodFilter === period.period ? " active" : ""}`}
                      onClick={() => setPeriodFilter(period.period)}
                    >
                      {period.period}
                    </button>
                  ))}
                </div>
              )}

              {shownPeriods.map((period) => {
                const periodSummary = period.summary || computePeriodSummary(period.subjects);
                return (
                  <section className="notas-period" key={period.period}>
                    <header className="notas-period-head">
                      <h3 className="notas-period-title">{period.period}</h3>
                      <div className="notas-period-meta">
                        <span className="notas-chip ok">
                          {countLabel(periodSummary.approved, "aprobada", "aprobadas")}
                        </span>
                        {periodSummary.failed > 0 ? (
                          <span className="notas-chip bad">
                            {countLabel(periodSummary.failed, "reprobada", "reprobadas")}
                          </span>
                        ) : null}
                        {periodSummary.pending > 0 ? (
                          <span className="notas-chip warn">
                            {countLabel(periodSummary.pending, "pendiente", "pendientes")}
                          </span>
                        ) : null}
                      </div>
                    </header>

                    <div className="notas-list">
                      {(period.subjects || []).map((subject) => {
                        const status = gradeStatusKey(subject.status);
                        return (
                          <article className="nota-card" key={subject.id}>
                            <div className="nota-card-head">
                              <span className="nota-subject">
                                {subject.subject_name}
                              </span>
                              <span className={`nota-badge ${status}`}>
                                {gradeStatusLabel(subject.status)}
                              </span>
                            </div>
                            <div className="nota-stats">
                              {GRADE_FIELDS.map((field) => (
                                <div className="nota-stat" key={field.key}>
                                  <span className="nota-stat-label">
                                    {field.label}
                                  </span>
                                  <span
                                    className={`nota-stat-value${
                                      field.key === "final_grade" ? " strong" : ""
                                    }`}
                                  >
                                    {formatGrade(subject[field.key])}
                                  </span>
                                </div>
                              ))}
                            </div>
                          </article>
                        );
                      })}
                    </div>
                  </section>
                );
              })}
            </>
          )}
        </>
      )}
    </div>
  );
}

export default GradesView;
