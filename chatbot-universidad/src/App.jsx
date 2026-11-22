import { lazy, Suspense, useState } from "react";
import ChatContainer from "./components/chat/chatContainer";
import { useAuth } from "./hooks/useAuth";
import { sessionScope } from "./services/authService";
import { useTheme } from "./hooks/useTheme";
import escudo from "./assets/universidad.png.webp";
import "./App.css";

// Las vistas secundarias se cargan bajo demanda para aligerar el bundle
// inicial (FullCalendar y las vistas de datos solo se descargan al abrirlas).
const CalendarView = lazy(() => import("./components/calendar/CalendarView"));
const OfertaAcademicaView = lazy(() => import("./components/oferta/OfertaAcademicaView"));
const CarrerasView = lazy(() => import("./components/carreras/CarrerasView"));
const PagosView = lazy(() => import("./components/pagos/PagosView"));
const HorarioView = lazy(() => import("./components/horario/HorarioView"));
const GradesView = lazy(() => import("./components/notas/GradesView"));
const LoginModal = lazy(() => import("./components/auth/LoginModal"));

function ThemeIcon({ theme }) {
  if (theme === "dark") {
    return (
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        aria-hidden="true"
        focusable="false"
      >
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41" />
      </svg>
    );
  }

  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>
  );
}

const TITLES = {
  chat: "Asistente Académico IA",
  calendar: "Calendario Académico",
  oferta: "Sedes y Facultades",
  carreras: "Carreras",
  pagos: "Pagos del Estudiante",
  horario: "Horarios de Clases",
  notas: "Mis Notas",
};

function App() {
  const [view, setView] = useState("chat");
  const [ofertaTab, setOfertaTab] = useState("facultad");
  const [pendingMessage, setPendingMessage] = useState(null);
  const [carreraInicialId, setCarreraInicialId] = useState(null);
  const [horarioSelection, setHorarioSelection] = useState(null);
  const [showLogin, setShowLogin] = useState(false);
  const [afterLoginView, setAfterLoginView] = useState(null);

  const { isAuthed, student, logout, token } = useAuth();
  const { theme, toggleTheme } = useTheme();

  const openOferta = (tab) => {
    setOfertaTab(tab === "sede" ? "sede" : "facultad");
    setView("oferta");
  };

  // Abre "Ver carreras"; si se recibe un id, muestra directamente su ficha.
  const openCarreras = (careerId = null) => {
    setCarreraInicialId(careerId ?? null);
    setView("carreras");
  };

  // Abre la consulta de pagos y recibos por carné.
  const openPagos = () => {
    setView("pagos");
  };

  // Abre el módulo de horarios (con una selección previa opcional).
  const openHorario = (selection = null) => {
    setHorarioSelection(selection);
    setView("horario");
  };

  // Abre el módulo de calificaciones del estudiante.
  const openNotas = () => {
    setView("notas");
  };

  // Abre el inicio de sesión. `redirect` indica a qué vista ir tras iniciar
  // sesión (p. ej. el horario cuando se entra desde una tarjeta de horario).
  const openLogin = (redirect = null) => {
    setAfterLoginView(redirect);
    setShowLogin(true);
  };

  // Permite que "Preguntar al asistente" vuelva al chat con una consulta lista.
  const askAssistant = (text) => {
    setPendingMessage(text);
    setView("chat");
  };

  return (
    <div className="app">
      <div className="chat-card" key={sessionScope(token)}>

        <div className="chat-header">
          <img src={escudo} alt="Escudo" className="logo" />
          <div>
            <h1>{TITLES[view] || TITLES.chat}</h1>
            <p>Universidad Nacional Comandante Padre Gaspar García Laviana</p>
          </div>

          <div className="header-auth">
            <button
              type="button"
              className="theme-toggle"
              onClick={toggleTheme}
              aria-label={theme === "dark" ? "Cambiar al modo claro" : "Cambiar al modo noche"}
              title={theme === "dark" ? "Cambiar al modo claro" : "Cambiar al modo noche"}
            >
              <ThemeIcon theme={theme} />
              <span className="theme-toggle-label">
                {theme === "dark" ? "Cambiar a claro" : "Cambiar a oscuro"}
              </span>
            </button>

            {isAuthed ? (
              <>
                <span className="header-auth-chip" title="Sesión activa">
                  🎓 {student?.carnet || "Estudiante"}
                </span>
                <button
                  className="header-auth-btn"
                onClick={() => logout().catch((error) => window.alert(error.message))}
                >
                  Salir
                </button>
              </>
            ) : (
              <button
                className="header-auth-btn primary"
                onClick={() => openLogin(null)}
              >
                Iniciar sesión
              </button>
            )}
          </div>
        </div>

        {view === "chat" && (
          <ChatContainer
            onOpenCalendar={() => setView("calendar")}
            onOpenOferta={openOferta}
            onOpenCarreras={openCarreras}
            onOpenPagos={openPagos}
            onOpenHorario={openHorario}
            onOpenNotas={openNotas}
            onLogin={() => openLogin("horario")}
            pendingMessage={pendingMessage}
            onPendingConsumed={() => setPendingMessage(null)}
          />
        )}

        <Suspense
          fallback={
            <div className="view-loading">
              <div className="view-loading-spinner" aria-hidden="true" />
              <p>Cargando…</p>
            </div>
          }
        >
          {view === "calendar" && <CalendarView onBack={() => setView("chat")} />}
          {view === "oferta" && (
            <OfertaAcademicaView
              key={ofertaTab}
              onBack={() => setView("chat")}
              initialTab={ofertaTab}
            />
          )}
          {view === "carreras" && (
            <CarrerasView
              onBack={() => setView("chat")}
              onAskAssistant={askAssistant}
              initialCareerId={carreraInicialId}
            />
          )}
          {view === "pagos" && <PagosView onBack={() => setView("chat")} onLogin={() => openLogin(null)} />}
          {view === "notas" && (
            <GradesView
              onBack={() => setView("chat")}
              onLogin={() => openLogin("notas")}
            />
          )}
          {view === "horario" && (
            <HorarioView
              onBack={() => setView("chat")}
              onLogin={() => openLogin(null)}
              initialSelection={horarioSelection}
            />
          )}

          {showLogin && (
            <LoginModal
              onClose={() => setShowLogin(false)}
              onSuccess={() => {
                setView(afterLoginView || "chat");
              }}
            />
          )}
        </Suspense>

      </div>
    </div>
  );
}

export default App;
