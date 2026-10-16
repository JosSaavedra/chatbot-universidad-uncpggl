/**
 * Utilidades de presentación de calificaciones.
 *
 * Se mantienen como funciones puras (JS plano) para poder probarlas con
 * `npm test` y reutilizarlas en la vista del módulo "Mis notas".
 */

export const GRADE_STATUS_LABELS = {
  approved: "Aprobado",
  failed: "Reprobado",
  pending: "Pendiente",
};

/**
 * Normaliza un estado académico a una de las tres claves conocidas. Cualquier
 * valor distinto (o ausente) se considera "pendiente".
 */
export const gradeStatusKey = (status) => {
  if (status === "approved" || status === "failed") return status;
  return "pending";
};

export const gradeStatusLabel = (status) =>
  GRADE_STATUS_LABELS[gradeStatusKey(status)];

/**
 * Formatea una nota. Los valores vacíos se muestran como "—" (sin datos).
 */
export const formatGrade = (value) => {
  if (value === null || value === undefined || value === "") return "—";
  const number = Number(value);
  if (!Number.isFinite(number)) return "—";
  return number.toLocaleString("es-NI", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

/**
 * Devuelve "N singular" o "N plural" según la cantidad, para respetar la
 * concordancia en el resumen ("1 reprobada", "2 aprobadas", ...).
 */
export const countLabel = (count, singular, plural) => {
  const total = Number(count) || 0;
  return `${total} ${total === 1 ? singular : plural}`;
};

/**
 * Cuenta aprobadas, reprobadas y pendientes de una lista de asignaturas.
 */
export const computePeriodSummary = (subjects = []) => {
  const approved = subjects.filter(
    (subject) => gradeStatusKey(subject.status) === "approved"
  ).length;
  const failed = subjects.filter(
    (subject) => gradeStatusKey(subject.status) === "failed"
  ).length;

  return {
    count: subjects.length,
    approved,
    failed,
    pending: subjects.length - approved - failed,
  };
};
