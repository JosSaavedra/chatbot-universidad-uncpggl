import {
  escapeHtml,
  renderDocument,
  printInPopup,
  formatGeneratedAt,
} from "./pdfDocument.js";
import {
  computePeriodSummary,
  countLabel,
  formatGrade,
  gradeStatusKey,
  gradeStatusLabel,
} from "./grades.js";

/**
 * Exportación de calificaciones a PDF.
 *
 * Reutiliza el sistema de impresión A4 (ver `pdfDocument.js`). El documento se
 * genera EXCLUSIVAMENTE con los datos autorizados devueltos por
 * `/api/my-grades`: no se usa ningún carné ni `user_id` introducido por el
 * usuario, por lo que no es posible exportar notas de otro estudiante.
 *
 * No se envía información a Groq ni a servicios externos de generación de PDF.
 */

export const UNIVERSITY_NAME =
  "Universidad Nacional Comandante Padre Gaspar García Laviana (UNCPGGL)";

export const REPORT_TITLE = "Reporte de calificaciones";

export const DEMO_NOTE =
  "Documento de demostración. No constituye una certificación académica oficial.";

/** Estilos propios del reporte de calificaciones. */
const GRADES_CSS = `
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .doc-university {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6b7280;
    margin: 0 0 4px;
  }
  .doc-info {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 4px 18px;
    margin: 0;
  }
  .doc-info-item { padding: 3px 0; }
  .doc-info-label {
    display: block;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
  }
  .doc-info-value { font-size: 12px; font-weight: 600; color: #111827; }
  .doc-summary { font-size: 11px; color: #6b7280; margin: 0 0 8px; }
  .num { text-align: center; }
  .doc-status { font-weight: 700; }
  .doc-status.approved { color: #15803d; }
  .doc-status.failed { color: #b91c1c; }
  .doc-status.pending { color: #b45309; }
  .doc-notice {
    margin-top: 16px;
    border: 1px solid #f59e0b;
    background: #fffbeb;
    color: #92400e;
    font-size: 11px;
    padding: 8px 10px;
    border-radius: 6px;
  }
`;

const infoItem = (label, value) => `
      <div class="doc-info-item">
        <span class="doc-info-label">${escapeHtml(label)}</span>
        <span class="doc-info-value">${escapeHtml(value)}</span>
      </div>`;

const gradeCell = (value) =>
  `<td class="num">${escapeHtml(formatGrade(value))}</td>`;

const statusCell = (status) => {
  const key = gradeStatusKey(status);
  return `<td class="num"><span class="doc-status ${key}">${escapeHtml(
    gradeStatusLabel(status)
  )}</span></td>`;
};

const subjectsTable = (subjects) => {
  const rows = (subjects || [])
    .map(
      (s) => `
        <tr>
          <td>${escapeHtml(s.subject_name)}</td>
          ${gradeCell(s.first_partial)}
          ${gradeCell(s.second_partial)}
          ${gradeCell(s.final_exam)}
          ${gradeCell(s.final_grade)}
          ${statusCell(s.status)}
        </tr>`
    )
    .join("");

  return `
    <table>
      <thead>
        <tr>
          <th>Asignatura</th>
          <th class="num">I parcial</th>
          <th class="num">II parcial</th>
          <th class="num">Examen final</th>
          <th class="num">Nota definitiva</th>
          <th class="num">Estado</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>`;
};

/**
 * Construye el HTML del reporte de calificaciones (función pura, testeable).
 *
 * @param {object} grades - Datos de `/api/my-grades` (`student`, `periods`).
 * @param {object} [options]
 * @param {string} [options.period] - Período a exportar ("all" = todos).
 */
export const buildGradesDocumentHtml = (grades, { period = "all" } = {}) => {
  const student = grades?.student;
  const allPeriods = Array.isArray(grades?.periods) ? grades.periods : [];
  const periods =
    period === "all"
      ? allPeriods
      : allPeriods.filter((p) => p.period === period);

  const periodLabel =
    period !== "all"
      ? period
      : allPeriods.length === 1
        ? allPeriods[0].period
        : "Todos los períodos";

  const sections = periods
    .map((p) => {
      const subjects = p.subjects || [];
      const summary = p.summary || computePeriodSummary(subjects);
      const summaryLine = `${countLabel(summary.approved, "aprobada", "aprobadas")} · ${countLabel(summary.failed, "reprobada", "reprobadas")} · ${countLabel(summary.pending, "pendiente", "pendientes")}`;
      return `
  <h2 class="section-title">Período ${escapeHtml(p.period)}</h2>
  <p class="doc-summary">${escapeHtml(summaryLine)}</p>
  ${subjectsTable(subjects)}`;
    })
    .join("");

  const body = `
  <h2 class="section-title">Datos del estudiante</h2>
  <div class="doc-info">
    ${infoItem("Estudiante", student?.name || "—")}
    ${infoItem("Carné", student?.carnet || "—")}
    ${infoItem("Carrera", student?.career || "—")}
    ${infoItem("Período académico", periodLabel)}
    ${infoItem(
      "Año académico",
      student?.year_of_study ? `${student.year_of_study}º año` : "—"
    )}
    ${infoItem("Fecha de generación", formatGeneratedAt())}
  </div>
  ${
    sections ||
    `<p class="empty">No hay calificaciones registradas para exportar.</p>`
  }
  <p class="doc-notice">${escapeHtml(DEMO_NOTE)}</p>`;

  return renderDocument({
    eyebrow: UNIVERSITY_NAME,
    title: REPORT_TITLE,
    subtitle: student?.name ? `Estudiante: ${student.name}` : "",
    body,
    footerLeft: `Carné ${student?.carnet || "—"} · ${periodLabel}`,
    extraCss: GRADES_CSS,
  });
};

/**
 * Genera y abre el PDF de calificaciones.
 *
 * @param {object} grades - Datos de `/api/my-grades`.
 * @param {object} [options]
 * @param {string} [options.period] - Período a exportar ("all" = todos).
 */
export const exportGradesPdf = (grades, { period = "all" } = {}) => {
  const allPeriods = Array.isArray(grades?.periods) ? grades.periods : [];
  const selected =
    period === "all" ? allPeriods : allPeriods.filter((p) => p.period === period);
  const total = selected.reduce((n, p) => n + (p.subjects?.length || 0), 0);

  if (total === 0) {
    throw new Error("No hay calificaciones para exportar.");
  }

  const html = buildGradesDocumentHtml(grades, { period });
  printInPopup(html, REPORT_TITLE);
};
