import { useState } from "react";
import ReactMarkdown from "react-markdown";
import escudo from "../../assets/universidad.png.webp";
import TypewriterText from "./TypewriterText";

function MessageBubble({ message, animate = false }) {
  const isBot = message.role === "bot";
  // Cada burbuja tiene su propio estado (key = message.id), así que inicia siempre sin animar.
  const [typed, setTyped] = useState(false);

  const renderMarkdown = (content) => (
    <ReactMarkdown
      components={{
        p: ({ children }) => (
          <p style={{ margin: "4px 0" }}>{children}</p>
        ),
        ul: ({ children }) => (
          <ul style={{ paddingLeft: "18px", margin: "4px 0" }}>{children}</ul>
        ),
        ol: ({ children }) => (
          <ol style={{ paddingLeft: "18px", margin: "4px 0" }}>{children}</ol>
        ),
        li: ({ children }) => (
          <li style={{ margin: "2px 0" }}>{children}</li>
        ),
        strong: ({ children }) => (
          <strong style={{ fontWeight: "600" }}>{children}</strong>
        ),
      }}
    >
      {content}
    </ReactMarkdown>
  );

  const renderContent = (content) => {
    // El último mensaje se escribe con efecto máquina y luego se renderiza como Markdown.
    if (animate && isBot && !typed) {
      return (
        <TypewriterText text={content} speed={10} onComplete={() => setTyped(true)} />
      );
    }
    return renderMarkdown(content);
  };

  return (
    <div className={`message-wrapper ${message.role}`}>
      {isBot && (
        <div className="avatar-container">
          <img src={escudo} alt="Bot Avatar" className="bot-avatar-img" />
        </div>
      )}
      <div className={`message-bubble ${message.role}`}>
        <div className="message-text" style={{ wordBreak: "break-word" }}>
          {isBot ? renderContent(message.content) : (
            <span style={{ whiteSpace: "pre-wrap" }}>{message.content}</span>
          )}
        </div>
        <div className="message-footer">
          <span className="message-time">{message.time}</span>
        </div>
      </div>
    </div>
  );
}

export default MessageBubble;