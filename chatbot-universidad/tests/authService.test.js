import { test, afterEach } from "node:test";
import assert from "node:assert/strict";
import { printInPopup } from "../src/utils/pdfDocument.js";
import { acceptSession, getAuthSnapshot, loginByCarnet, logout, sessionScope } from "../src/services/authService.js";

const originalFetch = globalThis.fetch;
const originalStorage = globalThis.localStorage;
let calls = [];

function fakeFetch(payload, status = 200) {
  return async (url, options = {}) => {
    calls.push({ url, options });
    return {
      ok: status >= 200 && status < 300,
      status,
      json: async () => payload,
    };
  };
}

function fakeStorage() {
  const values = new Map();
  return {
    values,
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, String(value)),
    removeItem: (key) => values.delete(key),
    clear: () => values.clear(),
  };
}

afterEach(() => {
  globalThis.fetch = originalFetch;
  if (originalStorage === undefined) delete globalThis.localStorage;
  else globalThis.localStorage = originalStorage;
  calls = [];
});

test("el acceso local siempre envía carné y contraseña", async () => {
  globalThis.fetch = fakeFetch({ errors: { carnet: ["Las credenciales no son correctas."] } }, 422);

  await assert.rejects(
    loginByCarnet("2024-CON-001", "ClaveIndividual#2026"),
    /Las credenciales no son correctas\./,
  );

  assert.equal(calls.length, 1);
  assert.match(calls[0].url, /\/login$/);
  assert.doesNotMatch(calls[0].url, /login\/carnet/);
  assert.deepEqual(JSON.parse(calls[0].options.body), {
    carnet: "2024-CON-001",
    password: "ClaveIndividual#2026",
  });
});

test("el acceso institucional envía correo y contraseña", async () => {
  globalThis.fetch = fakeFetch({ errors: { carnet: ["Las credenciales no son correctas."] } }, 422);

  await assert.rejects(loginByCarnet("estudiante01@example.com", "ClaveIndividual#2026"));

  assert.deepEqual(JSON.parse(calls[0].options.body), {
    email: "estudiante01@example.com",
    password: "ClaveIndividual#2026",
  });
});

test("no intenta autenticarse si falta la contraseña", async () => {
  globalThis.fetch = fakeFetch({}, 200);

  await assert.rejects(loginByCarnet("2024-CON-001"), /contraseña/i);
  assert.equal(calls.length, 0);
});

test("presenta un mensaje específico cuando el servidor limita los intentos", async () => {
  globalThis.fetch = fakeFetch({ message: "Too Many Attempts." }, 429);

  await assert.rejects(
    loginByCarnet("2024-CON-001", "ClaveIndividual#2026"),
    /Demasiados intentos de acceso/,
  );
});

test("la contraseña no se guarda en el almacenamiento del navegador", async () => {
  const storage = fakeStorage();
  globalThis.localStorage = storage;
  globalThis.fetch = fakeFetch({
    token: "1|token-ficticio",
    student: { carnet: "2024-CON-001" },
    idle_minutes: 30,
    expires_at: "2030-01-01T00:00:00.000Z",
  });

  const password = "ClaveIndividual#2026";
  await loginByCarnet("2024-CON-001", password);

  for (const value of storage.values.values()) {
    assert.doesNotMatch(String(value), new RegExp(password));
  }

  await logout();
});

test("salir cierra las ventanas de impresión de la sesión", async () => {
  globalThis.fetch = async () => ({ ok: true, status: 200 });
  const popup = {
    closed: false,
    document: { open() {}, write() {}, close() {} },
    focus() {},
    close() { this.closed = true; },
  };
  const auth = await import("../src/services/authService.js");
  auth.acceptSession({ token: "774|ficticio", expires_at: new Date(Date.now() + 60_000).toISOString() });
  printInPopup("<main>Recibo ficticio</main>", "Recibo", popup);
  assert.equal(popup.closed, false);
  await auth.logout();
  assert.equal(popup.closed, true);
});

test("recargar cierra la impresión privada antes de perder su referencia", async () => {
  const previousWindow = globalThis.window;
  const lifecycle = new EventTarget();
  const storage = fakeStorage();
  globalThis.window = lifecycle;
  globalThis.localStorage = storage;
  globalThis.fetch = fakeFetch({});
  try {
    const { printInPopup: beforeReload } = await import("../src/utils/pdfDocument.js?before-reload");
    acceptSession({ token: "775|ficticio", expires_at: new Date(Date.now() + 60_000).toISOString() });
    const popup = {
      closed: false,
      document: { open() {}, write() {}, close() {} },
      focus() {},
      close() { this.closed = true; },
    };
    const keys = [...storage.values];
    beforeReload("<main>Reporte ficticio</main>", "Reporte", popup);
    assert.equal(popup.closed, false);
    assert.deepEqual([...storage.values], keys);
    lifecycle.dispatchEvent(new Event("pagehide"));
    assert.equal(popup.closed, true);
    await import("../src/utils/pdfDocument.js?after-reload");
    await logout();
    assert.equal(popup.closed, true);
  } finally {
    if (previousWindow === undefined) delete globalThis.window;
    else globalThis.window = previousWindow;
  }
});

test("salir limpia el estudiante y cambia el ámbito de la interfaz antes de recibir la revocación", async () => {
  const storage = fakeStorage();
  globalThis.localStorage = storage;
  const token = "42|token-ficticio";
  acceptSession({
    token,
    student: { carnet: "2024-CON-001" },
    idle_minutes: 30,
    expires_at: new Date(Date.now() + 60_000).toISOString(),
  });
  const chatKey = `chat_session_id:${sessionScope(token)}`;
  storage.setItem(chatKey, "historial-privado");
  let completeRevocation;
  globalThis.fetch = (_url, options) => new Promise((resolve) => {
    assert.equal(options.headers.Authorization, `Bearer ${token}`);
    completeRevocation = resolve;
  });

  const revocation = logout();
  assert.deepEqual(getAuthSnapshot(), { token: null, student: null });
  assert.equal(sessionScope(getAuthSnapshot().token), "guest");
  assert.equal(storage.getItem(chatKey), null);
  assert.equal(storage.getItem("uncpggl_student"), null);
  completeRevocation({ ok: true });
  await revocation;
});
