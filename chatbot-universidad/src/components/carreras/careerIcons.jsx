import { createElement, Fragment } from "react";

/**
 * Iconos por disciplina para las carreras.
 *
 * Se usan cuando la carrera NO tiene una imagen (`imagen_url`) cargada, en
 * lugar de repetir el mismo ícono de graduación para todas. Están dibujados
 * como trazos simples (un solo color, heredado de `currentColor`) para
 * mantener una interfaz universitaria limpia y coherente con el tema oscuro.
 */

const ICONS = {
  // Salud / medicina / enfermería: línea de latido.
  salud: [
    "M3 12.5h3.4l2.1-5.2 3.1 10.4 2.4-5.2H21",
  ],
  // Nutrición y dietética: hoja / alimento saludable.
  nutricion: [
    "M20 4C11 4 4 9 4 17c0 1 .1 2 .4 3C12 20 20 13 20 4z",
    "M20 4c-3.5 6-8.5 10-15.5 13",
  ],
  // Medicina veterinaria: huella.
  veterinaria: [
    "M6.6 10.2a1.9 1.9 0 1 0 0-3.8 1.9 1.9 0 0 0 0 3.8z",
    "M10.8 7a1.9 1.9 0 1 0 0-3.8 1.9 1.9 0 0 0 0 3.8z",
    "M15.6 7a1.9 1.9 0 1 0 0-3.8 1.9 1.9 0 0 0 0 3.8z",
    "M19.2 10.2a1.9 1.9 0 1 0 0-3.8 1.9 1.9 0 0 0 0 3.8z",
    "M12.6 20.2c-2.7 0-4.7-1.8-4.7-4 0-2.1 2-3.3 4.7-3.3s4.7 1.2 4.7 3.3c0 2.2-2 4-4.7 4z",
  ],
  // Ingeniería civil: puente.
  civil: [
    "M3 19h18",
    "M5.2 19V9.6",
    "M18.8 19V9.6",
    "M3 11.4C9 5.4 15 5.4 21 11.4",
    "M12 8.6V19",
  ],
  // Arquitectura: compás de dibujo.
  arquitectura: [
    "M12 6.6a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6z",
    "M12 6.8 6 20",
    "M12 6.8 18 20",
    "M8.4 15h7.2",
  ],
  // Ingeniería industrial: engrane.
  industrial: [
    "M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z",
    "M12 2.6v3M12 18.4v3M2.6 12h3M18.4 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M18.7 5.3l-2.1 2.1M7.4 16.6l-2.1 2.1",
  ],
  // Ingeniería en sistemas: código.
  sistemas: [
    "M8 8.2 4 12l4 3.8",
    "M16 8.2 20 12l-4 3.8",
    "M13.3 5.2 10.7 18.8",
  ],
  // Telecomunicaciones: antena / señal.
  telecom: [
    "M12 21.2v-9.4",
    "M12 11.8a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6z",
    "M8.3 6.6a5.2 5.2 0 0 0 0 7.4",
    "M15.7 6.6a5.2 5.2 0 0 1 0 7.4",
    "M5.5 3.8a9 9 0 0 0 0 12.8",
    "M18.5 3.8a9 9 0 0 1 0 12.8",
  ],
  // Física: átomo.
  fisica: [
    "M12 13.8a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6z",
    "M12 3.4c4.1 0 7.6 3.9 7.6 8.6S16.1 20.6 12 20.6 4.4 16.7 4.4 12 7.9 3.4 12 3.4z",
    "M6.5 6.5c2.9-2.9 8.1-1.4 11.6 2.1s5 8.7 2.1 11.6",
    "M6.5 17.5c-2.9-2.9-1.4-8.1 2.1-11.6s8.7-5 11.6-2.1",
  ],
  // Agronomía: brote.
  agronomia: [
    "M12 21v-8",
    "M12 13C12 9 9 6 5 6c0 4 3 7 7 7z",
    "M12 13c0-4 3-7 7-7 0 4-3 7-7 7z",
  ],
  // Forestal: árbol.
  forestal: [
    "M12 3.2 7.2 10h2.4L5.4 16h13.2L14.4 10h2.4z",
    "M12 16v4.8",
  ],
  // Administración: maletín.
  administracion: [
    "M4.4 7.6h15.2a1.6 1.6 0 0 1 1.6 1.6v8.2a1.6 1.6 0 0 1-1.6 1.6H4.4a1.6 1.6 0 0 1-1.6-1.6V9.2a1.6 1.6 0 0 1 1.6-1.6z",
    "M9 7.6V5.8a1.8 1.8 0 0 1 1.8-1.8h2.4A1.8 1.8 0 0 1 15 5.8v1.8",
    "M2.8 12.6h18.4",
  ],
  // Contabilidad: calculadora.
  contabilidad: [
    "M5.4 3.6h13.2v16.8H5.4z",
    "M8.6 7.6h6.8",
    "M8.6 11.8h.01M12 11.8h.01M15.4 11.8h.01M8.6 15.6h.01M12 15.6h.01M15.4 15.6h.01",
  ],
  // Economía: tendencia al alza.
  economia: [
    "M3.4 17.2 9 11.6l4 4 7.6-7.6",
    "M14.8 8h5.8v5.8",
  ],
  // Reserva: birrete (solo si ninguna disciplina coincide).
  default: [
    "M12 3.4 2.4 8.2 12 13l9.6-4.8z",
    "M6 10.6V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-5.4",
    "M21.6 8.2v6",
  ],
};

// El orden importa: la primera coincidencia gana (p. ej. "veterinaria" antes
// que "medicina" para no clasificar Medicina Veterinaria como salud humana).
const RULES = [
  [/veterinar/i, "veterinaria"],
  [/nutric/i, "nutricion"],
  [/medicina|enfermer|salud/i, "salud"],
  [/telecomunic|electr[oó]nica/i, "telecom"],
  [/civil/i, "civil"],
  [/arquitect/i, "arquitectura"],
  [/industrial/i, "industrial"],
  [/sistemas|inform[aá]tic|computaci/i, "sistemas"],
  [/f[ií]sica/i, "fisica"],
  [/agronom|agr[ií]cola/i, "agronomia"],
  [/forestal/i, "forestal"],
  [/contabilidad/i, "contabilidad"],
  [/econom/i, "economia"],
  [/administr/i, "administracion"],
];

const resolveDisciplineKey = (career) => {
  const name = `${career?.name || ""} ${career?.faculty || ""}`;
  const match = RULES.find(([pattern]) => pattern.test(name));
  return match ? match[1] : "default";
};

/**
 * Ícono SVG de la disciplina de una carrera.
 * Hereda el color mediante `currentColor` y es decorativo (el texto que lo
 * acompaña describe la carrera).
 */
function CareerIcon({ career, className = "" }) {
  const key = resolveDisciplineKey(career);
  const paths = ICONS[key] || ICONS.default;

  return createElement(
    "svg",
    {
      viewBox: "0 0 24 24",
      className,
      fill: "none",
      stroke: "currentColor",
      strokeWidth: 1.6,
      strokeLinecap: "round",
      strokeLinejoin: "round",
      focusable: "false",
      "aria-hidden": "true",
    },
    createElement(
      Fragment,
      null,
      paths.map((d, index) => createElement("path", { key: index, d }))
    )
  );
}

export default CareerIcon;
