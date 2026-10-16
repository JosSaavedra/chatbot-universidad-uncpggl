import { useEffect, useRef, useState, useCallback } from "react";
import { useChat } from "../../hooks/useChat";
import MessageBubble from "./MessageBubble";
import ChatInput from "./chatInput";
import QuickReplies from "./QuickReplies";
import PrivacyModal from "./PrivacyModal";
import escudo from "../../assets/universidad.png.webp";
import { clearSession } from "../../services/chatService";

function ChatContainer({ onOpenCalendar, onOpenOferta, onOpenCarreras, onOpenPagos, onOpenHorario, onLogin, pendingMessage, onPendingConsumed }) {
  const { messages, loading, handleSend, historyLoaded } = useChat();
  const messagesEndRef = useRef(null);
  const messagesContainerRef = useRef(null);
  const chatInputRef = useRef(null);
  const [userScrolledUp, setUserScrolledUp] = useState(false);
  const [showQuickReplies, setShowQuickReplies] = useState(true);
  const [showPrivacy, setShowPrivacy] = useState(false);
  const pendingSentRef = useRef(false);

  const handleScroll = () => {
    const container = messagesContainerRef.current;
    if (!container) return;
    const isAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;
    setUserScrolledUp(!isAtBottom);
  };

  useEffect(() => {
    if (!userScrolledUp) {
      messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    }
  }, [messages, userScrolledUp]);

  useEffect(() => {
    if (!loading) {
      chatInputRef.current?.focus();
    }
  }, [loading]);

  // Solo el último mensaje del bot se anima con efecto máquina, y únicamente
  // cuando ya terminó de cargar (loading = false). Cada burbuja se anima una sola vez.
  const lastMessage = messages[messages.length - 1];
  const animateLastId = !loading && lastMessage?.role === "bot" ? lastMessage.id : null;

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    setUserScrolledUp(false);
  };

  const handleNewConversation = () => {
    clearSession();
    window.location.reload();
  };

  const onSend = useCallback(async (text) => {
    setShowQuickReplies(false);
    await handleSend(text);
  }, [handleSend]);

  const onQuickReply = useCallback(async (text) => {
    setShowQuickReplies(false);
    await handleSend(text);
  }, [handleSend]);

  // Envía una consulta pendiente (por ejemplo, desde "Preguntar al asistente"
  // en la ficha de una carrera) una sola vez, cuando el historial ya cargó.
  useEffect(() => {
    if (!pendingMessage || !historyLoaded || pendingSentRef.current) return;
    pendingSentRef.current = true;
    const text = pendingMessage;
    // Se difiere la actualización de estado para no encadenar renders dentro
    // del propio efecto (react-hooks/set-state-in-effect).
    queueMicrotask(() => {
      onSend(text);
      onPendingConsumed?.();
    });
  }, [pendingMessage, historyLoaded, onSend, onPendingConsumed]);

  return (
    <div className="chat-container">

      <div className="new-chat-bar">
        <button className="new-chat-btn" onClick={handleNewConversation}>
          Nueva conversación
        </button>
      </div>

      <div
        className="chat-messages"
        ref={messagesContainerRef}
        onScroll={handleScroll}
      >
        {messages.map((msg) => (
          <MessageBubble
            key={msg.id}
            message={msg}
            animate={msg.id === animateLastId}
            onOpenCarreras={onOpenCarreras}
            onOpenPagos={onOpenPagos}
            onOpenCalendar={onOpenCalendar}
            onOpenHorario={onOpenHorario}
            onLogin={onLogin}
            onSelect={onQuickReply}
          />
        ))}

        {loading && (
          <div className="message-wrapper bot typing">
            <div className="avatar-container">
              <img src={escudo} alt="Bot Avatar" className="bot-avatar-img" />
            </div>
            <div className="message-bubble bot">
              <div className="typing-indicator">
                <span></span><span></span><span></span>
              </div>
            </div>
          </div>
        )}

        <div ref={messagesEndRef}></div>
      </div>

      <QuickReplies
        onSelect={onQuickReply}
        onOpenCalendar={onOpenCalendar}
        onOpenOferta={onOpenOferta}
        onOpenCarreras={onOpenCarreras}
        onOpenPagos={onOpenPagos}
        onOpenHorario={onOpenHorario}
        visible={showQuickReplies && !loading}
      />

      {userScrolledUp && (
        <button className="scroll-bottom-btn" onClick={scrollToBottom}>
          ↓
        </button>
      )}

      <ChatInput ref={chatInputRef} onSend={onSend} disabled={loading} />

      <button
        type="button"
        className="privacy-link"
        onClick={() => setShowPrivacy(true)}
      >
        Privacidad y datos de demostración
      </button>

      {showPrivacy && <PrivacyModal onClose={() => setShowPrivacy(false)} />}
    </div>
  );
}

export default ChatContainer;
