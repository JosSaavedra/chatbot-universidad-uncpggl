import { dayShort, timeRange, scheduleMeta } from "./scheduleDisplay.js";
import { escapeHtml, renderDocument, printInPopup } from "./pdfDocument.js";

/**
 * Exportación del horario a PDF.
 *
 * Genera un documento A4 con el formato del horario y abre el diálogo de
 * impresión del navegador (el usuario elige "Guardar como PDF"). No requiere
 * dependencias externas y funciona también sin conexión.
 */

const rowHtml = (c) => `
  <tr>
    <td>${escapeHtml(dayShort(c.day))}</td>
    <td>${escapeHtml(timeRange(c))}</td>
    <td>${escapeHtml(c.subject)}</td>
    <td>${escapeHtml(c.classroom || "—")}</td>
    <td>${escapeHtml(c.building || "—")}</td>
    <td>${escapeHtml(c.teacher || "—")}</td>
  </tr>`;

const tableHtml = (classes, emptyMessage) => {
  if (!classes || classes.length === 0) {
    return `<p class="empty">${escapeHtml(emptyMessage)}</p>`;
  }
  const sorted = [...classes].sort((a, b) => {
    const dayOrder = (d) =>
      ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"].indexOf(d);
    const diff = dayOrder(a.day) - dayOrder(b.day);
    if (diff !== 0) return diff;
    return String(a.start_time).localeCompare(String(b.start_time));
  });
  return `
    <table>
      <thead>
        <tr>
          <th>Día</th>
          <th>Horario</th>
          <th>Asignatura</th>
          <th>Aula</th>
          <th>Edificio</th>
          <th>Docente</th>
        </tr>
      </thead>
      <tbody>${sorted.map(rowHtml).join("")}</tbody>
    </table>`;
};

const titleFor = (personal) =>
  personal ? "Horario de clases personalizado" : "Horario general de clases";

/**
 * Construye el HTML del documento del horario (función pura, testeable).
 *
 * @param {object} schedule - Horario con `career`, `year`, `group` y `classes`.
 * @param {object} [options]
 * @param {"personal"|"general"} [options.mode] - Tipo de horario a exportar.
 */
export const buildScheduleDocumentHtml = (schedule, { mode = "general" } = {}) => {
  if (!schedule || !Array.isArray(schedule.classes)) {
    throw new Error("No hay datos de horario para exportar.");
  }

  const classes = schedule.classes;
  const today = classes.filter((c) => c.is_today);
  const meta = scheduleMeta(schedule);
  const personal = mode === "personal";
  const title = titleFor(personal);
  const subtitle = meta || (personal ? "Horario del estudiante" : "");
  const label = personal
    ? `Estudiante${meta ? ` · ${meta}` : ""}`
    : meta || "Consulta general (demostración)";

  const body = `
  ${
    today.length > 0
      ? `<h2 class="section-title">Clases de hoy</h2>${tableHtml(today, "")}`
      : ""
  }

  <h2 class="section-title">Horario semanal</h2>
  ${tableHtml(classes, "No hay clases registradas en este horario.")}`;

  return renderDocument({ title, subtitle, body, footerLeft: label });
};

/**
 * Genera el PDF del horario recibido.
 *
 * @param {object} schedule - Horario con `career`, `year`, `group` y `classes`.
 * @param {object} [options]
 * @param {"personal"|"general"} [options.mode] - Tipo de horario a exportar.
 */
export const exportSchedulePdf = (schedule, { mode = "general" } = {}) => {
  const html = buildScheduleDocumentHtml(schedule, { mode });
  printInPopup(html, titleFor(mode === "personal"));
};
