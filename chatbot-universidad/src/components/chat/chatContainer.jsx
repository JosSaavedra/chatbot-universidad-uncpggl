import { useEffect, useRef, useState, useCallback } from "react";
import { useChat } from "../../hooks/useChat";
import MessageBubble from "./MessageBubble";
import ChatInput from "./chatInput";
import QuickReplies from "./QuickReplies";
import escudo from "../../assets/universidad.png.webp";
import { clearSession } from "../../services/chatService";

function ChatContainer() {
  const { messages, loading, handleSend } = useChat();
  const messagesEndRef = useRef(null);
  const messagesContainerRef = useRef(null);
  const chatInputRef = useRef(null);
  const [userScrolledUp, setUserScrolledUp] = useState(false);
  const [showQuickReplies, setShowQuickReplies] = useState(true);

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

      <QuickReplies onSelect={onQuickReply} visible={showQuickReplies && !loading} />

      {userScrolledUp && (
        <button className="scroll-bottom-btn" onClick={scrollToBottom}>
          ↓
        </button>
      )}

      <ChatInput ref={chatInputRef} onSend={onSend} disabled={loading} />
    </div>
  );
}

export default ChatContainer;
