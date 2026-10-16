const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

/**
 * Consulta las calificaciones del ESTUDIANTE AUTENTICADO.
 *
 * El endpoint requiere sesión (token Sanctum) y obtiene el usuario únicamente
 * del token: nunca permite pedir las notas de otro estudiante. Devuelve
 * { student, summary, periods } o lanza un Error con el mensaje del backend.
 * El error incluye `status` para distinguir una sesión expirada (401).
 */
export const fetchMyGrades = async (token) => {
  const response = await fetch(`${API_URL}/my-grades`, {
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(
      payload?.message || `No se pudieron consultar tus notas (Error ${response.status}).`
    );
    error.status = response.status;
    throw error;
  }

  return payload.data;
};
