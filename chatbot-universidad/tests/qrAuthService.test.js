import { test, afterEach } from "node:test";
import assert from "node:assert/strict";
import {
  cancelQr,
  exchangeQr,
  getQrStatus,
  requestQr,
} from "../src/services/qrAuthService.js";

const originalFetch = globalThis.fetch;
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

afterEach(() => {
  globalThis.fetch = originalFetch;
  calls = [];
});

test("requestQr envía únicamente el carné", async () => {
  const expected = {
    request_id: "a".repeat(32),
    client_nonce: "b".repeat(64),
    qr_url: "https://backend.example/acceso/qr/" + "c".repeat(64),
    confirm_code: "123456",
    expires_at: "2030-01-01T00:00:00.000Z",
  };
  globalThis.fetch = fakeFetch(expected, 202);

  const result = await requestQr("2024-MED-001");

  assert.deepEqual(result, { ...expected, carnet: "2024-MED-001" });
  assert.equal(calls.length, 1);
  assert.match(calls[0].url, /\/api\/qr-auth\/request$/);
  assert.equal(calls[0].options.method, "POST");
  assert.deepEqual(JSON.parse(calls[0].options.body), { carnet: "2024-MED-001" });
  assert.doesNotMatch(calls[0].options.body, /email|correo|google|password/i);
});

test("requestQr rechaza un enlace relativo antes de generar la imagen", async () => {
  globalThis.fetch = fakeFetch({ qr_url: "/acceso/qr/" + "c".repeat(64) }, 202);

  await assert.rejects(requestQr("2024-MED-001"), /URL válida/);
});

test("getQrStatus lleva el nonce en una cabecera, nunca en la URL", async () => {
  globalThis.fetch = fakeFetch({ status: "pending", expires_at: "2030-01-01T00:00:00.000Z" });

  await getQrStatus("a".repeat(32), "b".repeat(64));

  assert.match(calls[0].url, /\/api\/qr-auth\/status\/a{32}$/);
  assert.doesNotMatch(calls[0].url, /b{64}/);
  assert.equal(calls[0].options.headers["X-Approval-Nonce"], "b".repeat(64));
  assert.equal(calls[0].options.body, undefined);
});

test("exchangeQr y cancelQr reenvían el nonce sin exponer credenciales", async () => {
  globalThis.fetch = fakeFetch({ token: "id|secreto" });
  const id = "a".repeat(32);
  const nonce = "b".repeat(64);

  await exchangeQr(id, nonce);
  await cancelQr(id, nonce);

  assert.equal(calls.length, 2);
  for (const call of calls) {
    assert.equal(call.options.method, "POST");
    assert.equal(call.options.headers["X-Approval-Nonce"], nonce);
    assert.doesNotMatch(call.url, /secreto|id_token|code_verifier/);
    assert.doesNotMatch(call.options.body, /email|password|google/i);
  }
  assert.match(calls[0].url, /\/exchange$/);
  assert.match(calls[1].url, /\/cancel$/);
});

test("los errores del backend se muestran sin crear una sesión", async () => {
  globalThis.fetch = fakeFetch({ message: "Solicitud cancelada." }, 409);

  await assert.rejects(() => cancelQr("a".repeat(32), "b".repeat(64)), /Solicitud cancelada/);
});
