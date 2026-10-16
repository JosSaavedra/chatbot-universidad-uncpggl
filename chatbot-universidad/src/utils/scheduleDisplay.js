const DAY_SHORT = {
  Lunes: "Lun",
  Martes: "Mar",
  Miércoles: "Mié",
  Jueves: "Jue",
  Viernes: "Vie",
  Sábado: "Sáb",
  Domingo: "Dom",
};

export const dayShort = (day) => DAY_SHORT[day] || day || "";

export const timeRange = (item) =>
  item ? `${item.start_time} - ${item.end_time}` : "";

/**
 * Línea de contexto del horario: carrera · año · grupo.
 */
export const scheduleMeta = (schedule) => {
  const parts = [];
  if (schedule?.career) parts.push(schedule.career);
  if (schedule?.year) parts.push(`${schedule.year}º año`);
  if (schedule?.group) parts.push(`Grupo ${schedule.group}`);
  return parts.join(" · ");
};

export const emptyScheduleMessage = (query) => {
  switch (query?.mode) {
    case "subject":
      return "No encontré esa asignatura en el horario.";
    case "day":
      return `No hay clases el ${query.day}.`;
    case "today":
      return "No hay clases para hoy.";
    default:
      return "No hay clases registradas.";
  }
};

/**
 * Selección (carrera/año/grupo) para abrir la vista completa de horarios.
 */
export const selectionFromSchedule = (schedule) => ({
  careerId: schedule?.career_id ?? null,
  year: schedule?.year ?? null,
  group: schedule?.group ?? null,
});
