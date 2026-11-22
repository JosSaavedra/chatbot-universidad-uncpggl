const API_URL = (import.meta.env && import.meta.env.VITE_API_URL)
  || "http://localhost:8000/api";

async function readJson(response, fallback) {
  if (response.ok) return response.json();
  let message = `No se pudo ${fallback}. Código ${response.status}.`;
  try {
    const payload = await response.json();
    if (payload?.message) message = payload.message;
  } catch { /* Respuesta sin JSON: se mantiene el mensaje genérico. */ }
  throw new Error(message);
}

export function isAbsoluteQrUrl(value) {
  if (typeof value !== "string") return false;
  try {
    const url = new URL(value);
    return ["http:", "https:"].includes(url.protocol) && Boolean(url.hostname);
  } catch {
    return false;
  }
}

/** React envía sólo el carné. La identidad Google la valida Laravel. */
export function requestQr(carnet) {
  return fetch(`${API_URL}/qr-auth/request`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ carnet }),
  }).then(async (response) => {
    const request = await readJson(response, "generar el código QR");
    if (!isAbsoluteQrUrl(request?.qr_url)) {
      throw new Error("El servidor no devolvió una URL válida para escanear el QR.");
    }
    return { ...request, carnet };
  });
}

export function getQrStatus(requestId, clientNonce) {
  return fetch(`${API_URL}/qr-auth/status/${requestId}`, {
    headers: { "X-Approval-Nonce": clientNonce, Accept: "application/json" },
  }).then((response) => readJson(response, "consultar la autorización"));
}

export function exchangeQr(requestId, clientNonce) {
  return fetch(`${API_URL}/qr-auth/exchange`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-Approval-Nonce": clientNonce,
    },
    body: JSON.stringify({ request_id: requestId }),
  }).then((response) => readJson(response, "completar el ingreso"));
}

export function cancelQr(requestId, clientNonce) {
  return fetch(`${API_URL}/qr-auth/cancel`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-Approval-Nonce": clientNonce,
    },
    body: JSON.stringify({ request_id: requestId }),
  }).then((response) => readJson(response, "cancelar la solicitud"));
}
