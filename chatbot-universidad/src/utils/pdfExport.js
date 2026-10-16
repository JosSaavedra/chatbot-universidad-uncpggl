import { dayShort, timeRange, scheduleMeta } from "./scheduleDisplay";

/**
 * Exportación del horario a PDF.
 *
 * Genera un documento A4 con el formato del horario y abre el diálogo de
 * impresión del navegador (el usuario elige "Guardar como PDF"). No requiere
 * dependencias externas y funciona también sin conexión.
 */

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

const DOCUMENT_CSS = `
  @page { size: A4; margin: 18mm 16mm; }
  * { box-sizing: border-box; }
  body {
    font-family: "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
    color: #1f2937;
    margin: 0;
  }
  .doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: 3px solid #1d4ed8;
    padding-bottom: 10px;
    margin-bottom: 18px;
  }
  .doc-title { font-size: 20px; font-weight: 700; margin: 0; color: #1d4ed8; }
  .doc-subtitle { font-size: 13px; margin: 4px 0 0; color: #374151; }
  .doc-date { font-size: 11px; color: #6b7280; text-align: right; }
  .section-title {
    font-size: 14px;
    font-weight: 700;
    margin: 18px 0 8px;
    color: #111827;
  }
  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
  th { background: #eff6ff; font-weight: 600; color: #1e3a8a; }
  tr:nth-child(even) td { background: #f9fafb; }
  .empty { font-size: 12px; color: #6b7280; font-style: italic; }
  .doc-footer {
    margin-top: 24px;
    padding-top: 8px;
    border-top: 1px solid #d1d5db;
    font-size: 10px;
    color: #9ca3af;
    display: flex;
    justify-content: space-between;
  }
`;

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

const buildDocument = (schedule, { title, subtitle, label }) => {
  const classes = schedule?.classes || [];
  const today = classes.filter((c) => c.is_today);
  const now = new Date().toLocaleString("es-GT", {
    dateStyle: "long",
    timeStyle: "short",
  });

  return `<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>${escapeHtml(title)}</title>
  <style>${DOCUMENT_CSS}</style>
</head>
<body>
  <header class="doc-header">
    <div>
      <h1 class="doc-title">${escapeHtml(title)}</h1>
      ${subtitle ? `<p class="doc-subtitle">${escapeHtml(subtitle)}</p>` : ""}
    </div>
    <div class="doc-date">Generado el<br />${escapeHtml(now)}</div>
  </header>

  ${
    today.length > 0
      ? `<h2 class="section-title">Clases de hoy</h2>${tableHtml(today, "")}`
      : ""
  }

  <h2 class="section-title">Horario semanal</h2>
  ${tableHtml(classes, "No hay clases registradas en este horario.")}

  <footer class="doc-footer">
    <span>${escapeHtml(label)}</span>
    <span>Asistente Universitario</span>
  </footer>
</body>
</html>`;
};

const printInPopup = (html, title) => {
  const win = window.open("", "_blank", "width=820,height=960");
  if (!win) {
    throw new Error("El navegador bloqueó la ventana de impresión.");
  }
  win.document.open();
  win.document.write(html);
  win.document.close();
  win.document.title = title;
  win.focus();
  // Se espera a que cargue para imprimir de forma confiable.
  win.onload = () => {
    win.print();
  };
};

/**
 * Genera el PDF del horario recibido.
 *
 * @param {object} schedule - Horario con `career`, `year`, `group` y `classes`.
 * @param {object} [options]
 * @param {"personal"|"general"} [options.mode] - Tipo de horario a exportar.
 */
export const exportSchedulePdf = (schedule, { mode = "general" } = {}) => {
  if (!schedule || !Array.isArray(schedule.classes)) {
    throw new Error("No hay datos de horario para exportar.");
  }

  const meta = scheduleMeta(schedule);
  const personal = mode === "personal";
  const title = personal ? "Horario de clases personalizado" : "Horario general de clases";
  const subtitle = meta || (personal ? "Horario del estudiante" : "");
  const label = personal
    ? `Estudiante${meta ? ` · ${meta}` : ""}`
    : meta || "Consulta general (demostración)";

  const html = buildDocument(schedule, { title, subtitle, label });
  printInPopup(html, title);
};
