import "./DemoBadge.css";

/**
 * Aviso de datos simulados del prototipo. Se muestra en las tarjetas que
 * contienen información de demostración (no oficial).
 */
function DemoBadge() {
  return (
    <span
      className="demo-badge"
      title="Datos simulados para fines de demostración de la tesis"
    >
      Datos de demostración — no oficiales
    </span>
  );
}

export default DemoBadge;
