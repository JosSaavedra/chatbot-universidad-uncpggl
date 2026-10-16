# Política de privacidad del asistente (prototipo)

Este documento describe **qué información del chatbot sale del servidor** hacia
el proveedor de IA (Groq) y cómo se minimiza. Aplica al prototipo académico de
la UNCPGGL. No es un documento legal definitivo: es la política del prototipo y
debe revisarse antes de un uso en producción.

## 1. Cómo funciona el asistente

El asistente responde en dos capas:

1. **Resolución estructurada (Laravel + SQLite).** Las intenciones (carreras,
   horarios, admisión, calendario, pagos…) se resuelven con consultas a la base
   de datos y se muestran como **tarjetas** deterministas en el chat. Esta capa
   **no consulta a la IA**.
2. **Redacción con IA (Groq).** El texto de la respuesta lo redacta un modelo
   alojado por un tercero (Groq). El modelo **solo redacta** a partir del
   contexto que el servidor le entrega; no accede directamente a la base de
   datos.

## 2. Qué se envía a Groq

Solo se envía:

- El **mensaje** que escribe la persona.
- Una **ventana limitada** de la conversación actual (ver sección 4).
- El **contexto** que el servidor decide incluir (sección 3).
- Instrucciones del sistema (tono, formato, reglas de privacidad).

## 3. Minimización de datos (`config/chatbot.php`)

| Dato | ¿Se envía a la IA? | Control |
| --- | --- | --- |
| Nombre del estudiante | **No** (por defecto) | `chatbot.privacy.send_identity` |
| Carné / código de estudiante | **No** (por defecto) | `chatbot.privacy.send_identity` |
| Carrera y año del estudiante | Sí (necesario para «mi horario») | — |
| Notas / calificaciones | Solo si la consulta las pide | `send_personal_data` + intención |
| Exámenes personales | Solo si la consulta los pide | `send_personal_data` + intención |
| Becas | Solo si la consulta las pide | `send_personal_data` + intención |
| Tutorías | Solo si la consulta las pide | `send_personal_data` + intención |
| Pagos / recibos | **No** (se muestran en su módulo) | — |
| Horario del estudiante | Sí (dato de demostración) | — |

Valores por defecto:

```php
// config/chatbot.php
'privacy' => [
    'send_identity'      => false, // nunca nombre ni carné salvo que se active
    'send_personal_data' => true,  // notas/exámenes/becas/tutorías SOLO si se piden
],
```

- `send_identity = false`: el nombre y el carné **nunca** se transmiten a la IA,
  porque no son indispensables para responder. Si se necesita depurar, puede
  activarse temporalmente.
- `send_personal_data = true`: los datos académicos privados se incluyen
  **únicamente cuando el mensaje los pide explícitamente** («¿cómo van mis
  notas?»). No se envían en cada mensaje. Con `false` se omiten siempre.

El horario del estudiante sí se comparte porque es **información de
demostración** y es necesaria para responder «¿quién es mi profesor de esa
materia?».

## 4. Memoria conversacional

- Se envía como máximo una **ventana de los últimos intercambios** de la
  **conversación actual** (`chatbot.memory.exchanges`, por defecto 6).
- La ventana se obtiene de la conversación ya resuelta y protegida: **nunca se
  mezclan conversaciones de distintas sesiones ni de distintos usuarios**.
- Los seguimientos («¿y cuánto cuesta?», «¿esa carrera?», «¿ese profesor?») se
  resuelven preferentemente con **datos estructurados** (tarjetas previas
  guardadas en `messages.data` y consultas a SQLite), no solo con el modelo.

## 5. Datos reales vs. simulados

- **Reales (del prototipo):** la conversación guardada, la cuenta del
  estudiante que inicia sesión y su horario/notas **si existen en la base de
  datos de demostración**.
- **Simulados (demostración):** carreras, costos, horarios por carrera,
  admisión y calendario se cargan con datos de ejemplo. No son información
  oficial verificada. El asistente debe advertirlo cuando sea relevante y el
  chat muestra la insignia «Demostración» en las tarjetas correspondientes.

## 6. Seguridad del historial

- El historial de una conversación **personal** solo lo puede leer su dueño.
  Una petición anónima o de otra cuenta recibe `403`.
- Una petición anónima **nunca** escribe dentro de la conversación de un
  usuario: usa una conversación de invitado aparte.
- Un `session_id` ajeno no permite acceder a datos privados.

## 7. Aviso de privacidad mostrado en el prototipo

> Prototipo académico de demostración. La información de carreras, horarios,
> admisión y calendario puede ser simulada. Tus mensajes se procesan con un
> servicio de IA (Groq) para redactar la respuesta. No ingreses datos sensibles
> (contraseñas, datos bancarios, etc.).

Este aviso se muestra en la interfaz del chat y su texto vive en
`config/chatbot.php` (`privacy.notice`).

## 8. Recomendaciones antes de producción

- Revisar la política de retención del proveedor de IA.
- Firmar un acuerdo de tratamiento de datos con el proveedor.
- Ofrecer consentimiento explícito y una versión legalmente revisada del aviso.
- Valorar un modelo autoalojado para eliminar la salida de datos a terceros.
