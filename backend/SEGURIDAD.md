# Acceso estudiantil: QR + Google OpenID Connect

## Modelo actual

Laravel sigue siendo la autoridad de modo y autorización. `GET /api/auth/mode` informa a React; cambiar el formulario o el JavaScript no cambia los permisos.

- **Seis cuentas protegidas** (siempre, en modo `demo` e `institutional`): `2024-MED-001`, `2023-ISI-001`, `2024-ADM-001`, `2022-ICI-001`, `2025-ENF-001`, `2023-MED-002`. Su única vía es un QR efímero aprobado con Google Sign-In desde el teléfono. No aceptan carné solo, contraseña, passkey, correos ni tokens Sanctum antiguos.
- **Cuatro cuentas de demostración** (`2024-CON-001`, `2025-ISI-002`, `2022-ARQ-001`, `2023-AGR-001`): acceso local con carné **y contraseña individual**. El seeder deja su hash inicial inutilizable; una persona administradora debe establecer la contraseña con `php artisan student:set-password {carnet}`.
- No hay selector de método. El carné determina la vía: los seis protegidos ven **«Ingresar con QR»**; los cuatro de demo ven **«Ingresar»** y deben escribir carné y contraseña; las cuentas no protegidas en modo institucional usan correo y contraseña.
- El acceso local se unifica en `POST /api/login`. Ya no existe `POST /api/login/carnet` ni `POST /api/register`. La contraseña siempre se verifica con `Hash::check` y los fallos usan una respuesta genérica. Sanctum sólo acepta los nombres explícitos `password-login` y `qr-google`; los tokens de métodos retirados o futuros se rechazan.
- Passkeys, aprobación por correo y sus rutas quedaron eliminadas. Se retiraron las migraciones huérfanas que crearían `student_passkeys` y `login_approvals`; no se ejecutó ninguna migración destructiva contra datos existentes.

## Cuentas Google vinculadas

Cada Gmail tiene dos carnés preautorizados. El correo **no** se envía desde React ni se usa para decidir el acceso:

| Cuenta Google | Carnés |
| --- | --- |
| Cuenta 1 (privada) | `2024-MED-001`, `2023-ISI-001` |
| Cuenta 2 (privada) | `2024-ADM-001`, `2022-ICI-001` |
| Cuenta 3 (privada) | `2025-ENF-001`, `2023-MED-002` |

Los tres Gmail reales **no se incluyen en este repositorio**. Sólo existen en el `.env` privado de cada instalación, dentro de `GOOGLE_ACCOUNT_LINKS` como JSON, y en el Dashboard de Render como variable de entorno. Nunca aparecen en código, logs, QR, URL, respuestas de status ni mensajes de error. La plantilla pública `.env.example` conserva los tres titulares y los seis carnés, con correos ficticios `example.com`; los carnés configurados se suman a la línea base de seis protegidos, de modo que una variable ausente no los desprotege.

`GOOGLE_ACCOUNT_LINKS` sincroniza la tabla `google_preauthorizations` mediante:

```bash
php artisan qr-google:sync-links
php artisan qr-google:links
```

Si la variable está ausente o en blanco, la sincronización conserva los vínculos. Un valor inválido detiene la sincronización. Usa el JSON explícito `[]` únicamente para retirar todas las autorizaciones activas.

Para reactivar un vínculo revocado, verifica que el correo y todos sus carnés siguen perteneciendo al titular esperado, confirma que la configuración privada contiene esa asignación y ejecuta `php artisan qr-google:sync-links --reactivate=CARNÉ`. La opción afecta al vínculo completo del titular, conserva el identificador Google previamente asociado y exige un nuevo acceso QR. Si otro titular activo tiene alguno de esos carnés, resuelve primero la reasignación. Una sincronización normal conserva las revocaciones.

En el primer inicio de sesión correcto se guarda el `sub` de Google junto al carné. A partir de ahí, ese carné sólo acepta esa identidad. Una identidad desconocida se rechaza; un `sub` distinto también. Para desvincular y revocar sesiones:

```bash
php artisan qr-google:revoke 2024-MED-001
```

## Variables de entorno

| Variable | Predeterminado | Uso |
| --- | --- | --- |
| `STUDENT_ACCESS_MODE` | `institutional` | Controla la presentación del acceso de demostración; nunca habilita autenticación por carné sin contraseña. |
| `DEMO_SEED_ON_EMPTY` | `false` | En Render Free, activarlo para sembrar datos ficticios cuando la base SQLite esté vacía. El comando es idempotente y nunca restablece contraseñas ni sobrescribe registros. |
| `TRUSTED_PROXIES` | vacío | Proxies de confianza para resolver la IP real en Render; `*` se usa en el blueprint de Render. |
| `TOKEN_LIFETIME_MINUTES` | `120` | Tiempo máximo desde la emisión del token Sanctum. |
| `TOKEN_IDLE_MINUTES` | `30` | Tiempo máximo desde la última petición autenticada. |
| `QR_AUTH_TTL_MINUTES` | `10` | Vigencia de la solicitud QR y del número de confirmación. |
| `QR_AUTH_URL` | `APP_URL` | Origen del backend accesible desde el teléfono. En producción debe usar HTTPS. En local, usa la IP de red o un dominio accesible para el teléfono. |
| `GOOGLE_CLIENT_ID` | — | ID de cliente OAuth de Google Cloud. |
| `GOOGLE_CLIENT_SECRET` | — | Secreto del cliente OAuth; nunca sale del backend. `GOOGLE_SECRET` se acepta como alias (Render). |
| `GOOGLE_REDIRECT_URI` | `APP_URL/auth/google/callback` | Callback exacto registrado en Google Cloud. |
| `GOOGLE_ACCOUNT_LINKS` | — | JSON privado con los tres pares de carnés y sus Gmail. |
| `GOOGLE_AUTH_URL` | `https://accounts.google.com/o/oauth2/v2/auth` | Endpoint de autorización. |
| `GOOGLE_TOKEN_URL` | `https://oauth2.googleapis.com/token` | Endpoint de intercambio de código. |
| `GOOGLE_JWKS_URL` | `https://www.googleapis.com/oauth2/v3/certs` | Claves públicas para verificar el ID token. |
| `QR_AUTH_TEST_OIDC_VERIFIER` | vacío | **Sólo pruebas**: nombre del verificador simulado. Debe estar vacío en producción. |

`config/services.php` expone estos valores a Socialite y al verificador. Si `GOOGLE_CLIENT_SECRET` está vacío se usa `GOOGLE_SECRET`, para no obligar a renombrar la variable ya creada en Render. En producción, `QR_AUTH_URL` y `GOOGLE_REDIRECT_URI` deben ser HTTPS; la aplicación falla deliberadamente si no lo son.

## Contraseñas locales

La contraseña de una cuenta se establece o cambia únicamente desde el backend, con entrada oculta y confirmación:

```bash
php artisan student:set-password 2024-CON-001
```

La orden exige al menos 12 caracteres, rechaza contraseñas frecuentes y las que contienen el carné, correo o nombre de la cuenta. No imprime ni registra la contraseña y revoca todos los tokens existentes al cambiarla. Para retirar físicamente tokens de métodos anteriores se dispone de `php artisan auth:revoke-legacy-tokens`; `php artisan demo:normalize-emails --dry-run` permite revisar la normalización de correos ficticios sin escribir.

Render usa SQLite con la ruta absoluta DB_DATABASE. Vercel requiere VITE_API_URL con la URL HTTPS de la API, incluido /api.

## Configurar Google Cloud

1. Crear o abrir el proyecto en **Google Cloud Console → APIs y servicios → Credenciales**.
2. Configurar la **pantalla de consentimiento OAuth**. Para pruebas se puede usar tipo `Externo` y añadir los tres Gmail como *usuarios de prueba*; publicarla sólo cuando la app esté lista.
3. Crear credenciales de tipo **ID de cliente OAuth → Aplicación web**.
4. En **URI de redireccionamiento autorizados**, añadir exactamente:
   `https://<host-de-render>/auth/google/callback`
   El valor debe coincidir con `GOOGLE_REDIRECT_URI`, sin barra final adicional, sin `http://` y sin el puerto local del frontend.
5. Copiar el ID y el secreto a `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET` (o `GOOGLE_SECRET`, alias admitido) del `.env` privado del backend. No pegarlos en código, chat ni repositorio.
6. No solicitar permisos de Gmail, contactos ni Drive. La app pide únicamente `openid` y `email` (opcionalmente `profile`).
7. Ejecutar `php artisan config:clear` y reiniciar el proceso del backend para que lea la nueva configuración.

## Flujo QR

1. React envía **sólo el carné** a `POST /api/qr-auth/request`. El backend genera un `request_id`, un nonce de navegador, un número aleatorio de seis dígitos y una URL HTTPS efímera. Del nonce sólo se guarda el hash.
2. La computadora muestra el QR, el número, el tiempo restante y **«Esperando autorización»**, con **«Cancelar solicitud»**. Sondea `GET /api/qr-auth/status/{id}` con el nonce en una cabecera; la respuesta no incluye correos, tokens ni datos personales.
3. El teléfono abre el QR y ve el número recibido en pantalla. Debe escribirlo y enviarlo con `POST` antes de continuar; abrir la página no aprueba nada. Inicia Google Sign-In en una pestaña nueva del propio teléfono.
4. Google devuelve el código a `GET /auth/google/callback`. Laravel valida `state`, PKCE y el `nonce` OIDC; intercambia el código por tokens y verifica el ID token con JWKS/RS256: firma, issuer, audience, `azp`, expiración, `nonce`, `email_verified` y `sub`.
5. La pantalla de revisión del teléfono ofrece **«Sí, continuar»** o **«No reconozco este acceso»**. La decisión se envía con `POST`; la sesión OAuth del teléfono es temporal y distinta del token final de la computadora.
6. La computadora canjea la solicitud aprobada con `POST /api/qr-auth/exchange` y recibe un token Sanctum llamado `qr-google`. El canje es de un solo uso; un segundo intento falla. Cancelar, rechazar o expirar cierra la solicitud sin emitir sesión.
7. React vuelve al chatbot con la sesión ya autenticada. El token está sujeto a expiración, inactividad y revocación. La allowlist de Sanctum sólo admite `qr-google` y `password-login`; los seis carnés rechazan `password-login`, `demo-session`, `chatbot-token` y cualquier nombre anterior o futuro no previsto.

La URL del QR contiene un identificador opaco de un solo uso. No contiene correos, tokens de Sanctum, tokens de Google, `state`, `nonce`, códigos ni el secreto del navegador. Las respuestas API llevan `Cache-Control: private, no-store` y la aplicación envía `Referrer-Policy: no-referrer`. Las rutas JSON están limitadas por throttling y los intentos de canje/concurrencia se resuelven de forma atómica en base de datos.

Notas, pagos, recibos, becas, tutorías, horarios y chats siguen filtrando por el usuario del token; las pruebas cubren cruce entre los seis carnés y sus pares.

## Prueba real en el teléfono

El deploy actual de Render corresponde al commit anterior `7585165`, por lo que QR + Google todavía no está publicado. Para ensayar con un teléfono, el backend debe ser accesible por HTTPS desde ese dispositivo (por ejemplo mediante el host definitivo de Render o un túnel HTTPS temporal; `localhost` del servidor no funciona en el teléfono).

1. Configura Google Cloud y el backend con `QR_AUTH_URL=https://<host-publico-del-backend>` y `GOOGLE_REDIRECT_URI=https://<host-publico-del-backend>/auth/google/callback`.
2. Añade el origen exacto de Vercel a `CORS_ALLOWED_ORIGINS` y ejecuta `php artisan config:clear`.
3. Abre el frontend en la computadora, escribe `2024-MED-001` y pulsa **«Ingresar con QR»**.
4. Escanea el QR con el teléfono. Comprueba que pide el número de seis dígitos mostrado en pantalla, que no muestra correos y que inicia Google Sign-In en una pestaña nueva.
5. Inicia sesión con el Gmail correspondiente al carné, revisa la cuenta y pulsa **«Sí, continuar»**. La computadora debe entrar al chatbot sola.
6. Repite con un carné del mismo Gmail (debe funcionar), con otro de los tres Gmail (debe funcionar) y con un Gmail ajeno a la lista (debe ser rechazado).
7. Prueba **«No reconozco este acceso»**, cancelar desde la computadora, reabrir un QR ya usado y dejar expirar una solicitud. Ninguno debe crear sesión.
8. Ejecuta `php artisan qr-google:revoke 2024-MED-001` y confirma que el token anterior deja de servir y que un nuevo `sub` no puede vincularse sin intervención administrativa.

## Vercel y Render

- **Vercel (frontend):** configurar `VITE_API_URL=https://chatbot-uncpggl-api.onrender.com/api` y el directorio raíz `chatbot-universidad`. No poner secretos de Google ni variables del backend en Vercel.
- **Render (backend):** usar el mismo host público en `APP_URL`, `QR_AUTH_URL` y `GOOGLE_REDIRECT_URI`. Registrar `https://chatbot-uncpggl-api.onrender.com/auth/google/callback` exactamente en Google Cloud. Mantener `GOOGLE_SECRET` si ya existe en el Dashboard; el código lo acepta.
- **Base de datos.** Render Free usa un filesystem efímero. La sección de Fase 4 describe las opciones con SQLite para la defensa.
- **Arranque del contenedor.** El proceso migra SQLite, ejecuta `demo:ensure-seeded` (sólo escribe si `DEMO_SEED_ON_EMPTY=true` y la base está vacía) y sincroniza preautorizaciones. Nunca restablece contraseñas ni sobrescribe registros.
- **Orígenes:** añadir el dominio exacto de Vercel (y cualquier dominio personalizado) a `CORS_ALLOWED_ORIGINS`. No usar comodines.
- **HTTPS:** obligatorio para QR, callback y URLs públicas. El backend rechaza configuraciones HTTP en producción.

## Riesgos y límites

- La verificación real con Google requiere credenciales OAuth y el callback HTTPS exacto. Aunque ya están configurados en Google Cloud/Render, la validación extremo a extremo con Gmail real todavía no se ejecutó: el código publicado sigue siendo el commit anterior. Las pruebas automáticas simulan Google y la verificación RS256/JWKS real se prueba con claves generadas localmente.
- Al perderse la base efímera se pierden las sesiones, el historial y el estado de las vinculaciones y revocaciones de Google.
- Google puede cambiar sus metadatos; `GOOGLE_JWKS_URL` y la caché de claves deben supervisarse.
- Un atacante con acceso físico desbloqueado al teléfono vinculado podría aprobar. El número de seis dígitos y la revisión explícita reducen el riesgo, pero no lo eliminan.
- No se implementó selección automática de cuenta por correo: Google muestra `prompt=select_account` y el usuario elige su cuenta en el teléfono. El backend valida el `sub` resultante.
- No se implementaron biometría local, notificaciones push, app nativa ni administración web de vinculaciones; éstas se gestionan por comandos Artisan.

## Verificación automática

Desde la raíz del proyecto:

```bash
cd backend && php artisan test --compact
cd chatbot-universidad && npm run lint && npm run build && npm test
python -X utf8 backend/tests/auth_regression_browser.py
python -X utf8 backend/tests/qr_google_browser.py
```

`auth_regression_browser.py` quedó actualizado: comprueba, para los seis carnés, que carné/contraseña/passkeys/aprobación por correo están cerrados y que `POST /api/qr-auth/request` es la única vía.

Los resultados de la Fase 2: **165/165** pruebas backend con **2907** aserciones; **41/41** pruebas React; lint y build correctos. `DemoSeedTest` cubre el interruptor explícito, la siembra única y la inmutabilidad de contraseñas; `QrGoogleAuthTest` verifica que las seis vinculaciones se cargan desde `GOOGLE_ACCOUNT_LINKS`. Las migraciones huérfanas de passkeys y aprobación por correo (`2026_11_20_*`) se retiraron: nunca estuvieron en HEAD y sus tablas no existen en el esquema nuevo. `AuthCredentialsTest.php` añade 21 escenarios de carné/correo + contraseña, respuestas genéricas, estado del estudiante, QR, administrador, rate limiting, allowlist de tokens, seeder y aislamiento de datos. `QrGoogleAuthTest.php` cubre vinculación de `sub`, revocación, repetición, expiración, cancelación, rechazo, cruce de navegadores y carnés, `state`/`nonce` inválidos, callback reutilizado, solicitudes simultáneas y bloqueo de rutas antiguas. `GoogleOidcVerifierTest.php` cubre RS256, JWKS, firma inválida, issuer, audience, `azp`, nonce, `email_verified`, expiración y `sub`; usa claves locales y correos `example.com`. El E2E histórico usa un servidor OAuth simulado y credenciales falsas; **no usa Gmail real ni envía correos**.

Copias previas al cambio, en la carpeta padre: `chatbot-antes-aprobacion-correo.zip`, `chatbot-antes-aprobacion-correo.sqlite`, `chatbot-antes-simplificacion-passkeys.zip`, `chatbot-antes-simplificacion-passkeys.sqlite`, `chatbot-antes-qr-google.zip` y `chatbot-antes-qr-google.sqlite`. Para restaurarlas, hacerlo en otra carpeta y con el `.env` correspondiente; no sobrescribir la base de trabajo ni publicar el `.env`.

## Fase 4 · Preparación del despliegue

1. **Render.** Conserva la `APP_KEY` válida entre arranques. Para una instalación nueva, genera la clave con `php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'` y guárdala como variable privada en el Dashboard. Retira cualquier `DB_URL` anterior. Usa `DB_CONNECTION=sqlite` y la ruta absoluta `DB_DATABASE` del Blueprint. Mantén `CACHE_STORE=database` para compartir los límites entre los procesos PHP. Configura las variables privadas de Google y Groq en Render.
2. **Vercel.** Usa `chatbot-universidad` como directorio raíz y define `VITE_API_URL` con la URL pública HTTPS del backend terminada en `/api`. Autoriza el origen exacto de Vercel en `CORS_ALLOWED_ORIGINS`. Si cambia el dominio de la API, actualiza también `connect-src` en `chatbot-universidad/vercel.json`. Registra la URL HTTPS exacta de retorno en Google Cloud y conserva `QR_AUTH_URL` en HTTPS.
3. **Verificación posterior.** Comprueba `/up`, el salto de HTTP a HTTPS, el preflight desde el origen autorizado y el rechazo de un origen ajeno. Revisa `Cache-Control: private, no-store` en la API y en los flujos QR/OAuth. Prueba el acceso con carné y con Google. Comprueba que el proxy de Render entrega la IP del visitante aun cuando el cliente envíe `X-Forwarded-For`. Limita el acceso al contenedor al proxy de Render.

### Aprovisionamiento de la defensa

**Render Free, sin Shell.** Configura `DEMO_SEED_ON_EMPTY=true` y `GOOGLE_ACCOUNT_LINKS` con los tres titulares y los seis carnés. En cada arranque, `docker/start.sh` ejecuta:

```sh
php artisan migrate --force
php artisan demo:ensure-seeded
php artisan qr-google:sync-links
```

`demo:ensure-seeded` sólo siembra si todas las tablas de datos están vacías; si existe cualquier usuario o registro, no escribe nada. No ejecuta `student:set-password`, no imprime contraseñas y no modifica cuentas existentes. Las cuatro cuentas locales de demostración quedan con contraseñas aleatorias inutilizables; el acceso de la defensa se hace con los seis carnés QR. Si necesitas acceso local con contraseña, prepara una base aislada con `migrate`, `db:seed` y `student:set-password`, o usa un disco persistente con Shell; no pongas contraseñas en variables de entorno.

**Base aislada con Shell (opcional).** En una copia aislada del proyecto, crea un archivo SQLite vacío. Desde `backend`, apunta `DB_DATABASE` a su ruta absoluta y confirma que corresponde a la base ficticia. Ejecuta:

```sh
php artisan migrate
php artisan db:seed
php artisan student:set-password 2024-CON-001
```

Repite el último comando para las cuentas locales que participarán. El comando solicita una contraseña individual con entrada oculta y revoca los tokens de esa cuenta. Mantén las contraseñas fuera del repositorio y de las variables `VITE_*`. Para los carnés protegidos, configura en privado cuentas Google autorizadas para la demostración y ejecuta `php artisan qr-google:sync-links` en esa base. El QR real requiere que el teléfono alcance la URL HTTPS registrada. Prepara el acceso local con carné y contraseña para una defensa sin Internet. Prueba los accesos y una consulta pública antes de la presentación.

### Persistencia y capacidad

- **Defensa sin contratar servicios.** Ejecuta Laravel y React en el equipo de la presentación con el SQLite ficticio preparado y una copia comprobada. Render Free usa almacenamiento efímero y puede entrar en reposo. Sobre una base vacía y con `DEMO_SEED_ON_EMPTY=true`, Render aplica migraciones, siembra una vez y sincroniza los vínculos QR; en arranques posteriores con datos no vuelve a sembrar. [Documentación de Render Free](https://render.com/docs/free)
- **Si ya hay un disco compatible.** Un servicio Render puede montar el disco en `/var/www/html/storage/database` y conservar la ruta del Blueprint. Prepara el directorio para que `www-data` escriba los archivos SQLite y WAL. Crea copias con el mecanismo de copia de SQLite y comprueba la recuperación en otro archivo. El disco limita el servicio a una instancia y los despliegues pueden interrumpirlo. [Discos persistentes de Render](https://render.com/docs/disks)
- La pérdida de la base efímera también elimina sesiones, historial, revocaciones y contadores. El chat admite dos solicitudes simultáneas y aplica un máximo compartido de 60 por minuto y 500 al día. Cada intento de Groq tiene un máximo de cuatro segundos y la solicitud puede hacer un segundo intento. El servidor PHP se mantiene para baja concurrencia y la defensa.
