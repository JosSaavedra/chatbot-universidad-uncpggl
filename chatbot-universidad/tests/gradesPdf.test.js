import test from "node:test";
import assert from "node:assert/strict";
import {
  buildGradesDocumentHtml,
  exportGradesPdf,
  DEMO_NOTE,
  UNIVERSITY_NAME,
  REPORT_TITLE,
} from "../src/utils/gradesPdfExport.js";
import { buildScheduleDocumentHtml } from "../src/utils/pdfExport.js";

const sample = {
  student: {
    name: "Gabriela Castillo",
    carnet: "2024-CON-001",
    career: "Contabilidad Pública y Finanzas",
    year_of_study: 2,
  },
  periods: [
    {
      period: "2025 · I",
      subjects: [
        {
          id: 1,
          subject_name: "Contabilidad II",
          first_partial: 88,
          second_partial: 90,
          final_exam: 85,
          final_grade: 88,
          status: "approved",
        },
        {
          id: 2,
          subject_name: "Derecho Mercantil",
          first_partial: 55,
          second_partial: 58,
          final_exam: 52,
          final_grade: 55,
          status: "failed",
        },
      ],
      summary: { approved: 1, failed: 1, pending: 0 },
    },
    {
      period: "2025 · II",
      subjects: [
        {
          id: 3,
          subject_name: "Finanzas I",
          first_partial: null,
          second_partial: null,
          final_exam: null,
          final_grade: null,
          status: "pending",
        },
      ],
      summary: { approved: 0, failed: 0, pending: 1 },
    },
  ],
};

test("incluye universidad, título, estudiante, carné y carrera", () => {
  const html = buildGradesDocumentHtml(sample);
  assert.ok(html.includes(UNIVERSITY_NAME));
  assert.ok(html.includes(REPORT_TITLE));
  assert.ok(html.includes("Gabriela Castillo"));
  assert.ok(html.includes("2024-CON-001"));
  assert.ok(html.includes("Contabilidad Pública y Finanzas"));
  assert.ok(html.includes("Fecha de generación"));
  assert.ok(html.includes("2025 · I"));
});

test("incluye asignaturas, notas y estados con todas las columnas", () => {
  const html = buildGradesDocumentHtml(sample);
  assert.ok(html.includes("Contabilidad II"));
  assert.ok(html.includes("Derecho Mercantil"));
  assert.ok(html.includes("Finanzas I"));
  assert.ok(html.includes("Aprobado"));
  assert.ok(html.includes("Reprobado"));
  assert.ok(html.includes("Pendiente"));
  for (const column of [
    "Asignatura",
    "I parcial",
    "II parcial",
    "Examen final",
    "Nota definitiva",
    "Estado",
  ]) {
    assert.ok(html.includes(column), `falta la columna ${column}`);
  }
});

test("incluye la nota de documento de demostración", () => {
  assert.ok(buildGradesDocumentHtml(sample).includes(DEMO_NOTE));
});

test("el resumen respeta la concordancia singular/plural", () => {
  const html = buildGradesDocumentHtml(sample);
  // Período con 1 aprobada y 1 reprobada -> singular.
  assert.ok(html.includes("1 aprobada · 1 reprobada · 0 pendientes"));
  // Período con 1 pendiente -> singular.
  assert.ok(html.includes("0 aprobadas · 0 reprobadas · 1 pendiente"));
  assert.equal(html.includes("1 aprobadas"), false);
  assert.equal(html.includes("1 reprobadas"), false);
  assert.equal(html.includes("1 pendientes"), false);
});

test("permite exportar un solo período", () => {
  const html = buildGradesDocumentHtml(sample, { period: "2025 · II" });
  assert.ok(html.includes("Finanzas I"));
  assert.equal(html.includes("Contabilidad II"), false);
  assert.equal(html.includes("Derecho Mercantil"), false);
});

test("un período sin coincidencias muestra el aviso de vacío", () => {
  const html = buildGradesDocumentHtml(sample, { period: "2030 · X" });
  assert.ok(html.includes("No hay calificaciones registradas para exportar."));
});

test("escapa el HTML de los datos (sin inyección)", () => {
  const html = buildGradesDocumentHtml({
    student: { name: "<script>alert(1)</script>", carnet: "A&B", career: "X" },
    periods: [
      {
        period: "2025 · I",
        subjects: [
          {
            id: 1,
            subject_name: '<img src=x onerror="alert(1)">',
            status: "approved",
          },
        ],
      },
    ],
  });

  assert.equal(html.includes("<script>alert(1)</script>"), false);
  assert.equal(html.includes("<img src=x"), false);
  assert.ok(html.includes("&lt;script&gt;alert(1)&lt;/script&gt;"));
  assert.ok(html.includes("A&amp;B"));
});

test("no permite exportar cuando no hay calificaciones", () => {
  assert.throws(() => exportGradesPdf({ periods: [] }), /No hay calificaciones/);
  assert.throws(
    () => exportGradesPdf({ periods: [{ period: "2025 · I", subjects: [] }] }),
    /No hay calificaciones para exportar/
  );
});

test("el horario sigue generándose igual (sin regresión)", () => {
  const schedule = {
    career: "Contabilidad Pública y Finanzas",
    year: 1,
    group: "A",
    classes: [
      {
        id: 1,
        day: "Lunes",
        start_time: "08:00",
        end_time: "10:00",
        subject: "Matemática",
        classroom: "Aula 1",
        building: "Edificio A",
        teacher: "MSc. Pérez",
      },
    ],
  };

  const general = buildScheduleDocumentHtml(schedule, { mode: "general" });
  assert.ok(general.includes("Horario general de clases"));
  assert.ok(general.includes("Horario semanal"));
  assert.ok(general.includes("Matemática"));
  assert.ok(general.includes("Aula 1"));

  const personal = buildScheduleDocumentHtml(schedule, { mode: "personal" });
  assert.ok(personal.includes("Horario de clases personalizado"));
});

test("el horario sin datos lanza un error claro", () => {
  assert.throws(() => buildScheduleDocumentHtml(null), /No hay datos de horario/);
});
