const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

const getSessionId = () => {
  let sessionId = localStorage.getItem("chat_session_id");
  if (!sessionId) {
    sessionId = "sess_" + Math.random().toString(36).substring(2, 11) + "_" + Date.now();
    localStorage.setItem("chat_session_id", sessionId);
  }
  return sessionId;
};

export const sendMessage = async (message) => {
  // Si hay sesión iniciada se usa el endpoint autenticado para que el bot
  // pueda responder con la información personal (p. ej. el horario propio).
  let token = null;
  try {
    token = localStorage.getItem("uncpggl_token");
  } catch {
    /* localStorage no disponible */
  }

  const endpoint = token ? "chat/auth" : "chat";
  const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${API_URL}/${endpoint}`, {
    method: "POST",
    headers,
    body: JSON.stringify({
      message,
      session_id: getSessionId(),
    }),
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({}));
    const error = new Error(err?.message || `Error ${response.status}`);
    error.status = response.status;
    throw error;
  }

  return response.json();
};

export const loadHistory = async () => {
  const sessionId = getSessionId();

  // El historial de una conversación personal exige el token del dueño; el
  // backend responde 403 si intenta leerse sin autorización.
  const headers = { Accept: "application/json" };
  try {
    const token = localStorage.getItem("uncpggl_token");
    if (token) headers.Authorization = `Bearer ${token}`;
  } catch {
    /* localStorage no disponible */
  }

  const response = await fetch(`${API_URL}/chat/history?session_id=${sessionId}`, {
    headers,
  });

  if (!response.ok) return [];

  const data = await response.json();
  return data.messages || [];
};

export const clearSession = () => {
  localStorage.removeItem("chat_session_id");
};