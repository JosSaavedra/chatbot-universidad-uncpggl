/**
 * Utilidades compartidas para generar documentos imprimibles en A4 y abrir el
 * diálogo del navegador ("Guardar como PDF").
 *
 * Todo el proceso ocurre en el navegador: no se envían datos a servicios
 * externos de generación de PDF. El HTML resultante se construye con valores
 * escapados para evitar inyección.
 */

/** Escapa un valor para insertarlo con seguridad en el HTML del documento. */
export const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

/** Hoja de estilos base del documento imprimible (formato A4). */
export const DOCUMENT_CSS = `
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

/** Fecha/hora legible de generación del documento. */
export const formatGeneratedAt = () =>
  new Date().toLocaleString("es-GT", {
    dateStyle: "long",
    timeStyle: "short",
  });

/**
 * Envuelve el cuerpo del documento en la plantilla HTML común.
 *
 * @param {object} options
 * @param {string} options.title - Título visible.
 * @param {string} [options.subtitle] - Línea secundaria bajo el título.
 * @param {string} [options.body] - HTML del cuerpo (ya construido y escapado).
 * @param {string} [options.footerLeft] - Texto del pie (izquierda).
 * @param {string} [options.footerRight] - Texto del pie (derecha).
 */
export const renderDocument = ({
  eyebrow = "",
  title,
  subtitle = "",
  body = "",
  footerLeft = "",
  footerRight = "Asistente Universitario",
  extraCss = "",
}) => {
  const now = new Date().toLocaleString("es-GT", {
    dateStyle: "long",
    timeStyle: "short",
  });

  return `<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>${escapeHtml(title)}</title>
  <style>${DOCUMENT_CSS}${extraCss}</style>
</head>
<body>
  <header class="doc-header">
    <div>
      ${eyebrow ? `<p class="doc-university">${escapeHtml(eyebrow)}</p>` : ""}
      <h1 class="doc-title">${escapeHtml(title)}</h1>
      ${subtitle ? `<p class="doc-subtitle">${escapeHtml(subtitle)}</p>` : ""}
    </div>
    <div class="doc-date">Generado el<br />${escapeHtml(now)}</div>
  </header>

  ${body}

  <footer class="doc-footer">
    <span>${escapeHtml(footerLeft)}</span>
    <span>${escapeHtml(footerRight)}</span>
  </footer>
</body>
</html>`;
};

/**
 * Abre una ventana con el documento y lanza el diálogo de impresión
 * ("Guardar como PDF"). Lanza un error si el navegador bloquea la ventana.
 */
export const printInPopup = (html, title) => {
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
