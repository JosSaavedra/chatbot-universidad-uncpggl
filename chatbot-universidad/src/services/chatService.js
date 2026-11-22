import { getToken, sessionFetch, sessionScope } from "./authService.js";

const API_URL = import.meta.env?.VITE_API_URL || "http://localhost:8000/api";

const chatKey = (token) => `chat_session_id:${sessionScope(token)}`;

const readConversation = (raw, token) => {
  if (!raw) return null;
  try {
    const session = JSON.parse(raw);
    return typeof session?.id === "string" && (token || typeof session.guestToken === "string")
      ? session
      : null;
  } catch {
    return token ? { id: raw } : null;
  }
};

export const sendMessage = async (message) => {
  // Si hay sesión iniciada se usa el endpoint autenticado para que el bot
  // pueda responder con la información personal (p. ej. el horario propio).
  const token = getToken();
  const key = chatKey(token);
  const raw = localStorage.getItem(key);
  const session = readConversation(raw, token);

  const endpoint = token ? "chat/auth" : "chat";
  const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  } else if (session) {
    headers["X-Chat-Guest-Token"] = session.guestToken;
  }

  const response = await sessionFetch(`${API_URL}/${endpoint}`, {
    method: "POST",
    headers,
    body: JSON.stringify({
      message,
      session_id: session?.id,
    }),
  });

  if (!response.ok) {
    if (response.status === 403 && getToken() === token && localStorage.getItem(key) === raw) {
      localStorage.removeItem(key);
    }
    const err = await response.json().catch(() => ({}));
    const error = new Error(err?.message || `Error ${response.status}`);
    error.status = response.status;
    throw error;
  }

  const data = await response.json();
  if (getToken() === token && localStorage.getItem(key) === raw && typeof data.session_id === "string") {
    const guestToken = data.guest_token ?? session?.guestToken;
    if (token || typeof guestToken === "string") {
      localStorage.setItem(key, JSON.stringify({ id: data.session_id, guestToken }));
    }
  }
  return data;
};

export const loadHistory = async () => {
  const token = getToken();
  const key = chatKey(token);
  const raw = localStorage.getItem(key);
  const session = readConversation(raw, token);
  if (!session) return [];

  // El historial de una conversación personal exige el token del dueño; el
  // backend responde 403 si intenta leerse sin autorización.
  const headers = { Accept: "application/json" };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  } else {
    headers["X-Chat-Guest-Token"] = session.guestToken;
  }

  const response = await sessionFetch(`${API_URL}/chat/history?session_id=${encodeURIComponent(session.id)}`, {
    headers,
  });

  if (response.status === 403 && getToken() === token && localStorage.getItem(key) === raw) {
    localStorage.removeItem(key);
  }
  if (!response.ok) return [];

  const data = await response.json();
  return data.messages || [];
};

export const deleteHistory = async () => {
  const token = getToken();
  const key = chatKey(token);
  const raw = localStorage.getItem(key);
  const session = readConversation(raw, token);
  if (!session) return;

  const headers = { Accept: "application/json" };
  if (token) headers.Authorization = `Bearer ${token}`;
  else headers["X-Chat-Guest-Token"] = session.guestToken;

  const response = await sessionFetch(`${API_URL}/chat/history?session_id=${encodeURIComponent(session.id)}`, {
    method: "DELETE",
    headers,
  });
  if (!response.ok) throw new Error("No se pudo confirmar el borrado del historial.");
  if (getToken() === token && localStorage.getItem(key) === raw) localStorage.removeItem(key);
};
