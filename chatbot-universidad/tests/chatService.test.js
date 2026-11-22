import { afterEach, test } from "node:test";
import assert from "node:assert/strict";
import { acceptSession, logout } from "../src/services/authService.js";
import { deleteHistory, loadHistory, sendMessage } from "../src/services/chatService.js";

const originalFetch = globalThis.fetch;
const originalStorage = globalThis.localStorage;

afterEach(() => {
  globalThis.fetch = originalFetch;
  if (originalStorage === undefined) delete globalThis.localStorage;
  else globalThis.localStorage = originalStorage;
});

function setUpStorage() {
  const values = new Map();
  globalThis.localStorage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: (key) => values.delete(key),
  };
  return values;
}

function response(payload) {
  return { ok: true, status: 200, json: async () => payload };
}

test("Laravel entrega el identificador y la prueba del invitado nunca viaja en la URL", async () => {
  const storage = setUpStorage();
  storage.set("chat_session_id:guest", "sess_legacy");
  let call = 0;
  globalThis.fetch = async (url, options) => {
    call++;
    if (call === 1) {
      assert.deepEqual(JSON.parse(options.body), { message: "Hola" });
      return response({ session_id: "servidor", guest_token: "prueba" });
    }
    assert.match(url, /session_id=servidor$/);
    assert.doesNotMatch(url, /prueba/);
    assert.equal(options.headers["X-Chat-Guest-Token"], "prueba");
    return response({ messages: [{ content: "Hola" }] });
  };

  assert.deepEqual(await loadHistory(), []);
  await sendMessage("Hola");
  assert.deepEqual(await loadHistory(), [{ content: "Hola" }]);
  assert.equal(call, 2);
});

test("el borrado conserva la referencia hasta que Laravel lo confirma", async () => {
  const storage = setUpStorage();
  const key = "chat_session_id:guest";
  storage.set(key, JSON.stringify({ id: "invitado", guestToken: "prueba" }));
  globalThis.fetch = async (url, options) => {
    assert.match(url, /chat\/history\?session_id=invitado$/);
    assert.equal(options.method, "DELETE");
    assert.equal(options.headers["X-Chat-Guest-Token"], "prueba");
    return { ok: false, status: 503 };
  };
  await assert.rejects(deleteHistory());
  assert.equal(storage.has(key), true);

  globalThis.fetch = async () => ({ ok: true, status: 204 });
  await deleteHistory();
  assert.equal(storage.has(key), false);
});

test("el chat invitado no se transfiere y una respuesta tardía no restaura el historial al salir", async () => {
  const storage = setUpStorage();
  storage.set("chat_session_id:guest", JSON.stringify({ id: "invitado", guestToken: "prueba" }));
  acceptSession({
    token: "42|token-ficticio",
    student: { carnet: "2024-CON-3189" },
    idle_minutes: 30,
    expires_at: new Date(Date.now() + 60_000).toISOString(),
  });

  let finishChat;
  globalThis.fetch = (url, options) => {
    if (url.endsWith("/logout")) return Promise.resolve(response({}));
    assert.match(url, /\/chat\/auth$/);
    assert.deepEqual(JSON.parse(options.body), { message: "Mis notas" });
    assert.equal(options.headers["X-Chat-Guest-Token"], undefined);
    return new Promise((resolve) => { finishChat = resolve; });
  };

  const pending = sendMessage("Mis notas");
  await logout();
  finishChat(response({ session_id: "privado", reply: "Respuesta anterior" }));
  await pending;
  assert.equal(storage.has("chat_session_id:session-42"), false);
  assert.equal(storage.has("chat_session_id:guest"), false);
});
