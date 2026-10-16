/**
 * Utilidades de texto para las respuestas del asistente.
 *
 * La IA (Groq) debe responder en texto plano/Markdown, pero en ocasiones puede
 * colar etiquetas HTML o JSX (p. ej. "<button>Ver información de pagos</button>").
 * React ya las escaparía como texto visible: aquí se eliminan de forma
 * defensiva CONSERVANDO el texto interno, sin tocar el Markdown seguro
 * (listas, negritas, cifras, nombres de carreras ni enlaces legítimos).
 */

const HTML_TAG_RE = /<\/?[a-zA-Z][^>]*>/g;

export function stripHtmlTags(text) {
  if (typeof text !== "string" || text.length === 0) return "";

  const out = text
    // Etiquetas de salto/bloque pasan a salto de línea para no perder estructura.
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<\/(p|div|li|ul|ol|h[1-6]|tr)>/gi, "\n")
    // Resto de etiquetas: se quitan, se conserva el texto interno.
    .replace(HTML_TAG_RE, "")
    // Colapsa líneas en blanco repetidas y espacios finales.
    .replace(/[ \t]+\n/g, "\n")
    .replace(/\n{3,}/g, "\n\n");

  return out.trim();
}
