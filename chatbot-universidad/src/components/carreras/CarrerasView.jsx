import { useEffect, useMemo, useRef, useState } from "react";
import { getOfertaAcademica } from "../../services/ofertaService";
import CareerIcon from "./careerIcons";
import "./CarrerasView.css";

const PENDING = "Información pendiente de publicación";

const hasText = (value) => typeof value === "string" && value.trim().length > 0;

const formatDuration = (years) => {
  if (!years && years !== 0) return PENDING;
  return `${years} ${years === 1 ? "año" : "años"}`;
};

const formatTime = (value) => (hasText(value) ? value.slice(0, 5) : "");

/** Pestañas del panel de detalle. */
const TABS = [
  { id: "general", label: "Información general" },
  { id: "plan", label: "Plan de estudios" },
  { id: "campo", label: "Campo laboral" },
  { id: "horarios", label: "Horarios" },
];

/**
 * Agrupa las materias por año académico conservando el orden recibido
 * desde el backend (year → period → name).
 */
const groupSubjects = (subjects = []) => {
  const groups = new Map();
  subjects.forEach((subject) => {
    const year = subject?.year ?? "—";
    if (!groups.has(year)) groups.set(year, []);
    groups.get(year).push(subject);
  });
  return [...groups.entries()];
};

/**
 * Agrupa los horarios por año de estudio.
 */
const groupSchedules = (schedules = []) => {
  const groups = new Map();
  schedules.forEach((schedule) => {
    const year = schedule?.year_of_study ?? "—";
    if (!groups.has(year)) groups.set(year, []);
    groups.get(year).push(schedule);
  });
  return [...groups.entries()];
};

/**
 * Pantalla independiente "Ver carreras".
 *
 * Muestra las carreras reales registradas en la base de datos como tarjetas
 * y permite abrir una ficha detallada en un panel lateral con pestañas. Todos
 * los datos provienen de la API; cuando un dato no existe se indica que está
 * pendiente de publicación (nunca se inventa información académica desde el
 * frontend).
 */
function CarrerasView({ onBack, onAskAssistant, initialCareerId = null }) {
  const [careers, setCareers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [search, setSearch] = useState("");
  const [sedeFilter, setSedeFilter] = useState("");
  const [modalidadFilter, setModalidadFilter] = useState("");

  const [selectedCareer, setSelectedCareer] = useState(null);
  const [tab, setTab] = useState("general");

  // Id de carrera solicitado desde el chat (p. ej. al pulsar una tarjeta).
  const initialCareerIdRef = useRef(initialCareerId);
  const panelBodyRef = useRef(null);

  useEffect(() => {
    let active = true;
    getOfertaAcademica()
      .then((data) => {
        if (!active) return;
        const list = Array.isArray(data) ? data : [];
        setCareers(list);

        const requestedId = initialCareerIdRef.current;
        if (requestedId != null) {
          const found = list.find(
            (c) => String(c.id) === String(requestedId)
          );
          if (found) setSelectedCareer(found);
        }
      })
      .catch((err) => {
        if (active) {
          setError(err.message || "No se pudieron cargar las carreras.");
          setCareers([]);
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, []);

  // Al cambiar de pestaña o de carrera, volver al inicio del contenido.
  useEffect(() => {
    if (panelBodyRef.current) panelBodyRef.current.scrollTop = 0;
  }, [tab, selectedCareer?.id]);

  const sedeOptions = useMemo(
    () =>
      [
        ...new Set(
          careers.flatMap((c) => (c.sedes || []).map((s) => s.name).filter(hasText))
        ),
      ].sort((a, b) => a.localeCompare(b)),
    [careers]
  );

  // El filtro por modalidad solo se muestra si existe el dato en la base.
  const modalidadOptions = useMemo(
    () => [...new Set(careers.map((c) => c.modalidad).filter(hasText))].sort(),
    [careers]
  );

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return careers.filter((c) => {
      if (
        sedeFilter &&
        !(c.sedes || []).some((s) => s.name === sedeFilter)
      ) {
        return false;
      }
      if (modalidadFilter && c.modalidad !== modalidadFilter) return false;
      if (!q) return true;
      const haystack = [
        c.name,
        c.faculty,
        c.description,
        ...(c.sedes || []).map((s) => s.name),
      ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();
      return haystack.includes(q);
    });
  }, [careers, search, sedeFilter, modalidadFilter]);

  const openDetail = (career) => {
    setSelectedCareer(career);
    setTab("general");
  };

  const closeDetail = () => setSelectedCareer(null);

  const handlePensum = (career) => {
    if (hasText(career?.pensum_url)) {
      window.open(career.pensum_url, "_blank", "noopener,noreferrer");
      return;
    }
    setTab("plan");
  };

  const askAssistant = (career) => {
    if (typeof onAskAssistant === "function") {
      onAskAssistant(
        `Quiero información sobre la carrera de ${career.name}`
      );
    }
  };

  const subjects = selectedCareer?.subjects || [];
  const schedules = selectedCareer?.schedules || [];
  const hasSubjects = subjects.length > 0;
  const hasSchedules = schedules.length > 0;

  return (
    <div className="carreras-view">
      <div className="carreras-toolbar">
        {onBack && (
          <button className="carreras-back-btn" onClick={onBack}>
            ← Volver al asistente
          </button>
        )}

        <input
          className="carreras-search"
          type="search"
          placeholder="Buscar carrera…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          aria-label="Buscar carrera"
        />

        {sedeOptions.length > 0 && (
          <select
            className="carreras-filter"
            value={sedeFilter}
            onChange={(e) => setSedeFilter(e.target.value)}
            aria-label="Filtrar por sede"
          >
            <option value="">Todas las sedes</option>
            {sedeOptions.map((sede) => (
              <option key={sede} value={sede}>
                {sede}
              </option>
            ))}
          </select>
        )}

        {modalidadOptions.length > 0 && (
          <select
            className="carreras-filter"
            value={modalidadFilter}
            onChange={(e) => setModalidadFilter(e.target.value)}
            aria-label="Filtrar por modalidad"
          >
            <option value="">Todas las modalidades</option>
            {modalidadOptions.map((modalidad) => (
              <option key={modalidad} value={modalidad}>
                {modalidad}
              </option>
            ))}
          </select>
        )}

        <span className="carreras-count">
          {filtered.length} {filtered.length === 1 ? "carrera" : "carreras"}
        </span>

        {loading && <span className="carreras-loading">Cargando…</span>}
      </div>

      {error && <div className="carreras-error">{error}</div>}

      <div className="carreras-list">
        {filtered.map((c) => (
          <article key={c.id} className="carrera-card">
            <div className="carrera-card-media">
              {hasText(c.imagen_url) ? (
                <img
                  src={c.imagen_url}
                  alt={`Imagen de ${c.name}`}
                  className="carrera-card-img"
                  loading="lazy"
                />
              ) : (
                <CareerIcon career={c} className="carrera-card-icon" />
              )}
            </div>

            <div className="carrera-card-body">
              <h3 className="carrera-titulo">{c.name}</h3>
              {hasText(c.faculty) && (
                <span className="carrera-facultad">{c.faculty}</span>
              )}

              <div className="carrera-card-meta">
                <span className="carrera-chip">
                  {formatDuration(c.duration_years)}
                </span>
                {hasText(c.modalidad) && (
                  <span className="carrera-chip">{c.modalidad}</span>
                )}
                {(c.sedes || []).length > 0 && (
                  <span className="carrera-chip">
                    {(c.sedes || []).length === 1
                      ? c.sedes[0].name
                      : `${(c.sedes || []).length} sedes`}
                  </span>
                )}
              </div>

              <p className="carrera-card-desc">
                {hasText(c.description) ? c.description : PENDING}
              </p>
            </div>

            <button
              className="carrera-detalle-btn"
              onClick={() => openDetail(c)}
            >
              Ver detalles
            </button>
          </article>
        ))}

        {!loading && !error && filtered.length === 0 && (
          <p className="carreras-empty">
            No se encontraron carreras con ese criterio.
          </p>
        )}
      </div>

      {/* ---- Panel lateral de detalle ---- */}
      <div
        className={`carrera-panel-overlay${selectedCareer ? " open" : ""}`}
        onClick={closeDetail}
      >
        {selectedCareer && (
          <aside
            className="carrera-panel"
            role="dialog"
            aria-modal="true"
            aria-label={`Detalle de ${selectedCareer.name}`}
            onClick={(e) => e.stopPropagation()}
          >
            <header className="carrera-panel-header">
              <div className="carrera-panel-media">
                {hasText(selectedCareer.imagen_url) ? (
                  <img
                    src={selectedCareer.imagen_url}
                    alt={`Imagen de ${selectedCareer.name}`}
                    className="carrera-panel-img"
                  />
                ) : (
                  <CareerIcon
                    career={selectedCareer}
                    className="carrera-panel-icon"
                  />
                )}
              </div>

              <div className="carrera-panel-title">
                <h2>{selectedCareer.name}</h2>
                <p>
                  {hasText(selectedCareer.faculty)
                    ? selectedCareer.faculty
                    : "Facultad pendiente de publicación"}
                  {hasText(selectedCareer.code)
                    ? ` · ${selectedCareer.code}`
                    : ""}
                </p>
              </div>

              <button
                className="carrera-panel-close"
                onClick={closeDetail}
                aria-label="Cerrar detalle"
              >
                ✕
              </button>
            </header>

            <div className="carrera-tabs" role="tablist" aria-label="Secciones de la carrera">
              {TABS.map((t) => (
                <button
                  key={t.id}
                  type="button"
                  role="tab"
                  id={`carrera-tab-${t.id}`}
                  aria-selected={tab === t.id}
                  aria-controls="carrera-tabpanel"
                  className={`carrera-tab${tab === t.id ? " active" : ""}`}
                  onClick={() => setTab(t.id)}
                >
                  {t.label}
                </button>
              ))}
            </div>

            <div
              className="carrera-panel-body"
              role="tabpanel"
              id="carrera-tabpanel"
              aria-labelledby={`carrera-tab-${tab}`}
              ref={panelBodyRef}
            >
              <section className="carrera-resumen" aria-label="Resumen de la carrera">
                <h3 className="carrera-resumen-title">Resumen</h3>
                <div className="carrera-fact">
                  <span className="carrera-fact-label">Duración</span>
                  <span className="carrera-fact-value">
                    {formatDuration(selectedCareer.duration_years)}
                  </span>
                </div>
                <div className="carrera-fact">
                  <span className="carrera-fact-label">Modalidad</span>
                  <span className="carrera-fact-value">
                    {hasText(selectedCareer.modalidad)
                      ? selectedCareer.modalidad
                      : PENDING}
                  </span>
                </div>
                <div className="carrera-fact">
                  <span className="carrera-fact-label">Título que otorga</span>
                  <span className="carrera-fact-value">
                    {hasText(selectedCareer.titulo_otorga)
                      ? selectedCareer.titulo_otorga
                      : PENDING}
                  </span>
                </div>
                <div className="carrera-fact">
                  <span className="carrera-fact-label">Sede</span>
                  <span className="carrera-fact-value">
                    {(selectedCareer.sedes || []).length > 0
                      ? selectedCareer.sedes.map((s) => s.name).join(", ")
                      : PENDING}
                  </span>
                </div>
              </section>

              {tab === "general" && (
                <>
                  <section className="carrera-panel-section">
                    <h3>Descripción</h3>
                    <p>
                      {hasText(selectedCareer.description)
                        ? selectedCareer.description
                        : PENDING}
                    </p>
                  </section>

                  <section className="carrera-panel-section">
                    <h3>Perfil profesional</h3>
                    <p>
                      {hasText(selectedCareer.perfil_profesional)
                        ? selectedCareer.perfil_profesional
                        : PENDING}
                    </p>
                  </section>

                  <section className="carrera-panel-section">
                    <h3>Requisitos de ingreso</h3>
                    <p>
                      {hasText(selectedCareer.requisitos_ingreso)
                        ? selectedCareer.requisitos_ingreso
                        : PENDING}
                    </p>
                  </section>
                </>
              )}

              {tab === "plan" && (
                <section className="carrera-panel-section">
                  <h3>Plan de estudios</h3>

                  {hasText(selectedCareer.pensum_url) && (
                    <button
                      type="button"
                      className="carrera-inline-link"
                      onClick={() => handlePensum(selectedCareer)}
                    >
                      Abrir pensum publicado ↗
                    </button>
                  )}

                  {hasSubjects ? (
                    groupSubjects(subjects).map(([year, items]) => (
                      <div key={year} className="carrera-year-block">
                        <h4 className="carrera-year-title">{year}º año</h4>
                        <ul className="carrera-subject-list">
                          {items.map((subject) => (
                            <li key={subject.id} className="carrera-subject">
                              <span className="carrera-subject-name">
                                {subject.name}
                              </span>
                              <span className="carrera-subject-meta">
                                {[
                                  hasText(subject.period) ? subject.period : null,
                                  subject.credits
                                    ? `${subject.credits} créditos`
                                    : null,
                                ]
                                  .filter(Boolean)
                                  .join(" · ")}
                              </span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    ))
                  ) : (
                    <p className="carrera-pendiente">{PENDING}</p>
                  )}
                </section>
              )}

              {tab === "campo" && (
                <section className="carrera-panel-section">
                  <h3>Campo laboral</h3>
                  <p>
                    {hasText(selectedCareer.campo_laboral)
                      ? selectedCareer.campo_laboral
                      : PENDING}
                  </p>
                </section>
              )}

              {tab === "horarios" && (
                <section className="carrera-panel-section">
                  <h3>Horarios</h3>
                  {hasSchedules ? (
                    groupSchedules(schedules).map(([year, items]) => (
                      <div key={year} className="carrera-year-block">
                        <h4 className="carrera-year-title">{year}º año</h4>
                        <ul className="carrera-schedule-list">
                          {items.map((schedule) => (
                            <li key={schedule.id} className="carrera-schedule">
                              <span className="carrera-schedule-subject">
                                {schedule.subject_name}
                              </span>
                              <span className="carrera-schedule-meta">
                                {schedule.day_of_week}{" "}
                                {formatTime(schedule.start_time)}–
                                {formatTime(schedule.end_time)}
                              </span>
                              <span className="carrera-schedule-meta">
                                {[schedule.classroom, schedule.teacher_name]
                                  .filter(hasText)
                                  .join(" · ")}
                              </span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    ))
                  ) : (
                    <p className="carrera-pendiente">{PENDING}</p>
                  )}
                </section>
              )}
            </div>

            <footer className="carrera-panel-actions">
              <button
                className="carrera-action-btn"
                onClick={() => handlePensum(selectedCareer)}
              >
                Ver pensum
              </button>
              <button
                className="carrera-action-btn"
                onClick={() => setTab("plan")}
              >
                Consultar materias
              </button>
              <button
                className="carrera-action-btn"
                onClick={() => setTab("horarios")}
              >
                Consultar horarios
              </button>
              <button
                className="carrera-action-btn primary"
                onClick={() => askAssistant(selectedCareer)}
              >
                Preguntar al asistente
              </button>
              <button
                className="carrera-action-btn ghost"
                onClick={closeDetail}
              >
                Volver a carreras
              </button>
            </footer>
          </aside>
        )}
      </div>
    </div>
  );
}

export default CarrerasView;
