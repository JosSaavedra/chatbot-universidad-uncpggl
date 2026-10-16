import { useEffect, useRef, useState } from "react";
import { useAuth } from "../../hooks/useAuth";
import {
  fetchMySchedule,
  fetchGeneralSchedule,
  fetchScheduleOptions,
} from "../../services/scheduleService";
import { dayShort, timeRange, scheduleMeta } from "../../utils/scheduleDisplay";
import { exportSchedulePdf } from "../../utils/pdfExport";
import DemoBadge from "../chat/DemoBadge";
import "./HorarioView.css";

function ClassCard({ item, compact = false }) {
  const meta = [
    item.classroom ? `📍 ${item.classroom}` : null,
    item.building,
    item.sede,
  ]
    .filter(Boolean)
    .join(" · ");

  return (
    <div
      className={`horario-class${item.is_current ? " is-current" : ""}${
        compact ? " compact" : ""
      }`}
    >
      <div className="horario-class-top">
        <span className="horario-class-time">{timeRange(item)}</span>
        {item.is_current ? (
          <span className="horario-class-now">En curso</span>
        ) : null}
      </div>
      <span className="horario-class-subject">{item.subject}</span>
      {meta ? <span className="horario-class-meta">{meta}</span> : null}
      {item.teacher ? (
        <span className="horario-class-meta">👨‍🏫 {item.teacher}</span>
      ) : null}
      {item.group && item.is_demo ? (
        <span className="horario-class-group">
          Año {item.year} · Grupo {item.group}
        </span>
      ) : null}
    </div>
  );
}

function Highlight({ kind, item }) {
  return (
    <div className={`horario-highlight ${kind}`}>
      <span className="horario-highlight-tag">
        {kind === "current" ? "Clase en curso" : `Próxima clase · ${dayShort(item.day)}`}
      </span>
      <span className="horario-highlight-subject">{item.subject}</span>
      <span className="horario-highlight-meta">
        🕐 {timeRange(item)}
        {item.classroom ? ` · 📍 ${item.classroom}` : ""}
        {item.building ? ` · ${item.building}` : ""}
        {item.teacher ? ` · 👨‍🏫 ${item.teacher}` : ""}
      </span>
    </div>
  );
}

function Agenda({ schedule, tab, dayFilter, onSelectDay }) {
  const classes = schedule.classes || [];
  const weekDays = (schedule.week || []).filter((d) => (d.items || []).length > 0);

  if (tab === "hoy") {
    const today = classes.filter((c) => c.is_today);
    return (
      <div className="horario-panel">
        {schedule.current_class ? (
          <Highlight kind="current" item={schedule.current_class} />
        ) : null}
        {schedule.next_class ? (
          <Highlight kind="next" item={schedule.next_class} />
        ) : null}

        {today.length === 0 ? (
          <p className="horario-empty">No hay clases para hoy.</p>
        ) : (
          <div className="horario-list">
            {today.map((c) => (
              <ClassCard key={c.id} item={c} />
            ))}
          </div>
        )}
      </div>
    );
  }

  // Vista semanal
  const shown = dayFilter
    ? weekDays.filter((d) => d.day === dayFilter)
    : weekDays;

  return (
    <div className="horario-panel">
      <div className="horario-days" role="tablist" aria-label="Filtrar por día">
        <button
          type="button"
          className={`horario-day-btn${!dayFilter ? " active" : ""}`}
          onClick={() => onSelectDay(null)}
        >
          Todos
        </button>
        {weekDays.map((d) => (
          <button
            key={d.day}
            type="button"
            className={`horario-day-btn${dayFilter === d.day ? " active" : ""}`}
            onClick={() => onSelectDay(d.day)}
          >
            {dayShort(d.day)}
          </button>
        ))}
      </div>

      {weekDays.length === 0 ? (
        <p className="horario-empty">No hay clases registradas.</p>
      ) : (
        <div className="horario-grid">
          {shown.map((d) => (
            <div className="horario-col" key={d.day}>
              <h4 className="horario-col-title">{d.day}</h4>
              <div className="horario-col-items">
                {d.items.map((c) => (
                  <ClassCard key={c.id} item={c} compact />
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function HorarioView({ onBack, onLogin, initialSelection }) {
  const { isAuthed, student, token, logout } = useAuth();

  // El modo se deriva del estado de sesión salvo que el usuario lo cambie
  // explícitamente (así, al iniciar sesión se pasa a "Mi horario" solo).
  const [modeOverride, setModeOverride] = useState(null);
  const mode = modeOverride ?? (isAuthed ? "personal" : "general");
  const [tab, setTab] = useState("hoy");
  const [dayFilter, setDayFilter] = useState(null);
  const [exportError, setExportError] = useState(null);

  const [personalData, setPersonalData] = useState(null);
  const [loadingPersonal, setLoadingPersonal] = useState(false);
  const [personalError, setPersonalError] = useState(null);

  const [options, setOptions] = useState(null);
  const [careerId, setCareerId] = useState(initialSelection?.careerId ?? "");
  const [year, setYear] = useState(initialSelection?.year ?? "");
  const [group, setGroup] = useState(initialSelection?.group ?? "");
  const [generalData, setGeneralData] = useState(null);
  const [loadingGeneral, setLoadingGeneral] = useState(false);
  const [generalError, setGeneralError] = useState(null);

  const initializedRef = useRef(false);

  // Catálogo de carreras/años/grupos para el modo demostración. En la misma
  // carga se calcula la selección inicial (carrera, año y grupo).
  useEffect(() => {
    let active = true;
    fetchScheduleOptions()
      .then((d) => {
        if (!active) return;
        setOptions(d);

        if (initializedRef.current) return;
        const careers = d?.careers || [];
        if (careers.length === 0) return;
        initializedRef.current = true;

        const validCareer =
          initialSelection?.careerId &&
          careers.some((c) => c.id === initialSelection.careerId)
            ? initialSelection.careerId
            : careers[0].id;
        const career = careers.find((c) => c.id === validCareer) || careers[0];
        const years = career.years || [];

        const validYear =
          initialSelection?.year &&
          years.some((y) => y.year === initialSelection.year)
            ? initialSelection.year
            : years[0]?.year ?? "";

        const groups = years.find((y) => y.year === validYear)?.groups || [];
        const validGroup =
          initialSelection?.group && groups.includes(initialSelection.group)
            ? initialSelection.group
            : groups[0] ?? "";

        setCareerId(validCareer);
        setYear(validYear);
        setGroup(validGroup);
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, [initialSelection]);

  // Horario personalizado.
  useEffect(() => {
    if (mode !== "personal" || !isAuthed) return undefined;
    let active = true;

    const load = async () => {
      setLoadingPersonal(true);
      setPersonalError(null);
      try {
        const data = await fetchMySchedule(token);
        if (active) setPersonalData(data);
      } catch (e) {
        if (active) setPersonalError(e.message);
      } finally {
        if (active) setLoadingPersonal(false);
      }
    };

    load();

    return () => {
      active = false;
    };
  }, [mode, isAuthed, token]);

  // Horario general (se actualiza al cambiar la selección).
  useEffect(() => {
    if (mode !== "general" || !careerId || !year || !group) return undefined;
    let active = true;

    const load = async () => {
      setLoadingGeneral(true);
      setGeneralError(null);
      try {
        const data = await fetchGeneralSchedule({ careerId, year, group });
        if (active) setGeneralData(data);
      } catch (e) {
        if (active) setGeneralError(e.message);
      } finally {
        if (active) setLoadingGeneral(false);
      }
    };

    load();

    return () => {
      active = false;
    };
  }, [mode, careerId, year, group]);

  const careers = options?.careers || [];
  const selectedCareer = careers.find((c) => c.id === careerId);
  const years = selectedCareer?.years || [];
  const groups = years.find((y) => y.year === year)?.groups || [];

  const onCareerChange = (id) => {
    setCareerId(id);
    const career = careers.find((c) => c.id === id);
    const nextYears = career?.years || [];
    const nextYear = nextYears[0]?.year ?? "";
    setYear(nextYear);
    setGroup(nextYears.find((y) => y.year === nextYear)?.groups?.[0] ?? "");
    setGeneralData(null);
  };

  const onYearChange = (value) => {
    setYear(value);
    setGroup(
      years.find((y) => y.year === value)?.groups?.[0] ?? ""
    );
    setGeneralData(null);
  };

  const handleLogout = async () => {
    await logout();
    setPersonalData(null);
    setModeOverride(null);
  };

  const scheduleForAgenda = mode === "personal" ? personalData : generalData;

  const handleExportPdf = () => {
    setExportError(null);
    try {
      exportSchedulePdf(scheduleForAgenda, { mode });
    } catch (e) {
      setExportError(e.message);
    }
  };
  return (
    <div className="horario-view">
      <div className="horario-toolbar">
        {onBack && (
          <button className="horario-back-btn" onClick={onBack}>
            ← Volver al asistente
          </button>
        )}

        {isAuthed ? (
          <div className="horario-session">
            <span className="horario-session-chip">
              🎓 {student?.carnet || "Sesión activa"}
            </span>
            <button className="horario-session-btn" onClick={handleLogout}>
              Cerrar sesión
            </button>
          </div>
        ) : (
          <button className="horario-session-btn primary" onClick={() => onLogin?.()}>
            🎓 Iniciar sesión con carné
          </button>
        )}
      </div>

      <section className="horario-hero">
        <h2 className="horario-hero-title">Horarios de clases</h2>
        <p className="horario-hero-text">
          Consulta tu horario personalizado o el horario general de una carrera
          en el modo demostración.
        </p>

        <div className="horario-mode" role="tablist" aria-label="Tipo de horario">
          {isAuthed && (
            <button
              type="button"
              role="tab"
              aria-selected={mode === "personal"}
              className={`horario-mode-btn${mode === "personal" ? " active" : ""}`}
              onClick={() => setModeOverride("personal")}
            >
              Mi horario
            </button>
          )}
          <button
            type="button"
            role="tab"
            aria-selected={mode === "general"}
            className={`horario-mode-btn${mode === "general" ? " active" : ""}`}
            onClick={() => setModeOverride("general")}
          >
            Consulta general (demostración)
          </button>
        </div>

        {mode === "general" && (
          <div className="horario-filters">
            <label className="horario-field">
              <span>Carrera</span>
              <select
                value={careerId}
                onChange={(e) => onCareerChange(Number(e.target.value))}
              >
                {careers.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} ({c.code})
                  </option>
                ))}
              </select>
            </label>

            <label className="horario-field">
              <span>Año</span>
              <select
                value={year}
                onChange={(e) => onYearChange(Number(e.target.value))}
              >
                {years.map((y) => (
                  <option key={y.year} value={y.year}>
                    {y.year}º año
                  </option>
                ))}
              </select>
            </label>

            <label className="horario-field">
              <span>Grupo</span>
              <select
                value={group}
                onChange={(e) => {
                  setGroup(e.target.value);
                  setGeneralData(null);
                }}
              >
                {groups.map((g) => (
                  <option key={g} value={g}>
                    Grupo {g}
                  </option>
                ))}
              </select>
            </label>
          </div>
        )}

        {mode === "personal" && personalData && (
          <p className="horario-context">{scheduleMeta(personalData)}</p>
        )}
        {mode === "general" && generalData && (
          <p className="horario-context">{scheduleMeta(generalData)}</p>
        )}
      </section>

      {scheduleForAgenda ? (
        <>
          <div className="horario-tabs" role="tablist" aria-label="Vista de horario">
            <button
              type="button"
              role="tab"
              aria-selected={tab === "hoy"}
              className={`horario-tab${tab === "hoy" ? " active" : ""}`}
              onClick={() => setTab("hoy")}
            >
              Hoy
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={tab === "semanal"}
              className={`horario-tab${tab === "semanal" ? " active" : ""}`}
              onClick={() => setTab("semanal")}
            >
              Semanal
            </button>
            <button
              type="button"
              className="horario-export-btn"
              onClick={handleExportPdf}
            >
              ⬇️ Exportar PDF
            </button>
          </div>

          {exportError ? (
            <p className="horario-empty error">{exportError}</p>
          ) : null}

          <Agenda
            schedule={scheduleForAgenda}
            tab={tab}
            dayFilter={dayFilter}
            onSelectDay={setDayFilter}
          />

          {mode === "general" && (
            <div className="horario-demo">
              <DemoBadge />
            </div>
          )}
        </>
      ) : mode === "personal" ? (
        <div className="horario-panel">
          {loadingPersonal ? (
            <p className="horario-empty">Cargando tu horario…</p>
          ) : personalError ? (
            <p className="horario-empty error">{personalError}</p>
          ) : (
            <p className="horario-empty">
              No hay un horario disponible para tu cuenta.
            </p>
          )}
        </div>
      ) : (
        <div className="horario-panel">
          {loadingGeneral ? (
            <p className="horario-empty">Cargando el horario…</p>
          ) : generalError ? (
            <p className="horario-empty error">{generalError}</p>
          ) : (
            <p className="horario-empty">
              Selecciona carrera, año y grupo para ver el horario.
            </p>
          )}
        </div>
      )}
    </div>
  );
}

export default HorarioView;
