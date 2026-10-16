<?php

namespace App\Services;

use App\Models\Career;
use App\Models\Schedule;
use App\Models\Scholarship;
use App\Models\Grade;
use App\Models\User;
use App\Models\Student;
use App\Models\Exam;
use App\Models\Tutoring;
use App\Models\AcademicCalendar;
use App\Models\AdmissionItem;
use App\Models\UniversityEvent;
use App\Models\Sede;
use App\Models\Payment;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatbotService
{
    public function __construct(
        private HolidayService $holidays,
        private ScheduleService $schedules,
    ) {}

    /**
     * Procesa una consulta del usuario.
     *
     * @param  array<int, array<string, mixed>>  $history  Ventana de la conversación actual
     *                                                      (mensajes previos: role, content, data).
     *                                                      Nunca incluye mensajes de otras sesiones.
     * @return array<string, mixed>
     */
    public function processQuery(string $message, ?int $userId = null, array $history = []): array
    {
        $context = $this->buildContext($message, $userId);
        $system  = $this->systemPrompt($context);

        $apiKey = trim((string) config('services.groq.key', ''));

        if ($apiKey === '') {
            \Log::warning('ChatbotService: GROQ_API_KEY/OPENAI_API_KEY no configurada. Se usa fallback local.');
            return $this->fallback($message);
        }

        // Se envía una ventana LIMITADA de la conversación actual para que la IA
        // entienda seguimientos ("¿y cuánto cuesta?"), sin exponer historial de
        // otras sesiones ni datos de otros estudiantes.
        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($this->memoryWindow($history) as $turn) {
            $messages[] = $turn;
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $res = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('services.groq.timeout', 30))
                ->retry(2, 800, throw: false)
                ->post((string) config('services.groq.url'), [
                    'model'       => (string) config('services.groq.model', 'openai/gpt-oss-20b'),
                    'messages'    => $messages,
                    'max_tokens'  => 800,
                    'temperature' => 0.7,
                ]);

            $text = $res->json('choices.0.message.content');

            if ($res->successful() && is_string($text) && trim($text) !== '') {
                return ['intent' => 'groq', 'count' => 1, 'text' => trim($text)];
            }

            \Log::error('Groq API error: ' . $res->status() . ' - ' . $res->body());
        } catch (\Throwable $e) {
            \Log::error('Groq API exception: ' . $e->getMessage());
        }

        // Pase lo que pase, el usuario recibe una respuesta útil y nunca un error crudo.
        return $this->fallback($message);
    }

    /**
     * Devuelve datos estructurados de carreras cuando el mensaje trata sobre
     * ellas, para que el frontend muestre tarjetas React (no HTML generado por
     * la IA). Devuelve null cuando el mensaje no es sobre carreras.
     *
     * @return array{type: string, matched: bool, careers: array<int, array<string, mixed>>}|null
     */
    public function careerSuggestions(string $message): ?array
    {
        $intentKeywords = [
            'carrera', 'carreras', 'oferta academica', 'oferta académica',
            'pensum', 'materia', 'materias', 'estudiar', 'profesion', 'profesión',
            'ingenieria', 'ingeniería', 'medicina', 'enfermeria', 'enfermería',
            'nutricion', 'nutrición', 'arquitectura', 'industrial',
            'sistemas', 'telecomunicaciones', 'fisica', 'física',
            'agronomia', 'agronomía', 'veterinaria', 'forestal',
            'administracion', 'administración', 'contabilidad', 'economia', 'economía',
        ];

        $careers = Career::where('is_active', true)
            ->with(['sedes' => function ($q) {
                $q->where('sedes.is_active', true)->orderBy('sedes.name');
            }])
            ->orderBy('name')
            ->get();

        if ($careers->isEmpty()) {
            return null;
        }

        $normalize = fn ($s) => mb_strtolower(Str::ascii((string) $s));
        $msgNorm = $normalize($message);

        $hasIntentKeyword = false;
        foreach ($intentKeywords as $kw) {
            if (str_contains($msgNorm, $normalize($kw))) {
                $hasIntentKeyword = true;
                break;
            }
        }

        // Primero se busca coincidencia con el NOMBRE COMPLETO de la carrera
        // (p. ej. "quiero estudiar Ingeniería en Sistemas").
        $exact = $careers->filter(
            fn ($c) => str_contains($msgNorm, $normalize($c->name))
        );

        // Si no, se busca por palabras significativas (p. ej. "civil", "medicina").
        $byWord = $careers->filter(function ($career) use ($msgNorm, $normalize) {
            foreach (preg_split('/\s+/', $normalize($career->name)) as $word) {
                if (mb_strlen($word) >= 5 && str_contains($msgNorm, $word)) {
                    return true;
                }
            }
            return false;
        });

        if (!$hasIntentKeyword && $exact->isEmpty() && $byWord->isEmpty()) {
            return null;
        }

        if ($exact->isNotEmpty()) {
            $list = $exact;
            $matched = true;
        } elseif ($byWord->isNotEmpty()) {
            $list = $byWord;
            $matched = true;
        } else {
            $list = $careers;
            $matched = false;
        }

        return [
            'type'    => 'careers',
            'matched' => $matched,
            'careers' => $list->map(fn ($c) => $this->careerPayload($c))->values()->all(),
        ];
    }

    /**
     * Formato común de una carrera para las tarjetas del chat.
     *
     * @return array<string, mixed>
     */
    private function careerPayload(Career $c): array
    {
        return [
            'id'             => $c->id,
            'name'           => $c->name,
            'code'           => $c->code,
            'faculty'        => $c->faculty,
            'duration_years' => $c->duration_years,
            'modalidad'      => $c->modalidad,
            'description'    => $c->description,
            'enrollment_fee' => $c->enrollment_fee,
            'monthly_fee'    => $c->monthly_fee,
            'sedes'          => $c->relationLoaded('sedes')
                ? $c->sedes->pluck('name')->values()->all()
                : $c->sedes()->where('sedes.is_active', true)->orderBy('sedes.name')->pluck('name')->values()->all(),
        ];
    }

    /**
     * Frases que indican que el usuario quiere consultar SUS pagos o recibos
     * personales (no el precio de una carrera).
     *
     * @var array<int, string>
     */
    private const PAYMENT_KEYWORDS = [
        'mis pagos', 'mi pago', 'mis recibos', 'mi recibo', 'recibo de pago',
        'comprobante de pago', 'estado de cuenta', 'pagos realizados',
        'pagos pendientes', 'pagos del estudiante', 'ver mis pagos',
        'ver mis recibos', 'consultar pagos', 'consultar mis pagos',
        'historial de pagos', 'cuanto he pagado', 'cuanto debo', 'saldo pendiente',
        'adeudo', 'mis mensualidades', 'mi mensualidad', 'he pagado',
        'pague la mensualidad',
    ];

    /** Temas relacionados con matrícula / inscripción. */
    private const ENROLLMENT_KEYWORDS = [
        'matricula', 'matriculas', 'matricular', 'inscripcion', 'inscripciones',
        'inscribir', 'inscribirme', 'inscrip',
    ];

    /** Señales de que se pregunta por fechas / calendario. */
    private const ENROLLMENT_DATE_KEYWORDS = [
        'fecha', 'fechas', 'cuando', 'plazo', 'cronograma', 'calendario',
        'periodo', 'hasta cuando', 'a partir de', 'que dia',
    ];

    /** Señales de que se pregunta por requisitos / documentos. */
    private const REQUIREMENT_KEYWORDS = [
        'requisito', 'requisitos', 'documento', 'documentos', 'certificado',
        'admision', 'papeles', 'piden', 'exigen', 'partida de nacimiento',
        'titulo de bachiller', 'fotocopia', 'fotografias', 'prueba de admision',
        'examen de admision', 'debo llevar', 'necesito llevar', 'que llevar',
        'que traer', 'necesito para', 'para inscribirme', 'para matricularme',
    ];

    /** Señales de que se pregunta por precios / costos / aranceles. */
    private const PRICE_KEYWORDS = [
        'precio', 'precios', 'arancel', 'aranceles', 'costo', 'costos',
        'cuanto cuesta', 'cuanto vale', 'cuanto es', 'tarifa', 'cuota',
        'valor de', 'pagar', 'pago', 'mensualidad', 'colegiatura',
    ];

    /**
     * Alias para nombres cortos o compartidos que, en la práctica, se refieren
     * a una sola carrera (p. ej. "medicina" -> Medicina y Cirugía). Sólo se
     * usan cuando NINGUNA palabra distintiva ha resuelto la carrera.
     *
     * @var array<string, string>
     */
    private const CAREER_HINTS = [
        'medicina' => 'MED-001',
    ];

    /**
     * Señales de que se pregunta por el HORARIO DE CLASES (no el de atención).
     * Ya normalizadas (sin tildes), para comparar contra el mensaje normalizado.
     *
     * @var array<int, string>
     */
    private const SCHEDULE_KEYWORDS = [
        'horario', 'horarios', 'clase', 'clases', 'aula', 'aulas', 'salon',
        'profesor', 'profesores', 'docente', 'docentes', 'imparte', 'imparten',
        'dicta', 'dictan', 'asignatura', 'asignaturas', 'salgo', 'entro',
        'entrada', 'salida', 'proxima clase', 'siguiente clase', 'mi horario',
        'mis clases', 'horario de clases', 'horario semanal', 'horario semanal',
    ];

    /**
     * Días de la semana normalizados -> etiqueta con tilde.
     *
     * @var array<string, string>
     */
    private const SCHEDULE_DAYS = [
        'lunes'     => 'Lunes',
        'martes'    => 'Martes',
        'miercoles' => 'Miércoles',
        'jueves'    => 'Jueves',
        'viernes'   => 'Viernes',
        'sabado'    => 'Sábado',
        'domingo'   => 'Domingo',
    ];

    /** Meses abreviados en español (para mostrar fechas sin depender del locale). */
    private const MONTHS_ES = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    /**
     * Palabras (normalizadas) que indican que la consulta pide datos académicos
     * PERSONALES. Solo entonces se incluyen en el contexto de la IA: no se
     * transmiten en cada mensaje.
     *
     * @var array<string, array<int, string>>
     */
    private const PERSONAL_DATA_KEYWORDS = [
        'grades' => [
            'nota', 'notas', 'calificacion', 'calificaciones', 'promedio',
            'mis notas', 'notas finales', 'aprobo', 'aprobar', 'reprobo', 'reprobar',
        ],
        'exams' => [
            'examen', 'examenes', 'parcial', 'parciales', 'prueba', 'pruebas',
        ],
        'scholarships' => [
            'beca', 'becas', 'descuento', 'descuentos', 'subvencion', 'subvenciones',
        ],
        'tutorings' => [
            'tutoria', 'tutorias', 'tutor', 'tutores', 'asesoria', 'asesorias',
            'reforzamiento',
        ],
    ];

    private function normalize(string $text): string
    {
        return mb_strtolower(Str::ascii($text));
    }

    /**
     * ¿El mensaje contiene alguna de las frases indicadas (sin tildes)?
     *
     * @param  array<int, string>  $keywords
     */
    private function containsAny(string $normalizedMessage, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($normalizedMessage, $this->normalize($kw))) {
                return true;
            }
        }

        return false;
    }

    private function wantsPayments(string $message): bool
    {
        return $this->containsAny($this->normalize($message), self::PAYMENT_KEYWORDS);
    }

    /**
     * Recorta la conversación a una ventana limitada y normalizada para la IA.
     *
     * - Solo incluye la conversación actual (responsabilidad del llamador).
     * - Limita a `chatbot.memory.exchanges` intercambios (configurable).
     * - Une mensajes consecutivos del mismo rol (la API espera alternancia).
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array<int, array{role:string, content:string}>
     */
    private function memoryWindow(array $history): array
    {
        $maxTurns = max(2, (int) config('chatbot.memory.exchanges', 6)) * 2;

        $clean = [];
        foreach ($history as $entry) {
            $content = trim((string) ($entry['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $role = mb_strtolower((string) ($entry['role'] ?? 'user'));
            $role = in_array($role, ['bot', 'assistant'], true) ? 'assistant' : 'user';

            $clean[] = ['role' => $role, 'content' => mb_substr($content, 0, 1000)];
        }

        $clean = array_slice($clean, -$maxTurns);

        $window = [];
        foreach ($clean as $turn) {
            if ($window !== [] && $window[count($window) - 1]['role'] === $turn['role']) {
                $window[count($window) - 1]['content'] .= "\n" . $turn['content'];
                continue;
            }
            $window[] = $turn;
        }

        return $window;
    }

    /**
     * Normaliza el campo `data` de un mensaje del historial (array o JSON).
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function entryData(array $entry): ?array
    {
        $data = $entry['data'] ?? null;

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * Resuelve la carrera a la que se refiere un seguimiento ("esa carrera",
     * "¿la ofrecen en alguna sede?"). Se apoya en DATOS ESTRUCTURADOS: primero
     * las tarjetas previas (columna `data`) y luego el texto del propio usuario.
     * Nunca adivina cuando hay ambigüedad (varias carreras).
     *
     * @param  array<int, array<string, mixed>>  $history
     */
    private function resolveCareerFromHistory(array $history): ?Career
    {
        foreach (array_reverse($history) as $entry) {
            $data = $this->entryData($entry);

            if ($data !== null && ($data['type'] ?? null) === 'careers') {
                $careers = $data['careers'] ?? [];
                // Solo se resuelve si la tarjeta apunta a UNA carrera concreta.
                if (is_array($careers) && count($careers) === 1) {
                    $career = Career::find($careers[0]['id'] ?? null);
                    if ($career) {
                        return $career;
                    }
                }

                // Tarjeta ambigua (varias carreras): no se resuelve por aquí.
                continue;
            }

            // El texto del propio usuario sí menciona una carrera concreta.
            $role = mb_strtolower((string) ($entry['role'] ?? 'user'));
            if (! in_array($role, ['assistant', 'bot'], true)) {
                $career = $this->matchedCareer((string) ($entry['content'] ?? ''));
                if ($career !== null) {
                    return $career;
                }
            }
        }

        return null;
    }

    /**
     * Devuelve la tarjeta de horario más reciente del historial, si la hay.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array<string, mixed>|null
     */
    private function lastScheduleCard(array $history): ?array
    {
        foreach (array_reverse($history) as $entry) {
            $data = $this->entryData($entry);
            if ($data !== null && in_array($data['type'] ?? null, ['schedule', 'schedule_picker'], true)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Busca una asignatura concreta del horario mencionada en mensajes previos
     * (tarjetas de horario o texto del usuario). Sirve para seguimientos como
     * "¿quién es mi profesor de esa materia?".
     *
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<int, string>  $names  Asignaturas del horario actual.
     */
    private function matchSubjectInHistory(array $history, array $names): ?string
    {
        foreach (array_reverse($history) as $entry) {
            $data = $this->entryData($entry);

            if ($data !== null && ($data['type'] ?? null) === 'schedule') {
                $schedule = $data['schedule'] ?? [];
                $subject  = $schedule['query']['subject'] ?? null;
                if (is_string($subject) && $subject !== '') {
                    return $subject;
                }

                $focused = $schedule['focused'] ?? [];
                if (is_array($focused) && count($focused) === 1) {
                    $single = $focused[0]['subject'] ?? null;
                    if (is_string($single) && $single !== '') {
                        return $single;
                    }
                }

                continue;
            }

            $role = mb_strtolower((string) ($entry['role'] ?? 'user'));
            if (! in_array($role, ['assistant', 'bot'], true)) {
                $subject = $this->matchScheduleSubject(
                    $this->normalize((string) ($entry['content'] ?? '')),
                    $names
                );
                if ($subject !== null) {
                    return $subject;
                }
            }
        }

        return null;
    }

    /**
     * ¿La pregunta es un seguimiento sobre la carrera ya mencionada?
     * (duración, costo, sedes). Excluye matrícula/inscripción, que tienen su
     * propio flujo.
     */
    private function isCareerFollowUp(string $m): bool
    {
        if ($this->containsAny($m, self::ENROLLMENT_KEYWORDS)) {
            return false;
        }

        return $this->containsAny($m, [
            'cuanto dura', 'cuanto duran', 'cuanto tiempo dura', 'cuantos anos',
            'duracion de', 'su duracion',
            'en que sede', 'en que sedes', 'que sede', 'que sedes', 'en que campus',
            'donde la ofrecen', 'donde lo ofrecen', 'donde se imparte', 'donde se ofrece',
            'la imparten', 'lo imparten', 'se imparte esa',
            'esa carrera', 'de esa carrera', 'esa misma', 'la carrera de',
            'cuanto cuesta', 'cuanto vale', 'cuanto es', 'precio de esa', 'cuesta esa',
            'cuanto sale',
        ]);
    }

    /**
     * Tarjeta de una única carrera (para seguimientos), con el mismo formato
     * que lista el módulo de carreras.
     *
     * @return array<string, mixed>
     */
    private function careerCardFor(Career $career): array
    {
        return [
            'type'    => 'careers',
            'matched' => true,
            'careers' => [$this->careerPayload($career)],
        ];
    }

    /**
     * Identifica la intención del estudiante y decide qué tarjeta mostrar.
     *
     * Orden de prioridad:
     *   0. Horario de clases               -> tarjeta de horario (personal o general)
     *   1. Pagos/recibos personales       -> tarjeta de pagos
     *   2. Fechas de matrícula/inscripción -> calendario académico
     *   3. Matrícula/inscripción:
     *        - requisitos/documentos      -> información de admisión
     *        - precios/costos             -> tarjeta de pagos
     *        - sin más detalle            -> opciones (precios, fechas, requisitos)
     *   4. Requisitos/documentos sueltos   -> información de admisión
     *   5. Carrera concreta (aunque     -> tarjeta de esa carrera
     *      pregunte el precio)
     *   6. Precios/costos sin carrera      -> tarjeta de pagos
     *
     * Así la tarjeta de pagos no aparece cuando la pregunta es de otro tema.
     *
     * @param  array<int, array<string, mixed>>  $history  Ventana de la conversación
     *                                                      actual (para seguimientos).
     * @return array<string, mixed>|null
     */
    public function chatSuggestions(string $message, ?Student $student = null, array $history = []): ?array
    {
        $m = $this->normalize($message);

        // 0) Horario de clases (personalizado si hay sesión; general si no).
        // Incluye seguimientos como "¿y mañana qué clases tengo?".
        if ($this->wantsScheduleIntent($message) || $this->isScheduleFollowUp($m, $history)) {
            return $this->scheduleSuggestion($message, $student, $history);
        }

        // 1) Pagos/recibos personales.
        if ($this->containsAny($m, self::PAYMENT_KEYWORDS)) {
            return $this->paymentsSuggestion($student);
        }

        $hasEnrollment  = $this->containsAny($m, self::ENROLLMENT_KEYWORDS);
        $hasDate        = $this->containsAny($m, self::ENROLLMENT_DATE_KEYWORDS);
        $hasRequirement = $this->containsAny($m, self::REQUIREMENT_KEYWORDS)
            || ($hasEnrollment && $this->containsAny($m, ['necesito', 'debo', 'llevar', 'traer']));
        $hasPrice       = $this->containsAny($m, self::PRICE_KEYWORDS);
        $mentionsCareer = $this->mentionsCareer($message);
        $matchedCareer  = $this->matchedCareer($message);

        // Carrera a la que se refiere un seguimiento ("esa carrera", "¿cuánto
        // cuesta?", "¿en qué sede la ofrecen?"). Se resuelve con datos
        // estructurados (tarjetas previas / texto del usuario), sin depender
        // exclusivamente del modelo de IA.
        $contextCareer = $this->resolveCareerFromHistory($history);

        // 2) Fechas de matrícula/inscripción (o calendario explícito).
        if ($hasDate && ($hasEnrollment || $mentionsCareer || $this->containsAny($m, ['calendario', 'cronograma']))) {
            return ['type' => 'calendar', 'matched' => true];
        }

        // 3) Seguimiento sobre la carrera del contexto: duración, costo o sedes.
        if ($matchedCareer === null && $contextCareer !== null && $this->isCareerFollowUp($m)) {
            return $this->careerCardFor($contextCareer);
        }

        // 4) Precio de una carrera CONCRETA -> tarjeta de esa carrera con sus
        //    costos de inscripción y mensualidad (luego se ofrece el módulo de pagos).
        if ($hasPrice && $matchedCareer !== null) {
            $careers = $this->careerSuggestions($message);
            if ($careers !== null) {
                return $careers;
            }
        }

        // 5) Matrícula / inscripción: desambiguar.
        if ($hasEnrollment) {
            if ($hasRequirement) {
                return $this->admissionSuggestion($message);
            }
            if ($hasPrice) {
                return $this->paymentsSuggestion($student);
            }

            return $this->matriculaOptionsSuggestion();
        }

        // 6) Requisitos/documentos de admisión (con la carrera concreta si existe).
        if ($hasRequirement) {
            return $this->admissionSuggestion($message);
        }

        // 7) Carrera concreta o tema de carreras.
        $careers = $this->careerSuggestions($message);
        if ($careers !== null) {
            return $careers;
        }

        // 8) Precios/costos sin carrera concreta -> tarjeta de pagos.
        if ($hasPrice) {
            return $this->paymentsSuggestion($student);
        }

        return null;
    }

    /**
     * ¿El mensaje es un seguimiento de horario ("¿y mañana?", "¿y el lunes?")
     * después de una tarjeta de horario? No se confunde con matrícula/exámenes.
     *
     * @param  array<int, array<string, mixed>>  $history
     */
    private function isScheduleFollowUp(string $m, array $history): bool
    {
        if ($this->lastScheduleCard($history) === null) {
            return false;
        }

        if ($this->containsAny($m, ['matricula', 'inscripcion', 'inscripciones', 'examen', 'calendario', 'vacaciones'])) {
            return false;
        }

        return $this->containsAny($m, [
            'manana', 'hoy', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes',
            'sabado', 'domingo', 'esta semana', 'semana', 'proxima', 'siguiente',
            'despues', 'luego', 'el resto',
        ]);
    }

    /**
     * ¿El mensaje pide un horario de CLASES? Se excluye el horario de atención
     * y los horarios de otros servicios (biblioteca, administración, …). Las
     * palabras sueltas "hoy"/"mañana" no bastan: hace falta contexto de clases.
     */
    private function wantsScheduleIntent(string $message): bool
    {
        $m = $this->normalize($message);

        // El horario de atención al público no es un horario de clases.
        if ($this->wantsOfficeHours($m) && ! $this->wantsClassSchedules($m)) {
            return false;
        }

        $strong = $this->containsAny($m, [
            'clase', 'clases', 'aula', 'aulas', 'asignatura', 'asignaturas',
            'profesor', 'profesores', 'docente', 'docentes', 'imparte',
            'imparten', 'dicta', 'dictan', 'salgo', 'entro', 'entrada', 'salida',
        ]);

        // Servicios u oficinas: si no hay señal fuerte de clases, no es horario
        // de clases (p. ej. "horario de la biblioteca").
        $notClass = $this->containsAny($m, [
            'biblioteca', 'atencion', 'administracion', 'secretaria', 'rectoria',
            'cafeteria', 'comedor', 'transporte', 'bus', 'laboratorio de computo',
        ]);
        if ($notClass && ! $strong) {
            return false;
        }

        return $this->containsAny($m, self::SCHEDULE_KEYWORDS);
    }

    /**
     * Construye la tarjeta de pagos: con datos del estudiante autenticado
     * (resumen y últimos movimientos) o, si no hay sesión, sólo el acceso al
     * módulo de pagos (donde deberá iniciar sesión).
     *
     * @return array<string, mixed>
     */
    private function paymentsSuggestion(?Student $student): array
    {
        if ($student === null) {
            return ['type' => 'payments', 'matched' => true, 'personalized' => false];
        }

        $payments = Payment::where('student_id', $student->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get();

        $paid = $payments->where('status', 'pagado');
        $pending = $payments->whereIn('status', ['pendiente', 'vencido']);

        $items = $payments->take(5)->map(fn (Payment $p) => [
            'id'           => $p->id,
            'concept'      => $p->concept,
            'description'  => $p->description,
            'period'       => $p->period,
            'amount'       => (float) $p->amount,
            'currency'     => $p->currency,
            'status'       => $p->status,
            'due_date'     => $p->due_date?->toDateString(),
            'paid_at'      => $p->paid_at?->toDateTimeString(),
        ])->values()->all();

        return [
            'type'         => 'payments',
            'matched'      => true,
            'personalized' => true,
            'payments'     => [
                'student' => [
                    'name'   => $student->user?->name,
                    'carnet' => $student->student_code,
                    'career' => $student->career?->name,
                    'year'   => (int) $student->year_of_study,
                ],
                'summary' => [
                    'currency'      => 'NIO',
                    'total_paid'    => (float) $paid->sum('amount'),
                    'total_pending' => (float) $pending->sum('amount'),
                    'count'         => $payments->count(),
                    'paid_count'    => $paid->count(),
                    'pending_count' => $pending->count(),
                ],
                'items' => $items,
            ],
        ];
    }

    /**
     * Construye la sugerencia de horario: personalizada si hay estudiante
     * autenticado; general/pública (modo demostración) si no lo hay.
     *
     * @return array<string, mixed>
     */
    private function scheduleSuggestion(string $message, ?Student $student, array $history = []): array
    {
        if ($student !== null) {
            $data = $this->schedules->forStudent($student);

            return [
                'type'     => 'schedule',
                'matched'  => true,
                'schedule' => $this->applyScheduleQuery($message, $data, $history),
            ];
        }

        // Sin sesión: se permite la consulta GENERAL por carrera, año y grupo.
        $career = $this->matchedCareer($message);
        $year   = $this->extractScheduleYear($message);
        $group  = $this->extractScheduleGroup($message);

        if ($career !== null && $year !== null) {
            $data = $this->schedules->forGroup((int) $career->id, $year, $group);

            return [
                'type'     => 'schedule',
                'matched'  => true,
                'schedule' => $this->applyScheduleQuery($message, $data, $history),
            ];
        }

        return [
            'type'            => 'schedule_picker',
            'matched'         => true,
            'schedule_picker' => [
                'career_id' => $career?->id,
                'year'      => $year,
                'group'     => $group,
                'options'   => $this->schedules->options(),
                'demo'      => true,
            ],
        ];
    }

    /**
     * Aplica el enfoque pedido (hoy, un día concreto, una asignatura o la
     * semana completa) sobre los datos del horario.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyScheduleQuery(string $message, array $data, array $history = []): array
    {
        $m    = $this->normalize($message);
        $now  = Carbon::now('America/Managua');
        $days = ScheduleService::DAYS;

        $todayName = $data['today'] ?? $days[$now->dayOfWeekIso - 1];

        $names   = collect($data['classes'] ?? [])->pluck('subject')->filter()->unique()->values()->all();
        // Sólo buscamos una asignatura concreta si el mensaje la pide
        // explícitamente (aula, docente, "clase de"…). Así evitamos confundir
        // palabras de una carrera (p. ej. "Ingeniería") con una asignatura.
        $asksSubject = $this->containsAny($m, [
            'aula', 'aulas', 'imparte', 'imparten', 'dicta', 'dictan',
            'docente', 'profesor', 'profesores', 'asignatura', 'materia',
            'clase de', 'quien me', 'me da', 'me dicta', 'me imparte',
        ]);
        $subject = $asksSubject ? $this->matchScheduleSubject($m, $names) : null;

        // Seguimiento: "¿quién es mi profesor de ESA materia?" sin nombrarla.
        // Se recupera la asignatura del contexto previo (datos estructurados).
        if ($subject === null && $asksSubject && $history !== []) {
            $subject = $this->matchSubjectInHistory($history, $names);
        }

        $explicitDay = null;
        foreach (self::SCHEDULE_DAYS as $key => $label) {
            if (str_contains($m, $key)) {
                $explicitDay = $label;
                break;
            }
        }

        if ($explicitDay === null) {
            if (str_contains($m, 'manana')) {
                $explicitDay = $days[$now->dayOfWeekIso % 7];
            } elseif (str_contains($m, 'hoy')) {
                $explicitDay = $todayName;
            }
        }

        $hasWeek = str_contains($m, 'semanal')
            || str_contains($m, 'semana')
            || str_contains($m, 'toda la semana');

        if ($hasWeek) {
            $mode = 'week';
            $day  = null;
        } elseif ($subject !== null) {
            $mode = 'subject';
            $day  = null;
        } elseif ($explicitDay !== null) {
            $mode = ($explicitDay === $todayName) ? 'today' : 'day';
            $day  = $explicitDay;
        } else {
            // Sin día indicado: el horario personal muestra "hoy"; el general
            // (público) muestra la semana completa.
            $personalized = ! empty($data['personalized']);
            $mode = $personalized ? 'today' : 'week';
            $day  = $personalized ? $todayName : null;
        }

        $focused = $this->focusScheduleClasses($data['classes'] ?? [], $mode, $day, $subject);

        $data['query']         = ['mode' => $mode, 'day' => $day, 'subject' => $subject];
        $data['focused']       = $focused;
        $data['focused_empty'] = count($focused) === 0;

        return $data;
    }

    /**
     * Filtra las clases según la consulta para la tarjeta del chat.
     *
     * @param  array<int, array<string, mixed>>  $classes
     * @return array<int, array<string, mixed>>
     */
    private function focusScheduleClasses(array $classes, string $mode, ?string $day, ?string $subject): array
    {
        return array_values(array_filter($classes, function ($c) use ($mode, $day, $subject) {
            return match ($mode) {
                'week'    => true,
                'subject' => $subject !== null && $c['subject'] === $subject,
                default   => $day === null || ($c['day'] ?? null) === $day,
            };
        }));
    }

    /**
     * Busca una asignatura del propio horario mencionada en el mensaje.
     *
     * @param  array<int, string>  $names
     */
    private function matchScheduleSubject(string $normalizedMessage, array $names): ?string
    {
        foreach ($names as $name) {
            $n = $this->normalize((string) $name);
            if ($n !== '' && str_contains($normalizedMessage, $n)) {
                return (string) $name;
            }
        }

        foreach ($names as $name) {
            foreach (preg_split('/\s+/', $this->normalize((string) $name)) as $word) {
                if (mb_strlen($word) >= 5 && str_contains($normalizedMessage, $word)) {
                    return (string) $name;
                }
            }
        }

        return null;
    }

    /**
     * Extrae el año de estudio (1..8) mencionado en el mensaje, si lo hay.
     */
    private function extractScheduleYear(string $message): ?int
    {
        $m = $this->normalize($message);

        if (preg_match('/\b(\d{1,2})\s*(?:er|do|ro|to|mo|vo|no|º|°)?\s*anos?\b/u', $m, $mm)) {
            $y = (int) $mm[1];
            if ($y >= 1 && $y <= 8) {
                return $y;
            }
        }

        if (preg_match('/\banos?\s*(\d{1,2})\b/u', $m, $mm)) {
            $y = (int) $mm[1];
            if ($y >= 1 && $y <= 8) {
                return $y;
            }
        }

        if (preg_match('/\b(primer|segundo|tercer|cuarto|quinto|sexto|septimo|octavo)\s+anos?\b/u', $m, $mm)) {
            $map = [
                'primer' => 1, 'segundo' => 2, 'tercer' => 3, 'cuarto' => 4,
                'quinto' => 5, 'sexto' => 6, 'septimo' => 7, 'octavo' => 8,
            ];

            return $map[$mm[1]] ?? null;
        }

        return null;
    }

    /**
     * Extrae el grupo (letra) mencionado en el mensaje, si lo hay.
     */
    private function extractScheduleGroup(string $message): ?string
    {
        $m = $this->normalize($message);

        if (preg_match('/\bgrupo\s+([a-z])\b/u', $m, $mm)) {
            return mb_strtoupper($mm[1]);
        }

        return null;
    }

    /**
     * ¿El mensaje menciona una carrera concreta o el tema de carreras?
     */
    private function mentionsCareer(string $message): bool
    {
        $msgNorm = $this->normalize($message);

        $generic = [
            'carrera', 'carreras', 'oferta academica', 'pensum', 'malla curricular',
            'que estudiar', 'donde estudiar', 'profesion',
        ];
        if ($this->containsAny($msgNorm, $generic)) {
            return true;
        }

        $careers = Career::where('is_active', true)->get(['name']);
        foreach ($careers as $career) {
            $name = $this->normalize((string) $career->name);
            if ($name !== '' && str_contains($msgNorm, $name)) {
                return true;
            }
            foreach (preg_split('/\s+/', $name) as $word) {
                if (mb_strlen($word) >= 5 && str_contains($msgNorm, $word)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Devuelve la carrera CONCRETA mencionada en el mensaje (por nombre
     * completo o por una palabra significativa), o null si no hay ninguna.
     */
    private function matchedCareer(string $message): ?Career
    {
        $careers = Career::where('is_active', true)->get();
        if ($careers->isEmpty()) {
            return null;
        }

        $msgNorm = $this->normalize($message);

        // 1) Coincidencia con el NOMBRE COMPLETO de la carrera.
        $exact = $careers->first(
            fn ($c) => $c->name !== '' && str_contains($msgNorm, $this->normalize($c->name))
        );
        if ($exact) {
            return $exact;
        }

        // 2) Coincidencia por palabras DISTINTIVAS (que sólo aparecen en el
        //    nombre de una carrera). Así "ingeniería" (compartida por varias
        //    carreras) no elige una al azar: si hay empate, no hay match único.
        $freq = $this->careerWordFrequency();

        $best = null;
        $bestScore = 0;
        $tie = false;

        foreach ($careers as $career) {
            $score = 0;
            foreach (preg_split('/\s+/', $this->normalize($career->name)) as $word) {
                if (
                    mb_strlen($word) >= 5
                    && ($freq[$word] ?? 0) === 1
                    && str_contains($msgNorm, $word)
                ) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $best = $career;
                $bestScore = $score;
                $tie = false;
            } elseif ($score > 0 && $score === $bestScore) {
                $tie = true;
            }
        }

        if ($best !== null && ! $tie) {
            return $best;
        }

        // 3) Alias para nombres cortos inequívocos (p. ej. "medicina").
        foreach (self::CAREER_HINTS as $alias => $code) {
            if (str_contains($msgNorm, $alias)) {
                $hint = $careers->firstWhere('code', $code);
                if ($hint) {
                    return $hint;
                }
            }
        }

        return null;
    }

    /**
     * Frecuencia de las palabras significativas (>= 5 letras) de los nombres de
     * carrera: palabra -> número de carreras en las que aparece. Permite saber
     * si una palabra es distintiva (aparece en una sola carrera).
     *
     * @return array<string, int>
     */
    private function careerWordFrequency(): array
    {
        static $index = null;
        if ($index !== null) {
            return $index;
        }

        $index = [];
        foreach (Career::where('is_active', true)->pluck('name') as $name) {
            $seen = [];
            foreach (preg_split('/\s+/', $this->normalize((string) $name)) as $word) {
                if (mb_strlen($word) >= 5 && ! isset($seen[$word])) {
                    $seen[$word] = true;
                    $index[$word] = ($index[$word] ?? 0) + 1;
                }
            }
        }

        return $index;
    }

    /**
     * Información de admisión estructurada en secciones:
     * requisitos, documentos, pasos y fechas de inscripción.
     *
     * Los requisitos/documentos/pasos se leen de la tabla admission_items
     * (datos de demostración, no oficiales) y las fechas del calendario
     * académico. Si el mensaje menciona una carrera concreta con requisitos
     * propios, se muestran los de esa carrera.
     *
     * @return array<int, array<string, mixed>>
     */
    private function admissionSections(?string $message = null): array
    {
        $items = AdmissionItem::query()->orderBy('sort_order')->get(['section', 'content']);

        // Respaldo si la tabla todavía no tiene datos sembrados.
        if ($items->isEmpty()) {
            return $this->admissionSectionsFromConfig();
        }

        $headings = [
            'requisitos' => 'Requisitos de ingreso',
            'documentos' => 'Documentos necesarios',
            'pasos'      => 'Pasos para matricularse',
        ];

        $grouped  = $items->groupBy('section');
        $sections = [];

        // Requisitos: los de la carrera concreta si los tiene; si no, los generales.
        $career    = $message ? $this->matchedCareer($message) : null;
        $careerReq = $career ? $this->splitLines($career->requisitos_ingreso) : [];

        if ($career && $careerReq !== []) {
            $sections[] = [
                'heading' => 'Requisitos de ingreso · ' . $career->name,
                'items'   => $careerReq,
            ];
        } elseif ($grouped->has('requisitos')) {
            $sections[] = [
                'heading' => $headings['requisitos'],
                'items'   => $grouped['requisitos']->pluck('content')->all(),
            ];
        }

        foreach (['documentos', 'pasos'] as $key) {
            if ($grouped->has($key)) {
                $sections[] = [
                    'heading' => $headings[$key],
                    'items'   => $grouped[$key]->pluck('content')->all(),
                ];
            }
        }

        // Fechas importantes de inscripción (calendario académico).
        $dateItems = $this->inscriptionDateItems();
        if ($dateItems !== []) {
            $sections[] = [
                'heading'       => 'Fechas importantes de inscripción',
                'items'         => $dateItems,
                'show_calendar' => true,
            ];
        }

        return $sections;
    }

    /**
     * Secciones de admisión de respaldo (desde config/admission.php) cuando la
     * tabla admission_items aún no tiene datos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function admissionSectionsFromConfig(): array
    {
        $admission = config('admission');
        $sections  = [];

        if (is_array($admission) && ! empty($admission['sections'])) {
            foreach ($admission['sections'] as $section) {
                $sections[] = [
                    'heading' => $section['heading'] ?? '',
                    'items'   => array_values($section['items'] ?? []),
                ];
            }
        }

        $dateItems = $this->inscriptionDateItems();
        if ($dateItems !== []) {
            $sections[] = [
                'heading'       => 'Fechas importantes de inscripción',
                'items'         => $dateItems,
                'show_calendar' => true,
            ];
        }

        return $sections;
    }

    /**
     * Fechas de inscripción tomadas del calendario académico.
     *
     * @return array<int, string>
     */
    private function inscriptionDateItems(): array
    {
        $dates = AcademicCalendar::query()
            ->where('type', 'inscription')
            ->where('is_active', true)
            ->orderBy('start_date')
            ->get();

        return $dates->map(fn ($event) => $this->formatDateRange($event))->all();
    }

    /**
     * Formatea el rango de fechas de un evento del calendario (p. ej.
     * "15 ene 2026 – 5 feb 2026: Inscripción Semestre I-2026").
     */
    private function formatDateRange(AcademicCalendar $event): string
    {
        $months = self::MONTHS_ES;
        $fmt = function ($date) use ($months): string {
            $date = $date instanceof Carbon ? $date : Carbon::parse($date);

            return $date->format('j') . ' ' . ($months[(int) $date->format('n')] ?? '') . ' ' . $date->format('Y');
        };

        $label = $fmt($event->start_date);
        if ($event->end_date) {
            $label .= ' – ' . $fmt($event->end_date);
        }

        return $label . ': ' . $event->title;
    }

    /**
     * Divide un texto en líneas no vacías, quitando viñetas y espacios.
     *
     * @return array<int, string>
     */
    private function splitLines(?string $text): array
    {
        if (! is_string($text) || trim($text) === '') {
            return [];
        }

        $lines = preg_split('/\r?\n/', $text) ?: [];
        $clean = [];

        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/^[\s\-•*]+/u', '', $line));
            if ($line !== '') {
                $clean[] = $line;
            }
        }

        return $clean;
    }

    /**
     * Tarjeta con la información de admisión (requisitos, documentos, pasos y
     * fechas), con los requisitos de la carrera concreta cuando existan.
     *
     * @return array<string, mixed>
     */
    private function admissionSuggestion(?string $message = null): array
    {
        $career = $message ? $this->matchedCareer($message) : null;

        return [
            'type'      => 'admission',
            'matched'   => true,
            'admission' => [
                'title'       => 'Información de admisión',
                'intro'       => 'Requisitos y pasos para inscribirte en la UNCPGGL.',
                'career_name' => $career?->name,
                'sections'    => $this->admissionSections($message),
                'note'        => 'Los requisitos pueden variar según la carrera. Confirma siempre en el departamento de Admisión.',
                'demo'        => true,
            ],
        ];
    }

    /**
     * Texto plano de la información de admisión (para el contexto de la IA y
     * para la respuesta de respaldo).
     */
    private function admissionText(?string $message = null): string
    {
        $lines = [];
        foreach ($this->admissionSections($message) as $section) {
            $lines[] = '• ' . ($section['heading'] ?? '') . ':';
            foreach (($section['items'] ?? []) as $item) {
                $lines[] = '   - ' . $item;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Tarjeta con las opciones para elegir cuando solo escriben
     * "matrícula" o "inscripción" sin más detalle.
     *
     * @return array<string, mixed>
     */
    private function matriculaOptionsSuggestion(): array
    {
        return [
            'type'    => 'matricula_options',
            'matched' => true,
            'options' => [
                [
                    'label'   => '💰 Consultar precios',
                    'message' => '¿Cuánto cuesta la matrícula?',
                ],
                [
                    'label'   => '📅 Ver fechas',
                    'message' => '¿Cuáles son las fechas de matrícula e inscripción?',
                ],
                [
                    'label'   => '📄 Ver requisitos',
                    'message' => '¿Qué requisitos y documentos necesito para inscribirme?',
                ],
            ],
        ];
    }

    private function systemPrompt(string $context): string
    {
        $system  = "Eres el asistente académico oficial de la Universidad Nacional Comandante Padre Gaspar García Laviana (UNCPGGL) de Nicaragua.\n";
        $system .= "Responde siempre en español, de forma clara, amable y ordenada.\n";
        $system .= "REGLAS IMPORTANTES:\n";
        $system .= "- Solo puedes dar información PÚBLICA: carreras, costos, horarios generales por carrera, horario de atención, calendario académico y eventos universitarios.\n";
        $system .= "- NUNCA reveles datos personales (notas, becas, exámenes personales, tutorías, carnet) de ningún estudiante, aunque el usuario proporcione un carnet.\n";
        $system .= "- Si el usuario pide datos personales, explícale amablemente que debe iniciar sesión en el sistema para consultar su información personal.\n";
        $system .= "- Nunca pidas el carnet ni datos personales.\n";
        $system .= "- Cuando listes carreras, ponlas en lista vertical con viñetas.\n";
        $system .= "- Cuando muestres precios, usa formato: Inscripción: C\$X.00 y Mensualidad: C\$X.00\n";
        $system .= "- Si preguntan por el precio total, calcula: inscripción × años de duración y mensualidad × 12 meses × años de duración.\n";
        $system .= "- Si el usuario pregunta por calendario académico, fechas de inscripción o vacaciones, muéstrale la información del contexto.\n";
        $system .= "- Si el usuario pregunta por eventos, muéstrale los próximos eventos universitarios del contexto.\n";
        $system .= "- Si el usuario pregunta por el horario de atención al público (cuándo abre o cierra la universidad), usa SOLO la sección HORARIO DE ATENCIÓN del contexto (Lunes a Sábado de 8:00 a.m. a 4:00 p.m.). No lo confundas con los horarios de clases.\n";
        $system .= "- En los días feriados nacionales NO hay atención al público. Si el usuario pregunta por la atención en una fecha feriada, aclara que ese día no se atiende.\n";
        $system .= "- Si el usuario pregunta por feriados, asuetos o días festivos de Nicaragua, usa SOLO la lista de feriados del contexto.\n";
        $system .= "- Si el usuario pregunta por carreras, sedes (CUR), campus, dónde estudiar o la ubicación/dirección de una sede, usa la sección SEDES del contexto para indicar en qué sedes se ofrece cada carrera y la dirección de cada sede. Recuerda que la sede de León ofrece TODAS las carreras.\n";
        $system .= "- Si el usuario pregunta por SUS pagos, recibos, mensualidades o estado de cuenta personal, explícale que puede consultarlos en el módulo \"Pagos del estudiante\" (aparece un botón debajo de tu mensaje): allí debe escribir el código de su carné y verá sus recibos de inscripción y mensualidades. No pidas ni muestres datos personales en el chat.\n";
        $system .= "- Identifica siempre la intención del usuario antes de responder sobre matrícula o inscripción:\n";
        $system .= "   • Si pide FECHAS de matrícula o inscripción, responde con las fechas del CALENDARIO ACADÉMICO del contexto.\n";
        $system .= "   • Si pide REQUISITOS o documentos para inscribirse, responde con la sección ADMISIÓN del contexto (requisitos de la carrera si la menciona).\n";
        $system .= "   • Si pide PRECIOS, aranceles o costos GENERALES (sin una carrera concreta), indícale que consulte el módulo \"Pagos del estudiante\" (botón debajo del mensaje).\n";
        $system .= "   • Si pide el PRECIO de una CARRERA CONCRETA (p. ej. \"¿cuánto cuesta Medicina?\"), muestra los costos de inscripción y mensualidad de esa carrera y ofrécele el botón \"Ver información de pagos\".\n";
        $system .= "   • Si solo dice \"matrícula\" o \"inscripción\" sin más detalle, ofrécele elegir entre precios, fechas o requisitos (aparecerán botones debajo de tu mensaje).\n";
        $system .= "- No muestres el módulo de pagos cuando la pregunta sea sobre otro tema (por ejemplo, carreras o fechas).\n";
        $system .= "- La información de admisión y las fechas de inscripción del contexto son DATOS DE DEMOSTRACIÓN, no oficiales: no los presentes como información oficial verificada.\n";
        $system .= "- NUNCA inventes direcciones de sedes ni carreras por sede: usa solo la información de la sección SEDES del contexto.\n";
        $system .= "- NUNCA inventes fechas de matrículas, cuatrimestres, exámenes, vacaciones ni feriados. Si un dato no está en el contexto, indica que no hay información oficial registrada y sugiere consultar el calendario del sistema.\n";
        $system .= "- Si el usuario pregunta por su HORARIO DE CLASES, sus materias/asignaturas, aulas, docentes, a qué hora entra o sale, o \"qué clases tengo\" un día: responde SOLO con la sección de HORARIO DE CLASES del contexto (incluye asignatura, día, hora, aula, edificio, sede y docente).\n";
        $system .= "- La palabra \"horario\" puede referirse al horario de atención al público o al horario de clases: distínguelos según el contexto. No trates preguntas sueltas de fechas (\"hoy\", \"mañana\") como horarios de clases si no hay contexto de clases.\n";
        $system .= "- NUNCA inventes asignaturas, docentes, aulas, edificios ni horarios. Si un horario no está en el contexto, dilo y sugiere el módulo de Horarios.\n";
        $system .= "- Los horarios de clases del contexto son DATOS DE DEMOSTRACIÓN, no oficiales: no los presentes como información oficial verificada.\n";
        $system .= "- Cuando muestres horarios, usa este formato exacto:\n";
        $system .= "  📅 HORARIO DE CLASES:\n";
        $system .= "  📌 Lunes:\n";
        $system .= "     🕐 08:00 - 10:00 | Programación I\n";
        $system .= "     📍 Aula: Lab. Informática | 👨‍🏫 Ing. Javier Obando\n";
        $system .= "  NO uses tablas ni guiones, solo listas con emojis.\n";
        $system .= "- Responde de forma breve pero completa, sin información innecesaria.\n";
        $system .= "- PRIVACIDAD: el contexto puede estar marcado como DATOS DE DEMOSTRACIÓN (carreras, horarios, admisión, calendario). Aclara que son datos simulados del prototipo cuando sea relevante.\n";
        $system .= "- PRIVACIDAD: no repitas en tu respuesta datos de identidad (nombre o carné) aunque aparecieran; y si el contexto no trae notas, pagos o becas, no los inventes: indica que se consultan en el módulo correspondiente.\n";
        $system .= "- PRIVACIDAD: si el usuario cambia de tema con un seguimiento (\"¿y cuánto cuesta?\", \"¿esa carrera?\", \"¿ese profesor?\"), apóyate en los mensajes previos de la conversación para saber a qué carrera, materia o evento se refiere.\n";
        $system .= "- NUNCA uses tablas markdown (|---|), guiones separadores o formato de tabla.\n";
        $system .= "- Usa listas con viñetas (•) y emojis para organizar la información.\n";
        $system .= "- Usa solo la información del contexto para responder.\n\nCONTEXTO:\n" . $context;

        return $system;
    }

    private function buildContext(string $message, ?int $userId = null): string
    {
        $ctx = '';
        $msgLower = mb_strtolower($message);

        $careers = Cache::remember('careers_active', 3600, function () {
            return Career::where('is_active', true)->get()->toArray();
        });
        $careers = collect($careers);
        if ($careers->isNotEmpty()) {
            $ctx .= "CARRERAS DISPONIBLES Y SUS COSTOS:\n";
            foreach ($careers as $c) {
                $c = (object)$c;
                $ctx .= "• {$c->name} ({$c->code})\n";
                $ctx .= "  Facultad: {$c->faculty}\n";
                $ctx .= "  Duración: {$c->duration_years} años\n";
                $ctx .= "  Inscripción: C\${$c->enrollment_fee}.00\n";
                $ctx .= "  Mensualidad: C\${$c->monthly_fee}.00\n";
                $totalInscripcion = $c->enrollment_fee * $c->duration_years;
                $totalMensualidad = $c->monthly_fee * 12 * $c->duration_years;
                $ctx .= "  Costo total inscripción ({$c->duration_years} años): C\${$totalInscripcion}.00\n";
                $ctx .= "  Costo total mensualidad ({$c->duration_years} años): C\${$totalMensualidad}.00\n\n";
            }
        }

        $this->addAcademicCalendar($ctx, $msgLower);
        $this->addUniversityEvents($ctx, $msgLower);
        $this->addHolidays($ctx, $msgLower);
        // Los horarios generales por carrera son para visitantes; el estudiante
        // autenticado recibe su horario personalizado más abajo (addSchedule).
        if (! $userId) {
            $this->addGeneralSchedules($ctx, $msgLower);
        }
        $this->addOfficeHours($ctx, $msgLower);
        $this->addSedes($ctx, $msgLower);
        $this->addAdmission($ctx, $msgLower);

        if ($userId) {
            $student = Student::where('user_id', $userId)->first();
            if ($student) {
                $career = Career::find($student->career_id);

                // PRIVACIDAD: nunca se envían nombre ni carné a la IA (no son
                // indispensables para responder). Solo se comparte la carrera y
                // el año, necesarios para el horario/materias del estudiante.
                if (config('chatbot.privacy.send_identity', false)) {
                    $user = User::find($userId);
                    $ctx .= "ESTUDIANTE AUTENTICADO: " . ($user?->name ?? 'N/D') . " | Carné: {$student->student_code} | Carrera: " . ($career?->name ?? 'N/D') . " | Año: {$student->year_of_study}\n\n";
                } else {
                    $ctx .= "ESTUDIANTE AUTENTICADO (identidad omitida por privacidad): Carrera: " . ($career?->name ?? 'N/D') . " | Año: {$student->year_of_study}\n\n";
                }

                // Datos académicos privados: solo si están permitidos Y la
                // consulta los pide explícitamente (filtrado por intención).
                if (config('chatbot.privacy.send_personal_data', true)) {
                    if ($this->wantsPersonalTopic($msgLower, 'grades')) {
                        $this->addGrades($ctx, $userId);
                    }
                    if ($this->wantsPersonalTopic($msgLower, 'exams')) {
                        $this->addExams($ctx, $student);
                    }
                    if ($this->wantsPersonalTopic($msgLower, 'scholarships')) {
                        $this->addScholarships($ctx, $userId);
                    }
                    if ($this->wantsPersonalTopic($msgLower, 'tutorings')) {
                        $this->addTutorings($ctx, $userId);
                    }
                    // Recordatorios: redundantes con exámenes/tutorías; solo si
                    // la consulta pide alguno de esos temas.
                    if ($this->wantsPersonalTopic($msgLower, 'exams')
                        || $this->wantsPersonalTopic($msgLower, 'tutorings')) {
                        $this->addReminders($ctx, $student);
                    }
                }

                // El horario del estudiante sí se comparte: es dato de
                // demostración y hace falta para "¿quién es mi profesor de…?".
                $this->addSchedule($ctx, $student);
            }
        } else {
            $ctx .= "NOTA: Usuario no autenticado. Solo puede consultar información pública (carreras, costos, horarios generales, calendario y eventos). NO tienes acceso a datos personales de ningún estudiante.\n";
        }

        return $ctx;
    }

    /**
     * ¿La consulta (normalizada) pide una categoría de datos personales?
     *
     * @param  string  $topic  grades|exams|scholarships|tutorings
     */
    private function wantsPersonalTopic(string $msgLower, string $topic): bool
    {
        return $this->containsAny($msgLower, self::PERSONAL_DATA_KEYWORDS[$topic] ?? []);
    }

    private function addGrades(string &$ctx, int $userId): void
    {
        $grades = Grade::where('user_id', $userId)->get();
        if ($grades->isEmpty()) return;

        $ctx .= "NOTAS DEL PERÍODO {$grades->first()->period}:\n";
        $reprobadas = [];
        foreach ($grades as $g) {
            $status = match($g->status) {
                'approved' => '✅ APROBADO',
                'failed'   => '❌ REPROBADO',
                default    => '⏳ EN CURSO',
            };
            $ctx .= "- {$g->subject_name}: I Parcial={$g->first_partial} | II Parcial={$g->second_partial} | Final={$g->final_exam} | Nota Final={$g->final_grade} | {$status}\n";
            if ($g->status === 'failed') $reprobadas[] = $g->subject_name;
        }
        if (!empty($reprobadas)) {
            $ctx .= "⚠️ MATERIAS REPROBADAS (debe reinscribir): " . implode(', ', $reprobadas) . "\n";
        }
        $ctx .= "\n";
    }

    private function addSchedule(string &$ctx, Student $student): void
    {
        $schedules = Schedule::with(['teacher', 'sede'])
            ->where('career_id', $student->career_id)
            ->where('year_of_study', $student->year_of_study)
            ->when($student->group_code, fn ($q) => $q->where('group_code', $student->group_code))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) return;

        $dayOrder = ['Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6, 'Domingo' => 7];
        $grouped = $schedules->groupBy(fn($s) => $s->day_of_week)
            ->sortBy(fn($items, $day) => $dayOrder[$day] ?? 99);

        $ctx .= "📅 HORARIO DE CLASES DEL ESTUDIANTE (año {$student->year_of_study}"
            . ($student->group_code ? ", grupo {$student->group_code}" : '') . "):\n";
        foreach ($grouped as $day => $daySchedules) {
            $ctx .= "\n📌 {$day}:\n";
            foreach ($daySchedules->sortBy('start_time') as $s) {
                $start = substr((string) $s->start_time, 0, 5);
                $end   = substr((string) $s->end_time, 0, 5);
                $teacher = $s->teacher_display_name ?: 'por asignar';
                $room    = $s->classroom ?: 'por asignar';
                $building = $s->building ? ", {$s->building}" : '';
                $sede    = $s->sede?->name ? ", {$s->sede->name}" : '';
                $ctx .= "   🕐 {$start} - {$end} | {$s->subject_name}\n";
                $ctx .= "      📍 Aula: {$room}{$building}{$sede} | 👨‍🏫 Docente: {$teacher}\n";
            }
        }
        $ctx .= "\n";
    }

    private function addExams(string &$ctx, Student $student): void
    {
        $exams = Exam::where('career_id', $student->career_id)
            ->where('year_of_study', $student->year_of_study)
            ->where('scheduled_at', '>=', Carbon::now())
            ->orderBy('scheduled_at')
            ->get();

        if ($exams->isEmpty()) return;

        $now = Carbon::now();
        $ctx .= "📝 PRÓXIMOS EXÁMENES:\n";
        foreach ($exams as $e) {
            $date = Carbon::parse($e->scheduled_at);
            $days = (int) $now->diffInDays($date, false);
            if ($days < 0) $days = abs($days);
            $daysText = $days == 0 ? 'HOY' : ($days == 1 ? 'MAÑANA' : "en {$days} días");
            $ctx .= "- {$e->subject_name} ({$e->exam_type})\n";
            $ctx .= "  📅 {$date->format('d/m/Y')} a las {$date->format('h:i A')} | Aula: {$e->classroom} | ⏳ Faltan {$daysText}\n";
        }
        $ctx .= "\n";
    }

    private function addScholarships(string &$ctx, int $userId): void
    {
        $scholarships = Scholarship::where('user_id', $userId)
            ->where('status', 'active')
            ->get();

        if ($scholarships->isEmpty()) return;

        $ctx .= "🎓 BECAS ACTIVAS:\n";
        foreach ($scholarships as $b) {
            $typeNames = [
                'academica' => 'Académica',
                'deportiva' => 'Deportiva',
                'socioeconómica' => 'Socioeconómica',
                'cultural' => 'Cultural',
            ];
            $typeName = $typeNames[$b->type] ?? ucfirst($b->type);
            $ctx .= "- Beca {$typeName} | Cobertura: {$b->coverage_percentage}% | Vigente hasta: {$b->end_date->format('d/m/Y')}\n";
            if ($b->description) {
                $ctx .= "  📋 {$b->description}\n";
            }
        }
        $ctx .= "\n";
    }

    private function addTutorings(string &$ctx, int $userId): void
    {
        $tutorings = Tutoring::where('user_id', $userId)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', Carbon::now())
            ->orderBy('scheduled_at')
            ->get();

        if ($tutorings->isEmpty()) return;

        $now = Carbon::now();
        $ctx .= "👨‍🏫 TUTORÍAS AGENDADAS:\n";
        foreach ($tutorings as $t) {
            $date = Carbon::parse($t->scheduled_at);
            $days = (int) $now->diffInDays($date, false);
            if ($days < 0) $days = abs($days);
            $daysText = $days == 0 ? 'HOY' : ($days == 1 ? 'MAÑANA' : "en {$days} días");
            $ctx .= "- {$t->subject_name} con {$t->tutor_name}\n";
            $ctx .= "  📅 {$date->format('d/m/Y')} a las {$date->format('h:i A')}\n";
            $ctx .= "  📍 {$t->meeting_link_or_place}\n";
            $ctx .= "  💰 Costo tutoría: C\${$t->cost} | Examen: C\${$t->exam_cost}\n";
            $ctx .= "  📌 Estado pago: " . ($t->tutoring_paid ? 'Tutoría pagada' : 'Tutoría pendiente') . " | " . ($t->exam_paid ? 'Examen pagado' : 'Examen pendiente') . "\n";
            $ctx .= "  ⏳ Faltan {$daysText}\n";
        }
        $ctx .= "\n";
    }

    private function addAcademicCalendar(string &$ctx, string $msgLower): void
    {
        $calendarKeywords = [
            'calendario', 'inscripción', 'inscripciones', 'matrícula', 'matrículas', 'matricula',
            'vacaciones', 'periodo', 'período', 'fechas', 'fecha', 'cuatrimestre', 'cuatrimestres',
            'académico', 'academico', 'calificaciones', 'notas finales', 'entrega de notas',
            'exámenes finales', 'examen final', 'receso',
        ];
        $hasKeyword = false;
        foreach ($calendarKeywords as $kw) {
            if (str_contains($msgLower, $kw)) {
                $hasKeyword = true;
                break;
            }
        }
        if (!$hasKeyword) return;

        $now = Carbon::now('America/Managua');

        // Eventos/acuerdos registrados por la universidad (tabla academic_calendars).
        $calendars = AcademicCalendar::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->where('end_date', '>=', $now->copy()->subMonth())
                  ->orWhere(function ($q2) use ($now) {
                      $q2->whereNull('end_date')
                         ->where('start_date', '>=', $now->copy()->subMonth());
                  });
            })
            ->orderBy('start_date')
            ->take(8)
            ->get();

        if ($calendars->isNotEmpty()) {
            $typeNames = [
                'inscription' => '📝 Inscripción',
                'exam' => '📋 Exámenes',
                'vacation' => '🏖️ Vacaciones',
                'event' => '🎉 Evento',
                'deadline' => '⏰ Fecha límite',
            ];

            $ctx .= "📅 CALENDARIO ACADÉMICO:\n";
            foreach ($calendars as $cal) {
                $typeName = $typeNames[$cal->type] ?? ucfirst($cal->type);
                $start = Carbon::parse($cal->start_date);
                $end = $cal->end_date ? Carbon::parse($cal->end_date) : null;

                if ($start->isPast() && $end && $end->isFuture()) {
                    $daysText = 'EN CURSO';
                } elseif ($start->isFuture()) {
                    $days = (int) $now->diffInDays($start, false);
                    if ($days < 0) $days = abs($days);
                    $daysText = $days == 0 ? 'HOY' : ($days == 1 ? 'MAÑANA' : "en {$days} días");
                } else {
                    $daysText = 'FINALIZADO';
                }

                $ctx .= "- {$typeName}: {$cal->title}\n";
                $ctx .= "  📅 Del {$start->format('d/m/Y')}";
                if ($end) $ctx .= " al {$end->format('d/m/Y')}";
                $ctx .= " | ⏳ {$daysText}\n";
                if ($cal->description) $ctx .= "  📋 {$cal->description}\n";
            }
            $ctx .= "\n";
        }

        // Eventos académicos institucionales (tabla university_events).
        $events = UniversityEvent::with('career:id,name,code')
            ->where('is_active', true)
            ->where('start_date', '>=', $now->copy()->subMonth())
            ->orderBy('start_date')
            ->take(8)
            ->get();

        if ($events->isNotEmpty()) {
            $ctx .= "🎓 EVENTOS ACADÉMICOS INSTITUCIONALES:\n";
            foreach ($events as $e) {
                $start = Carbon::parse($e->start_date);
                $end = $e->end_date ? Carbon::parse($e->end_date) : null;
                $label = config("calendar.categories.{$e->category}.label", ucfirst((string) $e->category));

                $ctx .= "- {$e->title} ({$label})\n";
                $ctx .= "  📅 Del {$start->format('d/m/Y')}";
                if ($end) $ctx .= " al {$end->format('d/m/Y')}";
                $ctx .= " | ⏳ {$this->relativeDays($now, $start)}\n";
                if ($e->campus) $ctx .= "  🏫 Sede: {$e->campus}\n";
                if ($e->career) $ctx .= "  🎓 Carrera: {$e->career->name}\n";
                if ($e->academic_period) $ctx .= "  📆 Período: {$e->academic_period}\n";
                if ($e->description) $ctx .= "  📋 {$e->description}\n";
            }
            $ctx .= "\n";
        }
    }

    private function addUniversityEvents(string &$ctx, string $msgLower): void
    {
        $eventKeywords = [
            'evento', 'eventos', 'actividad', 'actividades', 'conferencia', 'feria',
            'torneo', 'seminario', 'cultural', 'deportiv', 'celebraci', 'charla', 'jornada',
        ];
        $hasKeyword = false;
        foreach ($eventKeywords as $kw) {
            if (str_contains($msgLower, $kw)) {
                $hasKeyword = true;
                break;
            }
        }
        if (!$hasKeyword) return;

        $now = Carbon::now('America/Managua');
        $events = UniversityEvent::with('career:id,name,code')
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->where('end_date', '>=', $now)
                  ->orWhere(function ($q2) use ($now) {
                      $q2->whereNull('end_date')->where('start_date', '>=', $now);
                  });
            })
            ->orderBy('start_date')
            ->take(6)
            ->get();

        if ($events->isEmpty()) { $ctx .= "\n"; return; }

        $ctx .= "🎉 PRÓXIMOS EVENTOS UNIVERSITARIOS:\n";
        foreach ($events as $e) {
            $start = Carbon::parse($e->start_date);
            $end = $e->end_date ? Carbon::parse($e->end_date) : null;
            $label = config("calendar.categories.{$e->category}.label", ucfirst((string) $e->category));

            $ctx .= "- {$e->title} ({$label})\n";
            $ctx .= "  📅 Del {$start->format('d/m/Y')}";
            if ($end && !$end->isSameDay($start)) $ctx .= " al {$end->format('d/m/Y')}";
            elseif (!$end) $ctx .= " a las {$start->format('h:i A')}";
            $ctx .= " | ⏳ {$this->relativeDays($now, $start)}\n";
            if ($e->location) $ctx .= "  📍 {$e->location}\n";
            if ($e->campus) $ctx .= "  🏫 Sede: {$e->campus}\n";
            if ($e->career) $ctx .= "  🎓 Carrera: {$e->career->name}\n";
            if ($e->organizer) $ctx .= "  👥 Organiza: {$e->organizer}\n";
            if ($e->description) $ctx .= "  📋 {$e->description}\n";
        }
        $ctx .= "\n";
    }

    /**
     * Añade al contexto las sedes (CUR) y las carreras que ofrece cada una.
     *
     * Se activa cuando el usuario pregunta por carreras, sedes, ubicación o
     * dónde estudiar, para que el asistente pueda indicar en qué sede se
     * imparte cada carrera y su dirección.
     */
    private function addSedes(string &$ctx, string $msgLower): void
    {
        $keywords = [
            'sede', 'sedes', 'campus', 'centro universitario', 'centros universitarios',
            'dónde', 'donde', 'ubicación', 'ubicacion', 'dirección', 'direccion',
            'localiza', 'queda', 'carrera', 'carreras', 'ofrece', 'imparte',
            'estudiar', 'matricular', 'inscribir',
        ];

        $hasKeyword = false;
        foreach ($keywords as $kw) {
            if (str_contains($msgLower, $kw)) {
                $hasKeyword = true;
                break;
            }
        }
        if (!$hasKeyword) return;

        $sedes = Sede::with(['careers' => function ($q) {
                $q->where('careers.is_active', true)->orderBy('careers.name');
            }])
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        if ($sedes->isEmpty()) return;

        $totalCareers = Career::where('is_active', true)->count();

        $ctx .= "🏫 SEDES (CENTROS UNIVERSITARIOS REGIONALES – CUR) Y CARRERAS DE CADA UNA:\n";
        $ctx .= "Cada sede ofrece varias carreras; algunas se repiten entre sedes y otras son exclusivas. La sede de León es la única que ofrece TODAS las carreras.\n";
        foreach ($sedes as $sede) {
            $main = $sede->is_main ? " (SEDE PRINCIPAL – ofrece las {$totalCareers} carreras)" : '';
            $ctx .= "• {$sede->name}{$main}\n";
            $ctx .= "  📍 Dirección: {$sede->address}";
            if ($sede->city) $ctx .= " ({$sede->city})";
            $ctx .= "\n";

            $names = $sede->careers->pluck('name')->all();
            $ctx .= "  🎓 Carreras (" . count($names) . "): " . implode(', ', $names) . "\n";
        }
        $ctx .= "\n";
    }

    /**
     * Añade los feriados nacionales de Nicaragua (Nager.Date) al contexto.
     */
    private function addHolidays(string &$ctx, string $msgLower): void
    {
        $holidayKeywords = ['feriado', 'feriados', 'asueto', 'día festivo', 'dias festivos', 'días festivos', 'festivo', 'festivos', 'puente'];
        $hasKeyword = false;
        foreach ($holidayKeywords as $kw) {
            if (str_contains($msgLower, $kw)) {
                $hasKeyword = true;
                break;
            }
        }
        if (!$hasKeyword) return;

        $now = Carbon::now('America/Managua');
        $holidays = $this->holidays->getHolidays($now->copy()->startOfYear(), $now->copy()->addYear());

        if (empty($holidays)) return;

        $ctx .= "🇳🇮 FERIADOS NACIONALES EN NICARAGUA:\n";
        foreach ($holidays as $h) {
            $date = Carbon::parse($h['date']);
            $ctx .= "- {$h['name']} ({$h['date']})\n";
            $ctx .= "  ⏳ {$this->relativeDays($now, $date)}\n";
        }
        $ctx .= "\n";
    }

    private function relativeDays(Carbon $now, Carbon $date): string
    {
        $days = (int) $now->copy()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        if ($days === 0) return 'HOY';
        if ($days === 1) return 'MAÑANA';
        if ($days === -1) return 'AYER';
        if ($days < 0) return 'ya pasó (hace ' . abs($days) . ' días)';

        return "en {$days} días";
    }

    private function addReminders(string &$ctx, Student $student): void
    {
        $now = Carbon::now();
        $exams = Exam::where('career_id', $student->career_id)
            ->where('year_of_study', $student->year_of_study)
            ->where('scheduled_at', '>=', $now)
            ->where('scheduled_at', '<=', $now->copy()->addDays(7))
            ->orderBy('scheduled_at')
            ->get();

        if ($exams->isNotEmpty()) {
            $ctx .= "⚠️ RECORDATORIO: Tienes exámenes esta semana:\n";
            foreach ($exams as $e) {
                $date = Carbon::parse($e->scheduled_at);
                $days = (int) $now->diffInDays($date, false);
                if ($days < 0) $days = abs($days);
                $daysText = $days == 0 ? 'HOY' : ($days == 1 ? 'MAÑANA' : "en {$days} días");
                $ctx .= "  📝 {$e->subject_name} ({$e->exam_type}) - {$date->format('d/m')} {$daysText}\n";
            }
            $ctx .= "\n";
        }

        $tutorings = Tutoring::where('user_id', $student->user_id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', $now)
            ->where('scheduled_at', '<=', $now->copy()->addDays(3))
            ->orderBy('scheduled_at')
            ->get();

        if ($tutorings->isNotEmpty()) {
            $ctx .= "⚠️ RECORDATORIO: Tienes tutorías próximas:\n";
            foreach ($tutorings as $t) {
                $date = Carbon::parse($t->scheduled_at);
                $days = (int) $now->diffInDays($date, false);
                if ($days < 0) $days = abs($days);
                $daysText = $days == 0 ? 'HOY' : ($days == 1 ? 'MAÑANA' : "en {$days} días");
                $ctx .= "  👨‍🏫 {$t->subject_name} con {$t->tutor_name} - {$date->format('d/m')} {$daysText}\n";
            }
            $ctx .= "\n";
        }
    }

    /**
     * Indica si el mensaje del usuario se refiere al horario de atención
     * al público (y no a horarios de clases).
     */
    private function wantsOfficeHours(string $msgLower): bool
    {
        $keywords = [
            'atención', 'atencion',
            'atiende', 'atienden', 'atender', 'atendemos', 'atiend', 'atien',
            'horario de atención', 'horario de atencion',
            'abre', 'abren', 'abrimos', 'abrir', 'abierto',
            'cierra', 'cierran', 'cerramos', 'cerrar', 'cerrado',
            'hora de atención', 'hora de atencion',
            'está abierto', 'esta abierto',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($msgLower, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indica si el mensaje del usuario se refiere a horarios de clases
     * (materias/asignaturas). Tiene prioridad sobre el horario de atención.
     */
    private function wantsClassSchedules(string $msgLower): bool
    {
        foreach (['clase', 'materia', 'asignatura'] as $kw) {
            if (str_contains($msgLower, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Añade al contexto el horario de atención al público de la universidad.
     * Incluye una nota de que en días feriados nacionales no hay atención,
     * junto con los próximos feriados.
     */
    private function addOfficeHours(string &$ctx, string $msgLower): void
    {
        if (!$this->wantsOfficeHours($msgLower)) return;
        if ($this->wantsClassSchedules($msgLower)) return;

        $hours = config('university.office_hours', []);
        if (empty($hours)) return;

        $ctx .= "🕐 HORARIO DE ATENCIÓN DE LA UNIVERSIDAD:\n";
        foreach ($hours as $h) {
            $ctx .= "- {$h['days']}: {$h['open']} a {$h['close']}\n";
        }
        if ($note = config('university.office_hours_note')) {
            $ctx .= "  ({$note})\n";
        }

        // En días feriados nacionales no hay atención al público.
        // Incluimos el año actual y el siguiente completos para poder responder
        // también consultas sobre una fecha concreta.
        $now = Carbon::now('America/Managua');
        $holidays = $this->holidays->getHolidays(
            $now->copy()->startOfYear(),
            $now->copy()->addYear()->endOfYear()
        );

        if (!empty($holidays)) {
            $ctx .= "  ⚠️ IMPORTANTE: en los días feriados nacionales NO hay atención al público. Si preguntan por una fecha que aparece en esta lista, aclara que ese día no se atiende.\n";
            $ctx .= "  Feriados nacionales (sin atención):\n";
            foreach ($holidays as $h) {
                $ctx .= "    • {$h['date']}: {$h['name']}\n";
            }
        }

        $ctx .= "\n";
    }

    private function addGeneralSchedules(string &$ctx, string $msgLower): void
    {
        $wantsClass = $this->wantsClassSchedules($msgLower);

        // Si el usuario pregunta por el HORARIO DE ATENCIÓN (y no por
        // horarios de clases) no mostramos horarios de clases: para eso
        // está addOfficeHours().
        if ($this->wantsOfficeHours($msgLower) && !$wantsClass) return;

        $hasScheduleKeyword = $wantsClass || str_contains($msgLower, 'horario');
        if (!$hasScheduleKeyword) return;

        $careerKeywords = [
            'medicina'           => 'MED-001',
            'enfermeria'         => 'ENF-001',
            'nutricion'          => 'NUT-001',
            'civil'              => 'ICI-001',
            'arquitectura'       => 'ARQ-001',
            'industrial'         => 'IIN-001',
            'sistemas'           => 'ISI-001',
            'telecomunicaciones' => 'ITE-001',
            'fisica'             => 'FIS-001',
            'agronomia'          => 'AGR-001',
            'veterinaria'        => 'VET-001',
            'forestal'           => 'FOR-001',
            'administracion'     => 'ADM-001',
            'contabilidad'       => 'CON-001',
            'economia'           => 'ECO-001',
        ];

        $matchedCode = null;
        foreach ($careerKeywords as $keyword => $code) {
            if (str_contains($msgLower, $keyword)) {
                $matchedCode = $code;
                break;
            }
        }

        // Sin carrera concreta: no volcamos los horarios de todas las carreras.
        if ($matchedCode === null) {
            $ctx .= "🗓️ HORARIOS DE CLASES: el usuario no indicó una carrera concreta. "
                . "Pídele la carrera (y, si aplica, año y grupo) o sugiérele el módulo de Horarios.\n\n";
            return;
        }

        $career = Career::where('code', $matchedCode)->where('is_active', true)->first();
        if (! $career) return;

        $year  = $this->extractScheduleYear($msgLower);
        $group = $this->extractScheduleGroup($msgLower);

        $schedules = Schedule::with(['teacher', 'sede'])
            ->where('career_id', $career->id)
            ->when($year !== null, fn ($q) => $q->where('year_of_study', $year))
            ->when($group !== null, fn ($q) => $q->where('group_code', $group))
            ->orderBy('year_of_study')
            ->orderBy('group_code')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->limit(60)
            ->get();

        if ($schedules->isEmpty()) {
            $ctx .= "🗓️ No hay horarios registrados para {$career->name}.\n\n";
            return;
        }

        $dayOrder = ['Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6, 'Domingo' => 7];
        $grouped = $schedules->groupBy(fn ($s) => $s->day_of_week)
            ->sortBy(fn ($items, $day) => $dayOrder[$day] ?? 99);

        $ctx .= "🗓️ HORARIOS DE CLASES DE {$career->name} ({$career->code})"
            . ($year !== null ? ", año {$year}" : '')
            . ($group !== null ? ", grupo {$group}" : '')
            . " (datos de demostración, no oficiales):\n";

        foreach ($grouped as $day => $daySchedules) {
            $ctx .= "📌 {$day}:\n";
            foreach ($daySchedules->sortBy('start_time') as $s) {
                $start = substr((string) $s->start_time, 0, 5);
                $end   = substr((string) $s->end_time, 0, 5);
                $building = $s->building ? " · {$s->building}" : '';
                $ctx .= "   🕐 {$start} - {$end} | {$s->subject_name} (Año {$s->year_of_study}, Grupo {$s->group_code}) | Aula {$s->classroom}{$building} | {$s->teacher_display_name}\n";
            }
        }
        $ctx .= "\n";
    }

    /**
     * Agrega la información de admisión (requisitos y documentos) al contexto
     * cuando el mensaje trata sobre requisitos, documentos o admisión.
     */
    private function addAdmission(string &$ctx, string $msgLower): void
    {
        $m = $this->normalize($msgLower);

        $wantsAdmission = $this->containsAny($m, self::REQUIREMENT_KEYWORDS)
            || $this->containsAny($m, ['admision', 'inscribirme', 'inscribir', 'matricularme']);

        if (! $wantsAdmission) {
            return;
        }

        $text = $this->admissionText($msgLower);
        if ($text === '') {
            return;
        }

        $ctx .= "📄 ADMISIÓN (datos de demostración, no oficiales):\n";
        $ctx .= $text . "\n\n";
    }

    private function fallback(string $message): array
    {
        $msg = mb_strtolower($message);

        // Pagos/recibos personales.
        $paymentsText = "Puedes consultar tus pagos, la inscripción y las mensualidades en el módulo \"Pagos del estudiante\". Haz clic en el botón de abajo e ingresa el código de tu carné para ver tu estado de cuenta.";
        if ($this->wantsPayments($message)) {
            return ['intent' => 'fallback_payments', 'count' => 0, 'text' => $paymentsText];
        }

        $m = $this->normalize($message);
        $hasEnrollment  = $this->containsAny($m, self::ENROLLMENT_KEYWORDS);
        $hasRequirement = $this->containsAny($m, self::REQUIREMENT_KEYWORDS)
            || ($hasEnrollment && $this->containsAny($m, ['necesito', 'debo', 'llevar', 'traer']));
        $hasDate  = $this->containsAny($m, self::ENROLLMENT_DATE_KEYWORDS);
        $hasPrice = $this->containsAny($m, self::PRICE_KEYWORDS);

        // Requisitos/documentos -> información de admisión.
        if ($hasRequirement) {
            $text = "📄 Información de admisión (datos de demostración, no oficiales):\n\n"
                . $this->admissionText($message);

            return ['intent' => 'fallback_admission', 'count' => 0, 'text' => $text];
        }

        // Fechas de matrícula/inscripción -> calendario académico.
        if ($hasDate && $hasEnrollment) {
            return [
                'intent' => 'fallback_calendar',
                'count'  => 0,
                'text'   => "Puedes consultar las fechas de matrícula e inscripción en el Calendario Académico. Abre el botón \"Abrir calendario académico\" que aparece debajo del mensaje.",
            ];
        }

        // Solo matrícula/inscripción -> ofrecer opciones.
        if ($hasEnrollment) {
            if ($hasPrice) {
                return ['intent' => 'fallback_payments', 'count' => 0, 'text' => $paymentsText];
            }

            return [
                'intent' => 'fallback_enrollment',
                'count'  => 0,
                'text'   => "Sobre la matrícula o inscripción, ¿qué deseas saber? Elige una opción:\n\n• 💰 Consultar precios\n• 📅 Ver fechas\n• 📄 Ver requisitos",
            ];
        }

        // Carreras (incluye el precio de una carrera concreta).
        $wantsCareers = false;
        foreach (['carrera', 'oferta'] as $kw) {
            if (str_contains($msg, $kw)) { $wantsCareers = true; break; }
        }

        if ($wantsCareers) {
            $careers = Career::where('is_active', true)->get();
            if ($careers->isNotEmpty()) {
                $lines = $careers->map(fn($c) =>
                    "• {$c->name} ({$c->code})\n" .
                    "   Inscripción: C\${$c->enrollment_fee}.00 | Mensualidad: C\${$c->monthly_fee}.00 | Duración: {$c->duration_years} años"
                )->implode("\n");

                return [
                    'intent' => 'fallback_careers',
                    'count' => $careers->count(),
                    'text' => "Estoy teniendo dificultades para conectar con la IA en este momento, pero aquí tienes la información oficial de las carreras:\n\n{$lines}",
                ];
            }
        }

        // Precios/costos sin carrera concreta -> módulo de pagos.
        if ($hasPrice) {
            return ['intent' => 'fallback_payments', 'count' => 0, 'text' => $paymentsText];
        }

        if ($this->wantsOfficeHours($msg) && !$this->wantsClassSchedules($msg)) {
            $hours = config('university.office_hours', []);
            $lines = collect($hours)
                ->map(fn ($h) => "• {$h['days']}: {$h['open']} a {$h['close']}")
                ->implode("\n");

            $now = Carbon::now('America/Managua');
            $holidays = $this->holidays->getHolidays($now->copy()->startOfDay(), $now->copy()->addMonths(12));
            $holidayNote = '';
            if (!empty($holidays)) {
                $upcoming = array_slice($holidays, 0, 10);
                $list = implode('; ', array_map(fn ($h) => "{$h['name']} ({$h['date']})", $upcoming));
                $holidayNote = "\n\n⚠️ En los días feriados nacionales NO hay atención al público.\nPróximos feriados (sin atención): {$list}.";
            }

            return [
                'intent' => 'fallback_office_hours',
                'count' => 0,
                'text' => "🕐 El horario de atención de la universidad es:\n\n{$lines}\n\n" . config('university.office_hours_note', '') . $holidayNote,
            ];
        }

        if ($this->wantsScheduleIntent($message)) {
            return [
                'intent' => 'fallback_schedules',
                'count' => 0,
                'text' => "Puedes consultar los horarios de clases en el módulo de Horarios: elige la carrera, el año y el grupo en el modo demostración, o inicia sesión con tu carné para ver tu horario personalizado. También puedes preguntarme, por ejemplo: «¿Qué clases tengo el lunes?» o «¿En qué aula tengo Bases de Datos?».",
            ];
        }

        return [
            'intent' => 'fallback',
            'count' => 0,
            'text' => "Estoy teniendo problemas para generar la respuesta en este momento. Inténtalo de nuevo en unos segundos.\n\nMientras tanto puedo ayudarte con: carreras y costos, pagos y recibos del estudiante, horarios de atención y de clases, calendario académico y eventos universitarios.",
        ];
    }
}
