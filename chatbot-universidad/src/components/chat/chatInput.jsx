import { useState, useRef, useImperativeHandle, forwardRef } from "react";

const ChatInput = forwardRef(function ChatInput({ onSend, disabled = false }, ref) {
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
