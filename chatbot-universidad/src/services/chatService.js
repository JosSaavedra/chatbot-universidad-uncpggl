const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

const getSessionId = () => {
  let sessionId = localStorage.getItem("chat_session_id");
  if (!sessionId) {
    sessionId = "sess_" + Math.random().toString(36).substr(2, 9) + "_" + Date.now();
    localStorage.setItem("chat_session_id", sessionId);
  }
  return sessionId;
};

export const sendMessage = async (message) => {
  const response = await fetch(`${API_URL}/chat`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Accept": "application/json",
    },
    body: JSON.stringify({
      message,
      session_id: getSessionId(),
    }),
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({}));
    throw new Error(err?.message || `Error ${response.status}`);
  }

  return response.json();
};

export const loadHistory = async () => {
  const sessionId = getSessionId();

  const response = await fetch(`${API_URL}/chat/history?session_id=${sessionId}`, {
    headers: { "Accept": "application/json" },
  });

  if (!response.ok) return [];

  const data = await response.json();
  return data.messages || [];
};

export const clearSession = () => {
  localStorage.removeItem("chat_session_id");
};