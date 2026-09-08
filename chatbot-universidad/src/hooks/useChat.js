import { useState, useEffect } from "react";
import { sendMessage, loadHistory } from "../services/chatService";

const formatTime = (dateStr) => {
  if (!dateStr) return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

export const useChat = () => {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const fetchHistory = async () => {
      try {
        const history = await loadHistory();
        if (history.length > 0) {
          const formatted = history.map((msg, index) => ({
            id: `history-${index}`,
            role: msg.role,
            content: msg.content,
            time: formatTime(msg.created_at),
          }));
          setMessages(formatted);
        } else {
          setMessages([{
            id: "initial-bot-message",
            role: "bot",
            content: "¡Hola! Soy el asistente universitario de la UNCPGGL. ¿En qué puedo ayudarte?",
            time: formatTime(),
          }]);
        }
      } catch {
        setMessages([{
          id: "initial-bot-message",
          role: "bot",
          content: "¡Hola! Soy el asistente universitario de la UNCPGGL. ¿En qué puedo ayudarte?",
          time: formatTime(),
        }]);
      }
    };

    fetchHistory();
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
        time: formatTime(),
      };

      setMessages((prev) => [...prev, botMessage]);
    } catch (error) {
      console.error("Chat Error:", error);
      setMessages((prev) => [
        ...prev,
        {
          id: `error-${Date.now()}`,
          role: "bot",
          content: "No se pudo conectar con el servidor. Por favor, revisa tu conexión.",
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
  };
};
