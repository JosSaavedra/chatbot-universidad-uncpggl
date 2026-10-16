const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

const readJson = async (response) => {
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(
      payload?.message || `No se pudo consultar el horario (Error ${response.status}).`
    );
  }
  return payload.data;
};

/**
 * Horario personalizado del estudiante autenticado (requiere token).
 */
export const fetchMySchedule = async (token) =>
  readJson(
    await fetch(`${API_URL}/my-schedule`, {
      headers: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    })
  );

/**
 * Catálogo público (modo demostración): carreras, años y grupos con horarios.
 */
export const fetchScheduleOptions = async () =>
  readJson(
    await fetch(`${API_URL}/schedules/options`, {
      headers: { Accept: "application/json" },
    })
  );

/**
 * Horario general público por carrera, año y grupo.
 */
export const fetchGeneralSchedule = async ({ careerId, year, group }) => {
  const params = new URLSearchParams({
    career_id: String(careerId),
    year: String(year),
  });
  if (group) {
    params.set("group", group);
  }

  return readJson(
    await fetch(`${API_URL}/schedules/general?${params.toString()}`, {
      headers: { Accept: "application/json" },
    })
  );
};
