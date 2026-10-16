import { useState, useEffect } from "react";
import { sendMessage, loadHistory } from "../services/chatService";

const formatTime = (dateStr) => {
  if (!dateStr) return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const greetingMessage = () => ({
  id: "initial-bot-message",
  role: "bot",
  content: "¡Hola! Soy el asistente universitario de la UNCPGGL. ¿En qué puedo ayudarte?",
  time: formatTime(),
});

export const useChat = () => {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [historyLoaded, setHistoryLoaded] = useState(false);

  useEffect(() => {
    let active = true;

    const fetchHistory = async () => {
      let base;
      try {
        const history = await loadHistory();
        base =
          history.length > 0
            ? history.map((msg, index) => ({
                id: `history-${index}`,
                role: msg.role,
                content: msg.content,
                data: msg.data || null,
                time: formatTime(msg.created_at),
              }))
            : [greetingMessage()];
      } catch {
        base = [greetingMessage()];
      }

      if (!active) return;

      // No sobrescribir mensajes que ya existan: en modo estricto (desarrollo)
      // el efecto se ejecuta dos veces y una segunda carga tardía borraría el
      // mensaje recién enviado desde "Preguntar al asistente".
      setMessages((prev) => (prev.length > 0 ? prev : base));
      setHistoryLoaded(true);
    };

    fetchHistory();
    return () => {
      active = false;
    };
  }, []);

  const handleSend = async (text) => {
    if (!text?.trim() || loading) return;

    const trimmedText = text.trim();
    const userMessage = {
      id: `user-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`,
      role: "user",
      content: trimmedText,
      time: formatTime(),
    };

    setMessages((prev) => [...prev, userMessage]);
    setLoading(true);

    try {
      const data = await sendMessage(trimmedText);

      const botMessage = {
        id: `bot-${Date.now()}`,
        role: "bot",
        content: data?.reply || "Lo siento, no pude procesar tu respuesta.",
        data: data?.data || null,
        time: formatTime(),
      };

      setMessages((prev) => [...prev, botMessage]);
    } catch (error) {
      console.error("Chat Error:", error);
      let content = "No se pudo conectar con el servidor. Por favor, revisa tu conexión.";
      if (error?.status === 429) {
        content = "Has enviado demasiadas consultas seguidas. Espera unos segundos e inténtalo de nuevo.";
      } else if (error?.status && error.status >= 500) {
        content = "El servidor está teniendo problemas en este momento. Inténtalo de nuevo en unos segundos.";
      }
      setMessages((prev) => [
        ...prev,
        {
          id: `error-${Date.now()}`,
          role: "bot",
          content,
          time: formatTime(),
        },
      ]);
    } finally {
      setLoading(false);
    }
  };

  return {
    messages,
    loading,
    handleSend,
    historyLoaded,
  };
};
