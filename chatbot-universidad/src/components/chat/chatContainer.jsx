import { useEffect, useRef, useState, useCallback } from "react";
import { useChat } from "../../hooks/useChat";
import MessageBubble from "./MessageBubble";
import ChatInput from "./chatInput";
import QuickReplies from "./QuickReplies";
import PrivacyModal from "./PrivacyModal";
import escudo from "../../assets/universidad.png.webp";
import { deleteHistory } from "../../services/chatService";

function ChatContainer({ onOpenCalendar, onOpenOferta, onOpenCarreras, onOpenPagos, onOpenHorario, onOpenNotas, onLogin, pendingMessage, onPendingConsumed }) {
  const { messages, loading, handleSend, historyLoaded } = useChat();
  const messagesEndRef = useRef(null);
  const messagesContainerRef = useRef(null);
  const chatInputRef = useRef(null);
  const quickMenuRef = useRef(null);
  const quickMenuButtonRef = useRef(null);
  const [userScrolledUp, setUserScrolledUp] = useState(false);
  // El menú de información/atajos empieza cerrado para que el chat ocupe
  // todo el alto disponible; se abre con el botón «+» del campo de escritura.
  const [quickMenuOpen, setQuickMenuOpen] = useState(false);
  const [showPrivacy, setShowPrivacy] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState("");
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

  const handleNewConversation = async () => {
    setDeleteError("");
    setDeleting(true);
    try {
      await deleteHistory();
      window.location.reload();
    } catch (error) {
      setDeleteError(error.message);
      setDeleting(false);
    }
  };

  // Cierra el menú al hacer clic fuera de él o al pulsar Escape.
  useEffect(() => {
    if (!quickMenuOpen) return;

    const handlePointerDown = (event) => {
      if (quickMenuRef.current?.contains(event.target)) return;
      if (quickMenuButtonRef.current?.contains(event.target)) return;
      setQuickMenuOpen(false);
    };

    const handleKeyDown = (event) => {
      if (event.key !== "Escape") return;
      setQuickMenuOpen(false);
      quickMenuButtonRef.current?.focus();
    };

    document.addEventListener("pointerdown", handlePointerDown);
    window.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("pointerdown", handlePointerDown);
      window.removeEventListener("keydown", handleKeyDown);
    };
  }, [quickMenuOpen]);

  const onSend = useCallback(async (text) => {
    setQuickMenuOpen(false);
    await handleSend(text);
  }, [handleSend]);

  const onQuickReply = useCallback(async (text) => {
    setQuickMenuOpen(false);
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
        <button className="new-chat-btn" onClick={handleNewConversation} disabled={loading || deleting}>
          {deleting ? "Borrando…" : "Borrar historial y empezar de nuevo"}
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
            onOpenNotas={onOpenNotas}
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

      {userScrolledUp && (
        <button className="scroll-bottom-btn" onClick={scrollToBottom}>
          ↓
        </button>
      )}

      <div className="chat-composer">
        <div ref={quickMenuRef}>
          <QuickReplies
            onSelect={onQuickReply}
            onOpenCalendar={onOpenCalendar}
            onOpenOferta={onOpenOferta}
            onOpenCarreras={onOpenCarreras}
            onOpenPagos={onOpenPagos}
            onOpenHorario={onOpenHorario}
            onOpenNotas={onOpenNotas}
            visible={quickMenuOpen && !loading}
          />
        </div>

        {deleteError && <p role="alert">{deleteError}</p>}
        <ChatInput
          ref={chatInputRef}
          onSend={onSend}
          disabled={loading || deleting}
          menuOpen={quickMenuOpen}
          onToggleMenu={() => setQuickMenuOpen((open) => !open)}
          menuButtonRef={quickMenuButtonRef}
        />
      </div>

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
