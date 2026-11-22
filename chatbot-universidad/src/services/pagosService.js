import { sessionFetch } from "./authService";

const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

/**
 * Consulta los pagos y recibos del ESTUDIANTE AUTENTICADO.
 *
 * El endpoint requiere sesión (token Sanctum) y sólo devuelve los pagos de la
 * propia cuenta. Devuelve { student, summary, payments } o lanza un Error con
 * el mensaje enviado por el backend.
 */
export const consultarPagos = async (token) => {
  return loadPaymentData("/student-payments", token);
};

export const consultarRecibo = (id, token) =>
  loadPaymentData(`/student-payments/${encodeURIComponent(id)}/receipt`, token);

async function loadPaymentData(path, token) {
  const response = await sessionFetch(`${API_URL}${path}`, {
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
}
