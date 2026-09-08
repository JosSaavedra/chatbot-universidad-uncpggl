import ChatContainer from "./components/chat/chatContainer";
import escudo from "./assets/universidad.png.webp";
import "./App.css";

function App() {
  return (
    <div className="app">
      <div className="chat-card">

        <div className="chat-header">
          <img src={escudo} alt="Escudo" className="logo" />
          <div>
            <h1>Asistente Académico IA</h1>
            <p>Universidad Nacional Comandante Padre Gaspar García Laviana</p>
          </div>
        </div>

        <ChatContainer />

      </div>
    </div>
  );
}

export default App;
