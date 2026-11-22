import { useState, useRef, useImperativeHandle, forwardRef } from "react";

const ChatInput = forwardRef(function ChatInput(
  { onSend, disabled = false, menuOpen = false, onToggleMenu, menuButtonRef },
  ref
) {
  const [text, setText] = useState("");
  const inputRef = useRef(null);

  useImperativeHandle(ref, () => ({
    focus: () => inputRef.current?.focus(),
  }));

  const handleSubmit = () => {
    if (!text.trim() || disabled) return;
    onSend(text);
    setText("");
  };

  return (
    <div className="input-container">
      <button
        type="button"
        ref={menuButtonRef}
        className={`quick-menu-toggle${menuOpen ? " is-open" : ""}`}
        onClick={onToggleMenu}
        disabled={disabled}
        aria-label={
          menuOpen
            ? "Ocultar el menú de preguntas frecuentes"
            : "Mostrar el menú de preguntas frecuentes"
        }
        aria-expanded={menuOpen}
        aria-controls="quick-replies-menu"
        title={
          menuOpen
            ? "Ocultar preguntas frecuentes"
            : "Mostrar preguntas frecuentes"
        }
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2.2"
          strokeLinecap="round"
          aria-hidden="true"
          focusable="false"
        >
          <path d="M12 5v14M5 12h14" />
        </svg>
      </button>

      <input
        ref={inputRef}
        className="chat-input"
        value={text}
        placeholder={disabled ? "Esperando respuesta..." : "Escribe tu consulta..."}
        onChange={(e) => setText(e.target.value)}
        onKeyDown={(e) => e.key === "Enter" && handleSubmit()}
        disabled={disabled}
      />
      <button
        className="send-btn"
        onClick={handleSubmit}
        disabled={disabled}
        style={{ opacity: disabled ? 0.5 : 1, cursor: disabled ? "not-allowed" : "pointer" }}
      >
        ➤
      </button>
    </div>
  );
});

export default ChatInput;
