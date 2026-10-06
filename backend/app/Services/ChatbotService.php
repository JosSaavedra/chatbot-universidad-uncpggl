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
use App\Models\UniversityEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ChatbotService
{
    public function processQuery(string $message, ?int $userId, ?string $studentCode = null): array
    {
        $context = $this->buildContext($message, $userId, $studentCode);

        $system  = "Eres el asistente académico oficial de la Universidad Nacional Comandante Padre Gaspar García Laviana (UNCPGGL) de Nicaragua.\n";
        $system .= "Responde siempre en español, de forma clara, amable y ordenada.\n";
        $system .= "REGLAS IMPORTANTES:\n";
        $system .= "- Si el usuario pide sus notas, horario personal, becas, exámenes o tutorías Y no hay datos de estudiante en el contexto, pídele su código estudiantil (carnet).\n";
        $system .= "- Si el contexto tiene datos del estudiante, úsalos para responder.\n";
        $system .= "- Los horarios generales de cada carrera SÍ puedes mostrarlos sin necesidad de login.\n";
        $system .= "- Cuando listes carreras, ponlas en lista vertical con viñetas.\n";
        $system .= "- Cuando muestres precios, usa formato: Inscripción: C\$X.00 y Mensualidad: C\$X.00\n";
        $system .= "- Si preguntan por el precio total, calcula: inscripción × años de duración y mensualidad × 12 meses × años de duración.\n";
        $system .= "- Si el estudiante tiene materias reprobadas, recuérdale que debe inscribir esa materia nuevamente.\n";
        $system .= "- Si hay próximos exámenes, menciónaselo al estudiante indicando cuántos días faltan.\n";
        $system .= "- Si hay tutorías agendadas, menciónaselas al estudiante con todos los detalles.\n";
        $system .= "- Si hay recordatorios de exámenes o tutorías próximas (esta semana), menciónalos primero.\n";
        $system .= "- Si el estudiante pregunta por calendario académico, fechas de inscripción o vacaciones, muéstrale la información del contexto.\n";
        $system .= "- Si el estudiante pregunta por eventos, muéstrale los próximos eventos universitarios del contexto.\n";
        $system .= "- Cuando muestres horarios, usa este formato exacto:\n";
        $system .= "  📅 HORARIO DE CLASES:\n";
        $system .= "  📌 Lunes:\n";
        $system .= "     🕐 08:00 - 10:00 | Programación I\n";
        $system .= "     📍 Aula: Lab. Informática | 👨‍🏫 Ing. Javier Obando\n";
        $system .= "  NO uses tablas ni guiones, solo listas con emojis.\n";
        $system .= "- Responde de forma breve pero completa, sin información innecesaria.\n";
        $system .= "- NUNCA uses tablas markdown (|---|), guiones separadores o formato de tabla.\n";
        $system .= "- Usa listas con viñetas (•) y emojis para organizar la información.\n";
        $system .= "- Para horarios, exámenes y tutorías, usa el formato de lista con emojis que se indica arriba.\n";
        $system .= "Usa solo la información del contexto para responder.\n\nCONTEXTO:\n" . $context;

        try {
            $apiKey = trim(config('services.groq.key', env('OPENAI_API_KEY', '')));

            $res = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'openai/gpt-oss-20b',
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $message],
                ],
                'max_tokens'  => 800,
                'temperature' => 0.7,
            ]);

            if ($res->successful()) {
                return ['intent' => 'groq', 'count' => 1, 'text' => $res->json('choices.0.message.content')];
            }

            \Log::error('Groq API error: ' . $res->status() . ' - ' . $res->body());
            return [
                'intent' => 'groq_error',
                'count' => 0,
                'text' => 'Error de la API de Groq (' . $res->status() . '): ' . $res->json('error.message', 'Error desconocido'),
            ];

        } catch (\Exception $e) {
            \Log::error('Groq API exception: ' . $e->getMessage());
            return $this->fallback($message);
        }
    }

    private function buildContext(string $message, ?int $userId, ?string $studentCode = null): string
    {
        $ctx = '';
        $msgLower = mb_strtolower($message);

        $careers = Cache::remember('careers_active', 3600, function () {
            return Career::where('is_active', true)->get();
        });
        if ($careers->isNotEmpty()) {
            $ctx .= "CARRERAS DISPONIBLES Y SUS COSTOS:\n";
            foreach ($careers as $c) {
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
        $this->addGeneralSchedules($ctx, $msgLower);

        if (!$studentCode) {
            $studentCode = $this->extractStudentCode($message);
        }

        if ($studentCode) {
            $student = Student::where('student_code', $studentCode)->first();
            if ($student) {
                $user   = User::find($student->user_id);
                $career = Career::find($student->career_id);
                $ctx .= "DATOS DEL ESTUDIANTE (carnet: {$studentCode}):\n";
                $ctx .= "Nombre: {$user->name} | Carné: {$student->student_code} | Carrera: {$career->name} | Año: {$student->year_of_study}\n\n";

                $this->addGrades($ctx, $student->user_id);
                $this->addSchedule($ctx, $student);
                $this->addExams($ctx, $student);
                $this->addScholarships($ctx, $student->user_id);
                $this->addTutorings($ctx, $student->user_id);
                $this->addReminders($ctx, $student);

            } else {
                $ctx .= "NOTA: No se encontró ningún estudiante con el carnet '{$studentCode}'.\n";
            }

        } elseif ($userId) {
            $student = Student::where('user_id', $userId)->first();
            if ($student) {
                $user   = User::find($userId);
                $career = Career::find($student->career_id);
                $ctx .= "ESTUDIANTE AUTENTICADO: {$user->name} | Carné: {$student->student_code} | Carrera: {$career->name} | Año: {$student->year_of_study}\n\n";

                $this->addGrades($ctx, $userId);
                $this->addSchedule($ctx, $student);
                $this->addExams($ctx, $student);
                $this->addScholarships($ctx, $userId);
                $this->addTutorings($ctx, $userId);
                $this->addReminders($ctx, $student);
            }
        } else {
            $ctx .= "NOTA: Usuario no autenticado. Si desea consultar notas, becas, exámenes o tutorías personalizadas, proporcione su código estudiantil (carnet).\n";
        }

        return $ctx;
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
        $schedules = Schedule::where('career_id', $student->career_id)
            ->where('year_of_study', $student->year_of_study)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) return;

        $dayOrder = ['Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6, 'Domingo' => 7];
        $grouped = $schedules->groupBy(fn($s) => $s->day_of_week)
            ->sortBy(fn($items, $day) => $dayOrder[$day] ?? 99);

        $ctx .= "📅 HORARIO DE CLASES:\n";
        foreach ($grouped as $day => $daySchedules) {
            $ctx .= "\n📌 {$day}:\n";
            foreach ($daySchedules->sortBy('start_time') as $s) {
                $ctx .= "   🕐 {$s->start_time} - {$s->end_time} | {$s->subject_name}\n";
                $ctx .= "      📍 Aula: {$s->classroom} | 👨‍🏫 Docente: {$s->teacher_name}\n";
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
        $calendarKeywords = ['calendario', 'inscripción', 'inscripciones', 'vacaciones', 'periodo', 'fechas', 'exámenes finales', 'académico'];
        $hasKeyword = false;
        foreach ($calendarKeywords as $kw) {
            if (str_contains($msgLower, $kw)) {
                $hasKeyword = true;
                break;
            }
        }
        if (!$hasKeyword) return;

        $now = Carbon::now();
        $calendars = AcademicCalendar::where('is_active', true)
            ->where('end_date', '>=', $now->copy()->subMonth())
            ->orderBy('start_date')
            ->take(6)
            ->get();

        if ($calendars->isEmpty()) return;

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

    private function addUniversityEvents(string &$ctx, string $msgLower): void
    {
        $eventKeywords = ['evento', 'eventos', 'actividad', 'actividades', 'conferencia', 'feria', 'torneo', 'seminario'];
        $hasKeyword = false;
        foreach ($eventKeywords as $kw) {
            if (str_contains($msgLower, $kw)) {
                $hasKeyword = true;
                break;
            }
        }
        if (!$hasKeyword) return;

        $now = Carbon::now();
        $events = UniversityEvent::where('is_active', true)
            ->where('event_date', '>=', $now)
            ->orderBy('event_date')
            ->take(5)
            ->get();

        if ($events->isEmpty()) return;

        $ctx .= "🎉 PRÓXIMOS EVENTOS UNIVERSITARIOS:\n";
        foreach ($events as $e) {
            $date = Carbon::parse($e->event_date);
            $days = (int) $now->diffInDays($date, false);
            if ($days < 0) $days = abs($days);
            $daysText = $days == 0 ? 'HOY' : ($days == 1 ? 'MAÑANA' : "en {$days} días");

            $ctx .= "- {$e->title}\n";
            $ctx .= "  📅 {$date->format('d/m/Y')} a las {$date->format('h:i A')} | ⏳ {$daysText}\n";
            if ($e->location) $ctx .= "  📍 {$e->location}\n";
            if ($e->organizer) $ctx .= "  👨‍🏫 Organiza: {$e->organizer}\n";
            if ($e->description) $ctx .= "  📋 {$e->description}\n";
        }
        $ctx .= "\n";
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

    private function addGeneralSchedules(string &$ctx, string $msgLower): void
    {
        $scheduleKeywords = ['horario', 'clases', 'materias', 'asignaturas'];
        $hasScheduleKeyword = false;
        foreach ($scheduleKeywords as $kw) {
            if (str_contains($msgLower, $kw)) {
                $hasScheduleKeyword = true;
                break;
            }
        }
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

        $careers = $matchedCode
            ? Career::where('code', $matchedCode)->where('is_active', true)->get()
            : Career::where('is_active', true)->get();

        foreach ($careers as $career) {
            $schedules = Schedule::where('career_id', $career->id)
                ->orderBy('year_of_study')
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();
            if ($schedules->isEmpty()) continue;

            $dayOrder = ['Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6, 'Domingo' => 7];
            $grouped = $schedules->groupBy(fn($s) => $s->day_of_week)
                ->sortBy(fn($items, $day) => $dayOrder[$day] ?? 99);

            $ctx .= "📅 HORARIOS DE {$career->name} ({$career->code}):\n";
            foreach ($grouped as $day => $daySchedules) {
                $ctx .= "\n📌 {$day}:\n";
                foreach ($daySchedules->sortBy('start_time') as $s) {
                    $ctx .= "   🕐 {$s->start_time} - {$s->end_time} | {$s->subject_name} (Año {$s->year_of_study})\n";
                    $ctx .= "      📍 Aula: {$s->classroom} | 👨‍🏫 {$s->teacher_name}\n";
                }
            }
            $ctx .= "\n";
        }
    }

    private function extractStudentCode(string $message): ?string
    {
        if (preg_match('/\b(\d{4}-[A-Z]{2,3}-\d{3})\b/', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function fallback(string $message): array
    {
        $msg = mb_strtolower($message);
        if (str_contains($msg, 'carrera') || str_contains($msg, 'oferta')) {
            $careers = Career::where('is_active', true)->get();
            $list = $careers->map(fn($c) => "• {$c->name}")->implode("\n");
            return ['intent' => 'careers', 'count' => $careers->count(), 'text' => "Carreras disponibles:\n\n" . $list];
        }
        return ['intent' => 'fallback', 'count' => 0, 'text' => 'Hola. Soy el asistente de la UNCPGGL. Puedo ayudarte con carreras, horarios, notas, becas, exámenes o tutorías.'];
    }
}
