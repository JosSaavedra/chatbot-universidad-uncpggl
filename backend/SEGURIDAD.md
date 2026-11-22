# Acceso estudiantil

## Modelo actual

Laravel sigue siendo la autoridad de modo y autorización. `GET /api/auth/mode` informa a React; cambiar el formulario o el JavaScript no cambia los permisos.

- **Contraseña y aprobación por correo** para `2024-MED-5837`, `2024-ADM-1748` y `2025-ENF-4621`. En computadora, el QR inicia el envío. En celular, se usa **«Continuar con la verificación»**. La aprobación exige escribir el número mostrado en el dispositivo de origen y confirmar.
- **QR con Google OpenID Connect** para `2023-ISI-9264`, `2022-ICI-8392` y `2023-MED-7056`. Se inicia con **«Usar QR con Google»**.
- **Cuatro cuentas de demostración** (`2024-CON-3189`, `2025-ISI-6473`, `2022-ARQ-2815`, `2023-AGR-9542`): acceso local con carné **y contraseña individual**. El seeder deja su hash inicial inutilizable; una persona administradora debe establecer la contraseña con `php artisan student:set-password {carnet}`.
- `POST /api/login` comprueba la contraseña con `Hash::check`. Para las cuentas con aprobación por correo, devuelve una solicitud temporal y reserva el token de sesión para el canje posterior. Sanctum exige el método asignado al carné mediante `password-login`, `qr-google` o `qr-email`.

## Configuración de la aprobación por correo

Configura `QR_EMAIL_2024_MED_5837`, `QR_EMAIL_2024_ADM_1748` y `QR_EMAIL_2025_ENF_4621` con los destinatarios autorizados. Usa el `.env` local excluido de Git o **Environment** en el servicio backend de Render. Las tres solicitudes vencen en cinco minutos y cada carné puede iniciar hasta tres envíos cada diez minutos.

El mailer de aprobación usa Gmail API por HTTPS con el permiso `https://www.googleapis.com/auth/gmail.send`. El cliente OAuth de escritorio y el Gmail remitente se configuran por separado del cliente web de ingreso con Google.

### Autorizar el Gmail remitente

1. Habilita **Gmail API** en el proyecto de Google Cloud del cliente de escritorio. En **Google Auth Platform → Data Access**, añade `gmail.send`. Para producción, completa la configuración de publicación y la verificación que corresponda. Una app externa en estado **Testing** requiere añadir el remitente como usuario de prueba y su refresh token de Gmail vence en siete días.
2. Guarda el JSON OAuth original en una carpeta privada fuera del repositorio. Desde la raíz del proyecto, ejecuta:

   ```powershell
   python backend/scripts/authorize_gmail.py --client "$env:USERPROFILE/GmailPrivado/client_secret.json" --output "$env:USERPROFILE/GmailPrivado/gmail-oauth.env" --from-address "remitente@gmail.com"
   ```

3. Selecciona en el navegador exactamente la cuenta que pasaste en `--from-address` y acepta el permiso de envío. El script recibe el callback local por `127.0.0.1`, verifica `state` y usa PKCE. Guarda las credenciales en el archivo privado indicado.
4. Copia las cuatro líneas del archivo a `backend/.env` para probar localmente. En **Render → chatbot-uncpggl-api → Environment**, carga `GMAIL_OAUTH_CLIENT_ID`, `GMAIL_OAUTH_CLIENT_SECRET`, `GMAIL_OAUTH_REFRESH_TOKEN` y `MAIL_FROM_ADDRESS` (el mismo Gmail que autorizaste). Añade allí las tres variables privadas `QR_EMAIL_*` indicadas arriba. Guarda con **Save only** hasta que autorices el despliegue.

### Publicación final

- [ ] **Antes del push**. Comprueba las contraseñas vigentes de los tres carnés de correo en la base que se usará en producción. En Render Free, SQLite es efímero y la siembra actual solo permite aprovisionar hashes conocidos para los cuatro carnés tradicionales. Una base recién sembrada no permite ensayar los tres accesos por correo con contraseñas conocidas.
- [ ] **Docker y Apache**. Ejecuta `docker build -t uncpggl-final backend` y `docker run --rm -d --name uncpggl-check --env-file <archivo-privado-de-prueba> -e PORT=10000 -p 127.0.0.1:18080:10000 uncpggl-final`. Usa una `APP_KEY` de prueba y deja que el contenedor cree su SQLite interno con la ruta predeterminada de la imagen. Verifica `docker exec uncpggl-check apache2ctl -t` y `curl.exe -fsS http://127.0.0.1:18080/up`. Termina la prueba con `docker stop uncpggl-check`.
- [ ] **Configurar Vercel antes del push**. Confirma el proyecto y directorio raíz `chatbot-universidad`, la rama de producción, el build `npm run build`, la salida `dist` y `VITE_API_URL=https://chatbot-uncpggl-api.onrender.com/api` en Production. En **Settings → Environments → Production → Branch Tracking**, desactiva **Auto-assign Custom Production Domains** para elegir cuándo se publica el build.
- [ ] **GitHub**. Tras autorizar la publicación, selecciona los cambios del cierre, incluidos `backend/app/Mail`, `backend/scripts/authorize_gmail.py`, las vistas de correo, las dos migraciones nuevas, `backend/docker/apache-mpm.conf` y `backend/docker/php-render.ini` que requiere el Dockerfile. Revisa `git diff --cached --name-status` y `git diff --cached --check`; los archivos `.env` privados, JSON OAuth, SQLite y copias locales deben quedar fuera. Confirma los cambios y publica la rama autorizada. Anota el SHA.
- [ ] **Render**. Con las variables privadas guardadas y aprobado el despliegue, despliega ese SHA del backend Docker mediante **Manual Deploy → Deploy a specific commit**. Revisa el arranque y las migraciones. Comprueba `https://chatbot-uncpggl-api.onrender.com/up`.
- [ ] **Publicar Vercel**. Cuando Render responda correctamente, comprueba que el build de Vercel corresponde al mismo SHA. En **Deployments**, selecciona **Promote** para ese build y comprueba la URL de producción.
- [ ] **Prueba final**. Desde la URL pública, verifica la recepción en los tres buzones reales y el canje solo en el navegador que inició la solicitud. Prueba también rechazo, ingreso con Google de los otros tres carnés y acceso tradicional de los cuatro restantes.

## Cuentas Google vinculadas

Las tres cuentas privadas de Google conservan estos carnés:

| Cuenta Google | Carnés |
| --- | --- |
| Cuenta 1 (privada) | `2023-ISI-9264` |
| Cuenta 2 (privada) | `2022-ICI-8392` |
| Cuenta 3 (privada) | `2023-MED-7056` |

Los tres Gmail reales se gestionan como configuración privada en `GOOGLE_ACCOUNT_LINKS`. La plantilla pública `.env.example` usa correos ficticios `example.com`. Los carnés configurados se suman a la lista de protección de Laravel, que exige QR incluso si la variable está ausente.

`GOOGLE_ACCOUNT_LINKS` sincroniza la tabla `google_preauthorizations` mediante:

```bash
php artisan qr-google:sync-links
php artisan qr-google:links
```

Si la variable está ausente o en blanco, la sincronización conserva los vínculos. Un valor inválido detiene la sincronización. Usa el JSON explícito `[]` únicamente para retirar todas las autorizaciones activas. Los seis carnés protegidos anteriores también exigen QR si se restaura una base antigua.

### Aplicación en una instalación existente

1. Haz una copia consistente de SQLite y comprueba su integridad antes de intervenir.
2. Actualiza únicamente los carnés de cada titular en el `GOOGLE_ACCOUNT_LINKS` privado. En Render, guarda el JSON crudo desde **Environment** para que se active junto con la nueva versión.
3. Ejecuta `php artisan migrate --force` antes de `php artisan qr-google:sync-links`. El arranque de Render ya sigue ese orden. La migración conserva las fichas, los datos académicos y los vínculos de Google, y revoca el acceso anterior de las cuentas afectadas.
4. Para inicializar una demo vacía en Render Free, consulta el procedimiento de aprovisionamiento de abajo. Los estudiantes que usan contraseña y aprobación por correo necesitan una contraseña individual aprovisionada.
5. Configura `VITE_API_URL` en Vercel con la URL HTTPS de la API terminada en `/api`. Mantén el origen de Vercel autorizado en Laravel y configura los secretos de Google y Groq exclusivamente en el backend.

Para reactivar un vínculo revocado, verifica que el correo y todos sus carnés siguen perteneciendo al titular esperado, confirma que la configuración privada contiene esa asignación y ejecuta `php artisan qr-google:sync-links --reactivate=CARNÉ`. La opción afecta al vínculo completo del titular, conserva el identificador Google previamente asociado y exige un nuevo acceso QR. Si otro titular activo tiene alguno de esos carnés, resuelve primero la reasignación. Una sincronización normal conserva las revocaciones.

### Conservar revocaciones en Render Free

Render Free usa un filesystem efímero: al reiniciarse o reconstruirse la base se pierden `personal_access_tokens` y el `revoked_at` de `google_preauthorizations`. Las variables privadas no sustituyen a SQLite, pero sí fijan la configuración autoritativa que el arranque vuelve a aplicar:

- `GOOGLE_ACCOUNT_LINKS`: conserva el JSON exacto de los tres titulares y sus carnés en **Environment** (nunca en el repositorio ni en variables `VITE_*`). `qr-google:sync-links` no reactiva vínculos revocados salvo que se use `--reactivate=...`. Para que un carné siga revocado tras una base nueva, retíralo del JSON de su titular (o usa `[]` sólo si quieres retirar todas las autorizaciones) y vuelve a sincronizar.
- `DEMO_STUDENT_PASSWORD_HASHES`: `demo:ensure-seeded` siembra únicamente una base vacía; no sobrescribe contraseñas, estados ni tokens. Mantén el mismo valor privado para no reabrir cuentas ya revocadas.
- Si una revocación no puede representarse en el JSON (por ejemplo, tokens ya emitidos), repite `php artisan qr-google:revoke CARNÉ` después de reconstruir SQLite. No ejecutes `--reactivate` como parte del arranque habitual.

En el primer inicio de sesión correcto se guarda el `sub` de Google junto al carné. A partir de ahí, ese carné sólo acepta esa identidad. Una identidad desconocida se rechaza; un `sub` distinto también. Para desvincular y revocar sesiones:

```bash
php artisan qr-google:revoke 2023-ISI-9264
```

## Variables de entorno

| Variable | Predeterminado | Uso |
| --- | --- | --- |
| `STUDENT_ACCESS_MODE` | `institutional` | Controla la presentación del acceso de demostración; nunca habilita autenticación por carné sin contraseña. |
| `DEMO_SEED_ON_EMPTY` | `false` | En Render Free, activarlo para sembrar datos ficticios cuando la base SQLite esté vacía. El comando es idempotente y nunca restablece contraseñas ni sobrescribe registros. |
| `DEMO_STUDENT_PASSWORD_HASHES` | vacío | Objeto JSON privado de carné a hash bcrypt para las cuatro cuentas tradicionales. Se usa sólo al crear la demo en una base vacía. |
| `TRUSTED_PROXIES` | vacío | Proxies de confianza para resolver la IP real en Render; `*` se usa en el blueprint de Render. |
| `TOKEN_LIFETIME_MINUTES` | `120` | Tiempo máximo desde la emisión del token Sanctum. |
| `TOKEN_IDLE_MINUTES` | `30` | Tiempo máximo desde la última petición autenticada. |
| `QR_AUTH_TTL_MINUTES` | `10` | Vigencia del QR de Google y de su número de confirmación. |
| `QR_AUTH_URL` | `APP_URL` | Origen del backend accesible desde el teléfono. En producción debe usar HTTPS. En local, usa la IP de red o un dominio accesible para el teléfono. |
| `GOOGLE_CLIENT_ID` | — | ID de cliente OAuth de Google Cloud. |
| `GOOGLE_CLIENT_SECRET` | — | Secreto del cliente OAuth; nunca sale del backend. `GOOGLE_SECRET` se acepta como alias (Render). |
| `GOOGLE_REDIRECT_URI` | `APP_URL/auth/google/callback` | Callback exacto registrado en Google Cloud. |
| `GOOGLE_ACCOUNT_LINKS` | — | JSON privado con los carnés que conservan Google y sus cuentas autorizadas. |
| `GOOGLE_AUTH_URL` | `https://accounts.google.com/o/oauth2/v2/auth` | Endpoint de autorización. |
| `GOOGLE_TOKEN_URL` | `https://oauth2.googleapis.com/token` | Endpoint de intercambio de código. |
| `GOOGLE_JWKS_URL` | `https://www.googleapis.com/oauth2/v3/certs` | Claves públicas para verificar el ID token. |
| `QR_AUTH_TEST_OIDC_VERIFIER` | vacío | **Sólo pruebas**: nombre del verificador simulado. Debe estar vacío en producción. |

`config/services.php` expone estos valores a Socialite y al verificador. Si `GOOGLE_CLIENT_SECRET` está vacío se usa `GOOGLE_SECRET`, para no obligar a renombrar la variable ya creada en Render. En producción, `QR_AUTH_URL` y `GOOGLE_REDIRECT_URI` deben ser HTTPS; la aplicación falla deliberadamente si no lo son.

## Contraseñas locales

La contraseña de una cuenta se establece o cambia únicamente desde el backend, con entrada oculta y confirmación:

```bash
php artisan student:set-password 2024-CON-3189
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
6. Para este cliente web de ingreso, solicita únicamente `openid` y `email` (opcionalmente `profile`). El permiso `gmail.send` corresponde al cliente de escritorio del remitente.
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
3. Abre el frontend en la computadora, escribe `2024-MED-5837` y pulsa **«Ingresar con QR»**.
4. Escanea el QR con el teléfono. Comprueba que pide el número de seis dígitos mostrado en pantalla, que no muestra correos y que inicia Google Sign-In en una pestaña nueva.
5. Inicia sesión con el Gmail correspondiente al carné, revisa la cuenta y pulsa **«Sí, continuar»**. La computadora debe entrar al chatbot sola.
6. Repite con un carné del mismo Gmail (debe funcionar), con otro de los tres Gmail (debe funcionar) y con un Gmail ajeno a la lista (debe ser rechazado).
7. Prueba **«No reconozco este acceso»**, cancelar desde la computadora, reabrir un QR ya usado y dejar expirar una solicitud. Ninguno debe crear sesión.
8. Ejecuta `php artisan qr-google:revoke 2024-MED-5837` y confirma que el token anterior deja de servir y que un nuevo `sub` no puede vincularse sin intervención administrativa.

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

**Render Free, sin Shell.** En **Environment** del servicio Laravel, confirma `DEMO_SEED_ON_EMPTY=true` y carga dos valores privados:

- `DEMO_STUDENT_PASSWORD_HASHES` contiene un objeto JSON con exactamente los carnés `2024-CON-3189`, `2025-ISI-6473`, `2022-ARQ-2815` y `2023-AGR-9542`. Cada valor es un hash bcrypt distinto, con prefijo `$2y$` y coste de al menos 12, compatible con `BCRYPT_ROUNDS` del backend. El coste predeterminado es 12.
- `GOOGLE_ACCOUNT_LINKS` contiene las tres asignaciones privadas con los seis carnés protegidos de la tabla anterior.

Pega cada JSON crudo, en una sola línea y sin comillas exteriores. En un servicio existente, carga ambas variables manualmente y selecciona **Save only** para que entren en vigor junto con el despliegue posterior del código. Una entrada `sync: false` en el Blueprint solicita el valor durante la creación inicial. Mantén estos valores en el backend y fuera de Git y de las variables `VITE_*`. [Variables de entorno de Render](https://render.com/docs/configure-environment-variables) · [Secretos en Blueprints](https://render.com/docs/blueprint-spec#prompting-for-secret-values)

En cada arranque, `docker/start.sh` ejecuta:

```sh
php artisan migrate --force
php artisan demo:ensure-seeded
unset DEMO_STUDENT_PASSWORD_HASHES
php artisan qr-google:sync-links
```

`demo:ensure-seeded` valida el objeto antes de escribir y aplica los hashes al crear las cuatro cuentas en la misma transacción que siembra los datos. Si el JSON configurado es inválido, falla el arranque y la siembra queda sin aplicar. Si el valor está vacío, se conserva el aprovisionamiento previo con secretos aleatorios inaccesibles. En una base con datos de aplicación, el comando termina sin tocar cuentas ni contraseñas. El proceso HTTP se inicia sin la variable de hashes.

Render Free pierde el SQLite del contenedor al reiniciar, redesplegar o entrar en reposo. La siguiente inicialización recupera los datos ficticios y las cuatro credenciales configuradas. Los cambios hechos durante la sesión, incluidos los registros de uso y la vinculación local del identificador de Google, duran lo que dura ese SQLite. Las preautorizaciones se cargan de nuevo desde `GOOGLE_ACCOUNT_LINKS`. [Documentación de Render Free](https://render.com/docs/free)

**Base aislada con Shell (opcional).** En una copia aislada del proyecto, crea un archivo SQLite vacío. Desde `backend`, apunta `DB_DATABASE` a su ruta absoluta y confirma que corresponde a la base ficticia. Ejecuta:

```sh
php artisan migrate
php artisan db:seed
php artisan student:set-password 2024-CON-3189
```

Repite el último comando para las cuentas locales que participarán. El comando solicita una contraseña individual con entrada oculta y revoca los tokens de esa cuenta. Mantén las contraseñas fuera del repositorio y de las variables `VITE_*`. Para los carnés protegidos, configura en privado cuentas Google autorizadas para la demostración y ejecuta `php artisan qr-google:sync-links` en esa base. El QR real requiere que el teléfono alcance la URL HTTPS registrada. Prepara el acceso local con carné y contraseña para una defensa sin Internet. Prueba los accesos y una consulta pública antes de la presentación.

### Persistencia y capacidad

- **Defensa sin contratar servicios.** Ejecuta Laravel y React en el equipo de la presentación con el SQLite ficticio preparado y una copia comprobada. Render Free usa almacenamiento efímero y puede entrar en reposo. Sobre una base vacía y con `DEMO_SEED_ON_EMPTY=true`, Render aplica migraciones, siembra una vez y sincroniza los vínculos QR; en arranques posteriores con datos no vuelve a sembrar. [Documentación de Render Free](https://render.com/docs/free)
- **Si ya hay un disco compatible.** Un servicio Render puede montar el disco en `/var/www/html/storage/database` y conservar la ruta del Blueprint. Prepara el directorio para que `www-data` escriba los archivos SQLite y WAL. Crea copias con el mecanismo de copia de SQLite y comprueba la recuperación en otro archivo. El disco limita el servicio a una instancia y los despliegues pueden interrumpirlo. [Discos persistentes de Render](https://render.com/docs/disks)
- La pérdida de la base efímera también elimina sesiones, historial, revocaciones y contadores. El chat admite dos solicitudes simultáneas y aplica un máximo compartido de 60 por minuto y 500 al día. Cada intento de Groq tiene un máximo de cuatro segundos y la solicitud puede hacer un segundo intento. El servidor PHP se mantiene para baja concurrencia y la defensa.
