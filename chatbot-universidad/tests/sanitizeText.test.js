import { test } from "node:test";
import assert from "node:assert/strict";
import { stripHtmlTags } from "../src/utils/sanitizeText.js";

test("elimina etiquetas HTML/JSX conservando el texto interno", () => {
  assert.equal(
    stripHtmlTags("<button>Ver información de pagos</button>"),
    "Ver información de pagos"
  );
  assert.equal(stripHtmlTags("<div><span>Hola</span></div>"), "Hola");
});

test("no deja ninguna etiqueta visible", () => {
  const out = stripHtmlTags("<p>Hola <strong>mundo</strong></p>");
  assert.equal(out.includes("<"), false);
  assert.equal(out.includes(">"), false);
  assert.equal(out, "Hola mundo");
});

test("convierte <br> y etiquetas de bloque en saltos de línea", () => {
  assert.equal(stripHtmlTags("Línea 1<br>Línea 2"), "Línea 1\nLínea 2");
  assert.equal(stripHtmlTags("<p>Uno</p><p>Dos</p>"), "Uno\nDos");
});

test("preserva Markdown seguro (listas, negritas, cifras y nombres)", () => {
  const md =
    "La carrera de Contabilidad Pública y Finanzas tiene los siguientes costos:\n\n" +
    "- Inscripción: C$600.00\n" +
    "- Mensualidad: C$340.00\n" +
    "- Duración: 4 años";

  assert.equal(stripHtmlTags(md), md);
  assert.equal(stripHtmlTags("**Inscripción:** C$600.00"), "**Inscripción:** C$600.00");
});

test("no altera texto sin etiquetas ni comparaciones matemáticas", () => {
  assert.equal(stripHtmlTags("Si el promedio es < 70 reprueba"), "Si el promedio es < 70 reprueba");
  assert.equal(stripHtmlTags("¿En qué puedo ayudarte hoy? 👋"), "¿En qué puedo ayudarte hoy? 👋");
});

test("devuelve cadena vacía para entradas no válidas", () => {
  assert.equal(stripHtmlTags(null), "");
  assert.equal(stripHtmlTags(undefined), "");
  assert.equal(stripHtmlTags(42), "");
  assert.equal(stripHtmlTags(""), "");
});
