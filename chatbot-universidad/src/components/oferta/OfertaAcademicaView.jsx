import { useEffect, useMemo, useState } from "react";
import { getOfertaAcademica } from "../../services/ofertaService";
import "./OfertaAcademicaView.css";

/**
 * Pantalla de oferta académica: sedes y facultades.
 *
 * Muestra la misma información de dos formas:
 *   - Por facultad: facultad -> carreras -> sedes donde se ofrecen.
 *   - Por sede:     sede -> carreras (con su facultad).
 *
 * Incluye buscador global y filtros por facultad y sede.
 * (El listado de carreras con su matrícula es una pantalla aparte: CarrerasView.)
 */
function OfertaAcademicaView({ onBack, initialTab = "facultad" }) {
  const [careers, setCareers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [tab, setTab] = useState(initialTab === "sede" ? "sede" : "facultad");
  const [search, setSearch] = useState("");
  const [facultyFilter, setFacultyFilter] = useState("");
  const [sedeFilter, setSedeFilter] = useState("");

  useEffect(() => {
    let active = true;
    getOfertaAcademica()
      .then((data) => {
        if (active) setCareers(Array.isArray(data) ? data : []);
      })
      .catch((err) => {
        if (active) {
          setError(err.message || "No se pudo cargar la oferta académica.");
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

  // Listas para los filtros (a partir de los datos completos).
  const faculties = useMemo(() => {
    const set = new Set(careers.map((c) => c.faculty).filter(Boolean));
    return Array.from(set).sort((a, b) => a.localeCompare(b, "es"));
  }, [careers]);

  const sedes = useMemo(() => {
    const map = new Map();
    careers.forEach((c) =>
      (c.sedes || []).forEach((s) => {
        if (!map.has(s.id)) map.set(s.id, s);
      })
    );
    return Array.from(map.values()).sort(
      (a, b) =>
        (b.is_main ? 1 : 0) - (a.is_main ? 1 : 0) ||
        a.name.localeCompare(b.name, "es")
    );
  }, [careers]);

  // Aplicación de filtros (buscador + facultad + sede).
  const filteredCareers = useMemo(() => {
    const q = search.trim().toLowerCase();
    const sid = sedeFilter ? Number(sedeFilter) : null;

    return careers.filter((c) => {
      if (facultyFilter && c.faculty !== facultyFilter) return false;
      if (sid && !(c.sedes || []).some((s) => s.id === sid)) return false;
      if (q) {
        const haystack = [
          c.name,
          c.code,
          c.faculty,
          ...(c.sedes || []).map((s) => s.name),
        ]
          .join(" ")
          .toLowerCase();
        if (!haystack.includes(q)) return false;
      }
      return true;
    });
  }, [careers, facultyFilter, sedeFilter, search]);

  // Vista "Por facultad": agrupa carreras por facultad.
  const byFaculty = useMemo(() => {
    const map = new Map();
    filteredCareers.forEach((c) => {
      const key = c.faculty || "Sin facultad";
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(c);
    });
    return Array.from(map.entries())
      .map(([name, items]) => ({ name, items }))
      .sort((a, b) => a.name.localeCompare(b.name, "es"));
  }, [filteredCareers]);

  // Vista "Por sede": agrupa carreras por sede.
  const bySede = useMemo(() => {
    const sid = sedeFilter ? Number(sedeFilter) : null;
    const map = new Map();
    filteredCareers.forEach((c) => {
      (c.sedes || []).forEach((s) => {
        if (sid && s.id !== sid) return;
        if (!map.has(s.id)) map.set(s.id, { sede: s, careers: [] });
        map.get(s.id).careers.push(c);
      });
    });
    return Array.from(map.values()).sort(
      (a, b) =>
        (b.sede.is_main ? 1 : 0) - (a.sede.is_main ? 1 : 0) ||
        a.sede.name.localeCompare(b.sede.name, "es")
    );
  }, [filteredCareers, sedeFilter]);

  const activeSedeName = useMemo(
    () => sedes.find((s) => String(s.id) === String(sedeFilter))?.name || "",
    [sedes, sedeFilter]
  );

  const hasFilters = !!(search.trim() || facultyFilter || sedeFilter);

  return (
    <div className="oferta-view">
      <div className="oferta-toolbar">
        <div className="oferta-filters">
          {onBack && (
            <button className="oferta-back-btn" onClick={onBack}>
              ← Volver al asistente
            </button>
          )}

          <input
            className="oferta-search"
            type="search"
            placeholder="Buscar carrera, facultad o sede…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            aria-label="Buscar carrera, facultad o sede"
          />

          <select
            className="oferta-select"
            value={facultyFilter}
            onChange={(e) => setFacultyFilter(e.target.value)}
            aria-label="Filtrar por facultad"
          >
            <option value="">Todas las facultades</option>
            {faculties.map((f) => (
              <option key={f} value={f}>
                {f}
              </option>
            ))}
          </select>

          <select
            className="oferta-select"
            value={sedeFilter}
            onChange={(e) => setSedeFilter(e.target.value)}
            aria-label="Filtrar por sede"
          >
            <option value="">Todas las sedes</option>
            {sedes.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
        </div>

        {loading && <span className="oferta-loading">Cargando…</span>}
      </div>

      {error && <div className="oferta-error">{error}</div>}

      <div className="oferta-summary">
        <div className="oferta-tabs" role="tablist" aria-label="Vista de la oferta">
          <button
            role="tab"
            aria-selected={tab === "facultad"}
            className={"oferta-tab" + (tab === "facultad" ? " active" : "")}
            onClick={() => setTab("facultad")}
          >
            🏛️ Por facultad
          </button>
          <button
            role="tab"
            aria-selected={tab === "sede"}
            className={"oferta-tab" + (tab === "sede" ? " active" : "")}
            onClick={() => setTab("sede")}
          >
            🏫 Por sede
          </button>
        </div>

        <span className="oferta-counts">
          🎓 {careers.length} carreras · 🏛️ {faculties.length} facultades · 🏫{" "}
          {sedes.length} sedes
          {hasFilters &&
            ` · ${filteredCareers.length} ${
              filteredCareers.length === 1 ? "resultado" : "resultados"
            }`}
        </span>
      </div>

      {tab === "facultad" && (
        <div className="oferta-faculty-grid">
          {byFaculty.map((f) => (
            <section key={f.name} className="oferta-faculty">
              <header className="oferta-faculty-header">
                <h3>{f.name}</h3>
                <span className="oferta-faculty-count">
                  {f.items.length}{" "}
                  {f.items.length === 1 ? "carrera" : "carreras"}
                </span>
              </header>

              <ul className="oferta-career-list">
                {f.items.map((c) => (
                  <li key={c.id} className="oferta-career">
                    <div className="oferta-career-main">
                      <span className="oferta-career-name">{c.name}</span>
                      <span className="oferta-career-meta">
                        {c.duration_years} años
                      </span>
                    </div>

                    <div className="oferta-sede-chips">
                      {(c.sedes || []).map((s) => (
                        <span
                          key={s.id}
                          className={
                            "oferta-chip" +
                            (activeSedeName && s.name === activeSedeName
                              ? " highlight"
                              : "") +
                            (s.is_main ? " main" : "")
                          }
                          title={s.address}
                        >
                          {s.name.replace(/^CUR\s+/, "")}
                        </span>
                      ))}
                    </div>
                  </li>
                ))}
              </ul>
            </section>
          ))}

          {!loading && !error && byFaculty.length === 0 && (
            <p className="oferta-empty">No se encontraron carreras con ese criterio.</p>
          )}
        </div>
      )}

      {tab === "sede" && (
        <div className="oferta-sede-grid">
          {bySede.map(({ sede, careers: sedeCareers }) => (
            <article
              key={sede.id}
              className={"oferta-sede-card" + (sede.is_main ? " is-main" : "")}
            >
              <header className="oferta-sede-header">
                <h3>{sede.name}</h3>
                {sede.is_main && (
                  <span className="oferta-main-badge">Sede principal</span>
                )}
              </header>

              <p className="oferta-sede-address">
                📍 {sede.address}
                {sede.city ? ` (${sede.city})` : ""}
              </p>

              <p className="oferta-sede-count">
                🎓 {sedeCareers.length}{" "}
                {sedeCareers.length === 1 ? "carrera" : "carreras"}
              </p>

              <div className="oferta-career-chips">
                {sedeCareers.map((c) => (
                  <span
                    key={c.id}
                    className={
                      "oferta-career-chip" +
                      (facultyFilter && c.faculty === facultyFilter
                        ? " highlight"
                        : "")
                    }
                    title={c.faculty}
                  >
                    {c.name}
                  </span>
                ))}
              </div>
            </article>
          ))}

          {!loading && !error && bySede.length === 0 && (
            <p className="oferta-empty">No se encontraron sedes con ese criterio.</p>
          )}
        </div>
      )}
    </div>
  );
}

export default OfertaAcademicaView;
