const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

/**
 * Obtiene todas las carreras activas con la información de las sedes donde se
 * imparten (facultad, duración, costos y lista completa de sedes).
 *
 * Con esto se construyen las vistas "Por facultad" y "Por sede" sin necesidad
 * de más llamadas al servidor.
 */
export const getOfertaAcademica = async () => {
  const response = await fetch(`${API_URL}/careers`, {
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({}));
    throw new Error(err?.message || `Error ${response.status}`);
  }

  const data = await response.json();
  return Array.isArray(data) ? data : data.data || [];
};
