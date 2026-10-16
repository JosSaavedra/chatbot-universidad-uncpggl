const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

/**
 * Obtiene feriados de Nicaragua + eventos universitarios en un rango de fechas.
 * @param {string} start - Fecha ISO (YYYY-MM-DD)
 * @param {string} end - Fecha ISO (YYYY-MM-DD)
 * @param {{ careerId?: number, category?: string }} filtros
 */
export const getCalendar = async (start, end, { careerId, category } = {}) => {
  const params = new URLSearchParams({ start, end });
  if (careerId) params.append("career_id", careerId);
  if (category) params.append("category", category);

  const response = await fetch(`${API_URL}/calendar?${params.toString()}`, {
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({}));
    throw new Error(err?.message || `Error ${response.status}`);
  }

  return response.json();
};

/**
 * Obtiene las categorías disponibles con etiqueta y color.
 */
export const getCategories = async () => {
  const response = await fetch(`${API_URL}/university-events/categories`, {
    headers: { Accept: "application/json" },
  });

  if (!response.ok) return [];
  return response.json();
};

/**
 * Obtiene las carreras activas para el filtro.
 */
export const getCareers = async () => {
  const response = await fetch(`${API_URL}/careers`, {
    headers: { Accept: "application/json" },
  });

  if (!response.ok) return [];
  const data = await response.json();
  return Array.isArray(data) ? data : data.data || [];
};
