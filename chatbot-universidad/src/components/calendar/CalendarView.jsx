import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import FullCalendar from "@fullcalendar/react";
import dayGridPlugin from "@fullcalendar/daygrid";
import interactionPlugin from "@fullcalendar/interaction";
import listPlugin from "@fullcalendar/list";
import esLocale from "@fullcalendar/core/locales/es";

import { getCalendar, getCategories, getCareers } from "../../services/calendarService";
import "./CalendarView.css";

const toDateStr = (date) => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
};

const formatDateTime = (iso) => {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  return date.toLocaleString("es-NI", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatDateOnly = (iso) => {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  return date.toLocaleDateString("es-NI", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  });
};

function CalendarView({ onBack }) {
  const calendarRef = useRef(null);

  const [events, setEvents] = useState([]);
  const [categories, setCategories] = useState([]);
  const [careers, setCareers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [categoryFilter, setCategoryFilter] = useState("");
  const [careerFilter, setCareerFilter] = useState("");
  const [selectedEvent, setSelectedEvent] = useState(null);

  // Carga inicial de catálogos (categorías y carreras).
  useEffect(() => {
    let active = true;
    Promise.all([getCategories(), getCareers()])
      .then(([cats, cars]) => {
        if (!active) return;
        setCategories(Array.isArray(cats) ? cats : []);
        setCareers(Array.isArray(cars) ? cars : []);
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, []);

  const fetchRange = useCallback(
    async (start, end) => {
      setLoading(true);
      setError(null);
      try {
        const data = await getCalendar(start, end, {
          category: categoryFilter || undefined,
          careerId: careerFilter || undefined,
        });

        const mapped = (data.events || []).map((ev) => ({
          id: `evt_${ev.id}`,
          title: ev.title,
          start: ev.start,
          end: ev.end,
          allDay: ev.allDay,
          backgroundColor: ev.color,
          borderColor: ev.color,
          textColor: "#ffffff",
          extendedProps: { ...ev, sourceType: "event" },
        }));

        const holidays = (data.holidays || []).map((h) => ({
          id: h.id,
          title: h.title,
          start: h.start,
          end: h.end,
          allDay: true,
          backgroundColor: h.color,
          borderColor: h.color,
          textColor: "#ffffff",
          extendedProps: { ...h, sourceType: "holiday" },
        }));

        setEvents([...holidays, ...mapped]);
      } catch (err) {
        setError(err.message || "No se pudo cargar el calendario.");
        setEvents([]);
      } finally {
        setLoading(false);
      }
    },
    [categoryFilter, careerFilter]
  );

  const handleDatesSet = useCallback(
    (arg) => {
      // arg.start / arg.end cubren el rango visible (incluye días de meses vecinos).
      fetchRange(toDateStr(arg.start), toDateStr(new Date(arg.end.getTime() - 86400000)));
    },
    [fetchRange]
  );

  // Re-consultar al cambiar los filtros.
  useEffect(() => {
    const api = calendarRef.current?.getApi();
    if (!api) return;
    const view = api.view;
    fetchRange(toDateStr(view.activeStart), toDateStr(new Date(view.activeEnd.getTime() - 86400000)));
  }, [fetchRange]);

  const handleEventClick = useCallback((info) => {
    info.jsEvent.preventDefault();
    setSelectedEvent(info.event);
  }, []);

  const legend = useMemo(() => categories, [categories]);

  return (
    <div className="calendar-view">
      <div className="calendar-toolbar">
        <div className="calendar-filters">
          {onBack && (
            <button className="calendar-back-btn" onClick={onBack}>
              ← Volver al asistente
            </button>
          )}
          <select
            className="calendar-select"
            value={categoryFilter}
            onChange={(e) => setCategoryFilter(e.target.value)}
            aria-label="Filtrar por categoría"
          >
            <option value="">Todas las categorías</option>
            {categories.map((c) => (
              <option key={c.value} value={c.value}>
                {c.label}
              </option>
            ))}
          </select>

          <select
            className="calendar-select"
            value={careerFilter}
            onChange={(e) => setCareerFilter(e.target.value)}
            aria-label="Filtrar por carrera"
          >
            <option value="">Todas las carreras</option>
            {careers.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </div>

        {loading && <span className="calendar-loading">Cargando…</span>}
      </div>

      {error && <div className="calendar-error">{error}</div>}

      <div className="calendar-legend">
        {legend.map((c) => (
          <span key={c.value} className="legend-item">
            <span className="legend-dot" style={{ background: c.color }} />
            {c.label}
          </span>
        ))}
      </div>

      <div className="calendar-body">
        <FullCalendar
          ref={calendarRef}
          plugins={[dayGridPlugin, interactionPlugin, listPlugin]}
          initialView="dayGridMonth"
          locale={esLocale}
          headerToolbar={{
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,listMonth",
          }}
          buttonText={{ today: "Hoy", month: "Mes", list: "Lista" }}
          height="auto"
          dayMaxEvents={3}
          events={events}
          datesSet={handleDatesSet}
          eventClick={handleEventClick}
          eventDisplay="block"
          fixedWeekCount={false}
        />
      </div>

      {selectedEvent && (
        <div className="event-modal-backdrop" onClick={() => setSelectedEvent(null)}>
          <div className="event-modal" onClick={(e) => e.stopPropagation()}>
            <button
              className="event-modal-close"
              onClick={() => setSelectedEvent(null)}
              aria-label="Cerrar"
            >
              ×
            </button>

            <span
              className="event-category-badge"
              style={{ background: selectedEvent.backgroundColor || selectedEvent.borderColor }}
            >
              {selectedEvent.extendedProps?.category_label ||
                selectedEvent.extendedProps?.category}
            </span>

            <h3 className="event-modal-title">{selectedEvent.title}</h3>

            <ul className="event-modal-details">
              <li>
                <strong>Inicio:</strong>{" "}
                {selectedEvent.allDay
                  ? formatDateOnly(selectedEvent.start)
                  : formatDateTime(selectedEvent.start)}
              </li>
              {selectedEvent.end && selectedEvent.end !== selectedEvent.start && (
                <li>
                  <strong>Fin:</strong>{" "}
                  {selectedEvent.allDay
                    ? formatDateOnly(selectedEvent.end)
                    : formatDateTime(selectedEvent.end)}
                </li>
              )}
              {selectedEvent.extendedProps?.career?.name && (
                <li>
                  <strong>Carrera:</strong> {selectedEvent.extendedProps.career.name}
                </li>
              )}
              {selectedEvent.extendedProps?.campus && (
                <li>
                  <strong>Sede:</strong> {selectedEvent.extendedProps.campus}
                </li>
              )}
              {selectedEvent.extendedProps?.academic_period && (
                <li>
                  <strong>Período:</strong> {selectedEvent.extendedProps.academic_period}
                </li>
              )}
              {selectedEvent.extendedProps?.location && (
                <li>
                  <strong>Lugar:</strong> {selectedEvent.extendedProps.location}
                </li>
              )}
              {selectedEvent.extendedProps?.organizer && (
                <li>
                  <strong>Organiza:</strong> {selectedEvent.extendedProps.organizer}
                </li>
              )}
            </ul>

            {selectedEvent.extendedProps?.description && (
              <p className="event-modal-description">
                {selectedEvent.extendedProps.description}
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default CalendarView;
