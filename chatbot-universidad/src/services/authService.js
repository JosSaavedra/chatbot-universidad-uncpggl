const API_URL = (import.meta.env && import.meta.env.VITE_API_URL)
  || "http://localhost:8000/api";

const TOKEN_KEY = "uncpggl_token";
const STUDENT_KEY = "uncpggl_student";
const EXPIRY_KEY = "uncpggl_expiry";
const IDLE_KEY = "uncpggl_idle";

const listeners = new Set();

const readStorage = () => {
  try {
    const token = localStorage.getItem(TOKEN_KEY);
    const raw = localStorage.getItem(STUDENT_KEY);
    const student = raw ? JSON.parse(raw) : null;
    const idle = JSON.parse(localStorage.getItem(IDLE_KEY) || "{}");
    return token ? { token, student, expiresAt: Number(localStorage.getItem(EXPIRY_KEY)), ...idle } : { token: null, student: null };
  } catch {
    return { token: null, student: null };
  }
};

let snapshot = readStorage();
let expiryTimer;

const setSnapshot = (next) => {
  snapshot = next;
  armExpiry();
  listeners.forEach((listener) => listener());
};

function armExpiry() {
  clearTimeout(expiryTimer);
  if (snapshot.expiresAt) {
    const until = Math.min(snapshot.expiresAt, snapshot.idleUntil || Infinity);
    expiryTimer = setTimeout(clearLocal, Math.max(0, until - Date.now()));
  }
}

function clearLocal() {
  try {
    if (snapshot.token) localStorage.removeItem(`chat_session_id:${sessionScope(snapshot.token)}`);
    for (const key of [TOKEN_KEY, STUDENT_KEY, EXPIRY_KEY, IDLE_KEY, "chat_session_id"]) localStorage.removeItem(key);
  } catch { /* Sin almacenamiento, también se limpia la memoria. */ }
  setSnapshot({ token: null, student: null });
}

armExpiry();
if (typeof window !== "undefined") {
  window.addEventListener("storage", (event) => {
    if ([TOKEN_KEY, EXPIRY_KEY, IDLE_KEY, null].includes(event.key)) setSnapshot(readStorage());
  });
}

export const sessionFetch = async (url, options) => {
  const sent = options.headers?.Authorization;
  const response = await fetch(url, { ...options, cache: "no-store" });
  if (response.status === 401 && sent && sent === `Bearer ${snapshot.token}`) clearLocal();
  else if (response.ok && sent === `Bearer ${snapshot.token}` && snapshot.idleMs && !url.includes("/chat/history")) {
    const idle = { idleMs: snapshot.idleMs, idleUntil: Date.now() + snapshot.idleMs };
    try { localStorage.setItem(IDLE_KEY, JSON.stringify(idle)); } catch { /* Solo memoria. */ }
    setSnapshot({ ...snapshot, ...idle });
  }
  return response;
};

export const getAccessMode = async () => {
  const response = await fetch(`${API_URL}/auth/mode`, { cache: "no-store" });
  if (!response.ok) throw new Error("No se pudo consultar el modo de acceso.");
  const data = await response.json();
  return {
    mode: data.mode,
  };
};

/**
 * Suscripción al estado de sesión (para useSyncExternalStore).
 */
export const subscribeAuth = (listener) => {
  listeners.add(listener);
  return () => listeners.delete(listener);
};

export const getAuthSnapshot = () => snapshot;

export const getToken = () => snapshot.token;

// Sanctum entrega "id público|secreto". Nunca usar el secreto como key de React.
export const sessionScope = (token) => {
  if (!token) return "guest";
  const id = /^(\d+)\|/.exec(token)?.[1];
  return id ? `session-${id}` : "authenticated";
};

export const getStudent = () => snapshot.student;

export const isAuthenticated = () => Boolean(snapshot.token);

/**
 * Inicia sesión con carné (demostración) o correo (cuenta institucional),
 * siempre acompañado de la contraseña individual.
 *
 * La contraseña sólo existe en memoria durante la petición: nunca se guarda
 * en localStorage/sessionStorage ni se incluye en mensajes de error.
 */
export const loginByCarnet = async (identifier, password) => {
  const normalizedIdentifier = String(identifier ?? "").trim();

  if (!normalizedIdentifier) {
    throw new Error("Ingresa tu carné o correo para iniciar sesión.");
  }

  if (typeof password !== "string" || password.length === 0) {
    throw new Error("Ingresa tu contraseña para iniciar sesión.");
  }

  const identifierField = normalizedIdentifier.includes("@") ? "email" : "carnet";
  const response = await fetch(`${API_URL}/login`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ [identifierField]: normalizedIdentifier, password }),
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    if (response.status === 429) {
      throw new Error("Demasiados intentos de acceso. Espera un minuto antes de volver a intentarlo.");
    }

    const message =
      payload?.errors?.carnet?.[0] ||
      payload?.errors?.email?.[0] ||
      payload?.message ||
      `No se pudo iniciar sesión (Error ${response.status}).`;
    throw new Error(message);
  }

  if (response.status === 202 && payload.method === "email") return payload;

  return acceptSession(payload);
};

export const acceptSession = (payload) => {
  const idleMs = Number(payload.idle_minutes) * 60_000;
  const idle = { idleMs, idleUntil: Date.now() + idleMs };
  try {
    localStorage.removeItem("chat_session_id:guest");
    localStorage.setItem(TOKEN_KEY, payload.token);
    localStorage.setItem(IDLE_KEY, JSON.stringify(idle));
    localStorage.setItem(EXPIRY_KEY, String(Date.parse(payload.expires_at)));
    localStorage.setItem(STUDENT_KEY, JSON.stringify(payload.student || null));
  } catch {
    /* localStorage no disponible */
  }

  setSnapshot({ token: payload.token, student: payload.student || null, expiresAt: Date.parse(payload.expires_at), ...idle });

  return payload;
};

/**
 * Cierra la sesión actual (limpia el token y, si es posible, lo revoca).
 */
export const logout = async () => {
  const token = snapshot.token;

  clearLocal();

  if (token) {
    try {
      const response = await fetch(`${API_URL}/logout`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
      });
      if (!response.ok && response.status !== 401) throw new Error();
    } catch {
      throw new Error("La sesión se cerró en este navegador, pero no se pudo confirmar su revocación en el servidor.");
    }
  }
};
