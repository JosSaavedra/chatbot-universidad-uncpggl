const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

const TOKEN_KEY = "uncpggl_token";
const STUDENT_KEY = "uncpggl_student";

const listeners = new Set();

const readStorage = () => {
  try {
    const token = localStorage.getItem(TOKEN_KEY);
    const raw = localStorage.getItem(STUDENT_KEY);
    const student = raw ? JSON.parse(raw) : null;
    return token ? { token, student } : { token: null, student: null };
  } catch {
    return { token: null, student: null };
  }
};

let snapshot = readStorage();

const setSnapshot = (next) => {
  snapshot = next;
  listeners.forEach((listener) => listener());
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

export const getStudent = () => snapshot.student;

export const isAuthenticated = () => Boolean(snapshot.token);

/**
 * Inicia sesión con el código de carné. Guarda el token y el estudiante en
 * localStorage y notifica a los componentes suscritos.
 */
export const loginByCarnet = async (carnet) => {
  const response = await fetch(`${API_URL}/login/carnet`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ carnet }),
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message =
      payload?.errors?.carnet?.[0] ||
      payload?.message ||
      `No se pudo iniciar sesión (Error ${response.status}).`;
    throw new Error(message);
  }

  try {
    localStorage.setItem(TOKEN_KEY, payload.token);
    localStorage.setItem(STUDENT_KEY, JSON.stringify(payload.student || null));
  } catch {
    /* localStorage no disponible */
  }

  setSnapshot({ token: payload.token, student: payload.student || null });

  return payload;
};

/**
 * Cierra la sesión actual (limpia el token y, si es posible, lo revoca).
 */
export const logout = async () => {
  const token = snapshot.token;

  try {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(STUDENT_KEY);
  } catch {
    /* localStorage no disponible */
  }

  setSnapshot({ token: null, student: null });

  if (token) {
    try {
      await fetch(`${API_URL}/logout`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
      });
    } catch {
      /* best-effort */
    }
  }
};
