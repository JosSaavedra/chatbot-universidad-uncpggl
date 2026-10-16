import { test } from "node:test";
import assert from "node:assert/strict";
import {
  computePeriodSummary,
  countLabel,
  formatGrade,
  gradeStatusKey,
  gradeStatusLabel,
} from "../src/utils/grades.js";

test("gradeStatusKey normaliza los estados conocidos", () => {
  assert.equal(gradeStatusKey("approved"), "approved");
  assert.equal(gradeStatusKey("failed"), "failed");
  assert.equal(gradeStatusKey("pending"), "pending");
});

test("gradeStatusKey trata estados desconocidos como pendiente", () => {
  assert.equal(gradeStatusKey(undefined), "pending");
  assert.equal(gradeStatusKey(null), "pending");
  assert.equal(gradeStatusKey("otro"), "pending");
});

test("gradeStatusLabel devuelve la etiqueta en español", () => {
  assert.equal(gradeStatusLabel("approved"), "Aprobado");
  assert.equal(gradeStatusLabel("failed"), "Reprobado");
  assert.equal(gradeStatusLabel("pending"), "Pendiente");
});

test("formatGrade muestra '—' cuando no hay nota", () => {
  assert.equal(formatGrade(null), "—");
  assert.equal(formatGrade(undefined), "—");
  assert.equal(formatGrade(""), "—");
  assert.equal(formatGrade("no-numero"), "—");
});

test("formatGrade conserva los valores numéricos", () => {
  // El separador decimal depende del ICU del entorno (coma en el navegador),
  // así que la prueba acepta ambos sin dejar de comprobar el valor.
  assert.match(formatGrade(0), /^0[.,]00$/);
  assert.match(formatGrade(85.5), /^85[.,]50$/);
});

test("computePeriodSummary cuenta aprobadas, reprobadas y pendientes", () => {
  const subjects = [
    { status: "approved" },
    { status: "approved" },
    { status: "failed" },
    { status: "pending" },
    { status: null },
  ];

  assert.deepEqual(computePeriodSummary(subjects), {
    count: 5,
    approved: 2,
    failed: 1,
    pending: 2,
  });
});

test("computePeriodSummary de una lista vacía es todo cero", () => {
  assert.deepEqual(computePeriodSummary(), {
    count: 0,
    approved: 0,
    failed: 0,
    pending: 0,
  });
});

test("countLabel respeta la concordancia singular/plural", () => {
  assert.equal(countLabel(0, "reprobada", "reprobadas"), "0 reprobadas");
  assert.equal(countLabel(1, "reprobada", "reprobadas"), "1 reprobada");
  assert.equal(countLabel(2, "aprobada", "aprobadas"), "2 aprobadas");
  assert.equal(countLabel(1, "asignatura", "asignaturas"), "1 asignatura");
  assert.equal(countLabel(3, "pendiente", "pendientes"), "3 pendientes");
});
