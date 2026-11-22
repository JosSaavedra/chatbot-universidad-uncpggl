import { test } from "node:test";
import assert from "node:assert/strict";
import { selectionFromSchedule } from "../src/utils/scheduleDisplay.js";

test("el horario personal no deja una selección reutilizable por el invitado", () => {
  const schedule = { career_id: 7, year: 3, group: "B" };
  assert.equal(selectionFromSchedule({ ...schedule, personalized: true }), null);
  assert.deepEqual(
    selectionFromSchedule({ ...schedule, personalized: false }),
    { careerId: 7, year: 3, group: "B" },
  );
});
