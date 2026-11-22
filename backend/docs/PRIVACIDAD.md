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
- Una **ventana limitada** de la conversación actual de invitado (ver sección 4).
- El **contexto** que el servidor decide incluir (sección 3).
- Instrucciones del sistema (tono, formato, reglas de privacidad).

## 3. Minimización de datos

Laravel resuelve el saludo por carné, los horarios personalizados, los pagos,
las calificaciones, las becas, los exámenes y las tutorías. Groq recibe
contexto del catálogo público, sin identidad ni datos académicos de la cuenta.
Las opciones antiguas de envío de identidad y datos académicos ya no se aplican.

El texto libre de las consultas que requieren Groq puede llegar al proveedor.
El servidor omite correos y carnés en los formatos reconocidos por el chat.

## 4. Memoria conversacional

- Se envía como máximo una **ventana de los últimos intercambios** de la
  **conversación actual** (`chatbot.memory.exchanges`, por defecto 6).
- Groq recibe esta ventana únicamente en el chat de invitado. Se obtiene de la conversación protegida: **nunca se
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

- Laravel genera el identificador de cada conversación con 32 bytes aleatorios.
- El historial y la escritura de invitados exigen la credencial cifrada que
  recibe el navegador al crear el chat. La credencial viaja en una cabecera.
- Las conversaciones autenticadas sólo se pueden consultar o modificar desde
  la cuenta propietaria. Al iniciar sesión se abre un historial separado.
- Los chats anónimos de versiones anteriores quedan fuera de la interfaz
  porque carecen de credencial. Las conversaciones de cuentas existentes
  siguen accesibles para sus propietarios.

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
