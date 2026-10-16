const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

/**
 * Consulta los pagos y recibos del ESTUDIANTE AUTENTICADO.
 *
 * El endpoint requiere sesión (token Sanctum) y sólo devuelve los pagos de la
 * propia cuenta. Devuelve { student, summary, payments } o lanza un Error con
 * el mensaje enviado por el backend.
 */
export const consultarPagos = async (token) => {
  const response = await fetch(`${API_URL}/student-payments`, {
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(
      payload?.message || `No se pudo consultar los pagos (Error ${response.status}).`
    );
  }

  return payload.data;
};
