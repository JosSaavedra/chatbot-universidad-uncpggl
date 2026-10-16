<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Memoria conversacional: seguimientos del tipo "¿y cuánto cuesta?",
 * "¿quién es mi profesor de esa materia?" o "¿y mañana qué clases tengo?".
 *
 * La resolución se apoya en DATOS ESTRUCTURADOS (tarjetas previas guardadas en
 * `messages.data` y consultas a SQLite), no solo en el modelo de IA. Además se
 * verifica que la memoria nunca mezcle conversaciones de distintos usuarios.
 */
class ChatbotMemoryTest extends TestCase
{
    use RefreshDatabase;

    private function seedCareers(): void
    {
        $careers = [
            ['name' => 'Medicina y Cirugía',     'code' => 'MED-001', 'faculty' => 'Ciencias de la Salud',      'duration_years' => 6, 'enrollment_fee' => 800, 'monthly_fee' => 500],
            ['name' => 'Ingeniería en Sistemas', 'code' => 'ISI-001', 'faculty' => 'Ciencias y Tecnología',     'duration_years' => 5, 'enrollment_fee' => 700, 'monthly_fee' => 400],
            ['name' => 'Enfermería',             'code' => 'ENF-001', 'faculty' => 'Ciencias de la Salud',      'duration_years' => 4, 'enrollment_fee' => 600, 'monthly_fee' => 350],
        ];

        foreach ($careers as $career) {
            Career::create($career + ['is_active' => true]);
        }
    }

    /**
     * Historial con la tarjeta de UNA carrera concreta (como la mostró el chat).
     *
     * @return array<int, array<string, mixed>>
     */
    private function historyWithSingleCareer(int $careerId): array
    {
        return [
            ['role' => 'user', 'content' => '¿Cuánto cuesta Medicina?', 'data' => null],
            [
                'role'    => 'bot',
                'content' => 'La carrera de Medicina y Cirugía...',
                'data'    => [
                    'type'    => 'careers',
                    'matched' => true,
                    'careers' => [['id' => $careerId, 'name' => 'Medicina y Cirugía']],
                ],
            ],
        ];
    }

    private function service(): ChatbotService
    {
        return app(ChatbotService::class);
    }

    public function test_duration_follow_up_uses_career_from_history(): void
    {
        $this->seedCareers();
        $med = Career::where('code', 'MED-001')->first();

        $result = $this->service()->chatSuggestions(
            '¿Y cuánto dura esa carrera?',
            null,
            $this->historyWithSingleCareer($med->id),
        );

        $this->assertNotNull($result);
        $this->assertSame('careers', $result['type']);
        $this->assertCount(1, $result['careers']);
        $this->assertSame($med->id, $result['careers'][0]['id']);
        $this->assertSame(6, $result['careers'][0]['duration_years']);
    }

    public function test_price_follow_up_returns_the_previous_career_not_payments(): void
    {
        $this->seedCareers();
        $med = Career::where('code', 'MED-001')->first();

        $result = $this->service()->chatSuggestions(
            '¿Cuánto cuesta?',
            null,
            $this->historyWithSingleCareer($med->id),
        );

        $this->assertSame('careers', $result['type']);
        $this->assertSame($med->id, $result['careers'][0]['id']);
    }

    public function test_price_without_history_still_returns_payments(): void
    {
        $this->seedCareers();

        $result = $this->service()->chatSuggestions('¿Cuánto cuesta?');

        $this->assertSame('payments', $result['type']);
    }

    public function test_sede_follow_up_returns_the_previous_career(): void
    {
        $this->seedCareers();
        $med = Career::where('code', 'MED-001')->first();

        $result = $this->service()->chatSuggestions(
            '¿En qué sede la ofrecen?',
            null,
            $this->historyWithSingleCareer($med->id),
        );

        $this->assertSame('careers', $result['type']);
        $this->assertSame($med->id, $result['careers'][0]['id']);
    }

    public function test_ambiguous_multi_career_history_is_not_guessed(): void
    {
        $this->seedCareers();

        // Tarjeta previa con VARIAS carreras (p. ej. "¿qué carreras hay?"):
        // no se debe adivinar a cuál se refiere "¿cuánto cuesta?".
        $history = [
            ['role' => 'user', 'content' => '¿Qué carreras hay?', 'data' => null],
            [
                'role'    => 'bot',
                'content' => 'Medicina, Ingeniería en Sistemas, Enfermería...',
                'data'    => [
                    'type'    => 'careers',
                    'matched' => false,
                    'careers' => [
                        ['id' => 1, 'name' => 'Medicina y Cirugía'],
                        ['id' => 2, 'name' => 'Ingeniería en Sistemas'],
                    ],
                ],
            ],
        ];

        $result = $this->service()->chatSuggestions('¿Cuánto cuesta?', null, $history);

        $this->assertSame('payments', $result['type']);
    }

    private function studentWithSchedule(): Student
    {
        $career = Career::create([
            'name' => 'Ingeniería en Sistemas', 'code' => 'ISI-001',
            'faculty' => 'Ciencias y Tecnología', 'duration_years' => 5,
            'enrollment_fee' => 700, 'monthly_fee' => 400, 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $student = Student::create([
            'user_id'      => $user->id,
            'career_id'    => $career->id,
            'student_code' => '2020-0001U',
            'year_of_study' => 1,
            'group_code'   => 'A',
        ]);

        foreach ([
            ['Programación I', 'Ing. Javier Obando', 'Lunes', '08:00', '10:00', 'Lab. Informática'],
            ['Base de Datos I', 'Ing. Marta Ruiz',  'Martes', '10:00', '12:00', 'Aula 12'],
        ] as [$subject, $teacher, $day, $start, $end, $room]) {
            Schedule::create([
                'career_id'     => $career->id,
                'subject_name'  => $subject,
                'teacher_name'  => $teacher,
                'day_of_week'   => $day,
                'start_time'    => $start,
                'end_time'      => $end,
                'classroom'     => $room,
                'year_of_study' => 1,
                'group_code'    => 'A',
                'is_demo'       => true,
            ]);
        }

        return $student;
    }

    public function test_teacher_follow_up_focuses_the_subject_from_history(): void
    {
        $student = $this->studentWithSchedule();

        $history = [
            ['role' => 'user', 'content' => '¿En qué aula tengo Base de Datos I?', 'data' => null],
            [
                'role'    => 'bot',
                'content' => 'Tu clase de Base de Datos I...',
                'data'    => [
                    'type'     => 'schedule',
                    'matched'  => true,
                    'schedule' => ['query' => ['mode' => 'subject', 'day' => null, 'subject' => 'Base de Datos I']],
                ],
            ],
        ];

        $result = $this->service()->chatSuggestions('¿Quién es mi profesor de esa materia?', $student, $history);

        $this->assertSame('schedule', $result['type']);
        $this->assertSame('Base de Datos I', $result['schedule']['query']['subject']);
        $subjects = collect($result['schedule']['focused'])->pluck('subject');
        $this->assertTrue($subjects->contains('Base de Datos I'));
    }

    public function test_tomorrow_follow_up_returns_schedule_for_next_day(): void
    {
        $student = $this->studentWithSchedule();

        $result = $this->service()->chatSuggestions('¿Y mañana qué clases tengo?', $student);

        $this->assertSame('schedule', $result['type']);
        $this->assertSame('day', $result['schedule']['query']['mode']);
    }

    public function test_history_window_never_mixes_conversations(): void
    {
        $this->seedCareers();

        // Conversación de OTRA sesión con una carrera mencionada.
        $other = Conversation::create(['session_id' => 'sess_other', 'user_id' => null, 'title' => 'Otra']);
        Message::create(['conversation_id' => $other->id, 'role' => 'user', 'content' => '¿Cuánto cuesta Medicina?']);

        // Conversación actual (con un turno previo propio).
        $mine = Conversation::create(['session_id' => 'sess_mine', 'user_id' => null, 'title' => 'Mía']);
        Message::create(['conversation_id' => $mine->id, 'role' => 'user', 'content' => 'Hola, buenas tardes']);

        config(['services.groq.key' => 'test-key']);

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => 'respuesta']]],
            ], 200),
        ]);

        $this->postJson('/api/chat', [
            'message'    => '¿Cuánto cuesta?',
            'session_id' => 'sess_mine',
        ])->assertOk();

        Http::assertSent(function ($request) {
            $turns = collect($request['messages'] ?? [])
                ->where('role', '!=', 'system')
                ->pluck('content')
                ->implode("\n");

            // Incluye la conversación actual...
            return str_contains($turns, 'Hola, buenas tardes')
                && str_contains($turns, '¿Cuánto cuesta?')
                // ...pero nunca contenido de la conversación ajena.
                && ! str_contains($turns, 'Medicina');
        });
    }

    public function test_http_follow_up_resolves_career_from_current_conversation(): void
    {
        $this->seedCareers();
        $med = Career::where('code', 'MED-001')->first();

        $conversation = Conversation::create(['session_id' => 'sess_follow', 'user_id' => null, 'title' => 'Follow']);
        Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => '¿Cuánto cuesta Medicina?']);
        Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'bot',
            'content'         => 'Medicina y Cirugía cuesta...',
            'data'            => [
                'type'    => 'careers',
                'matched' => true,
                'careers' => [['id' => $med->id, 'name' => 'Medicina y Cirugía']],
            ],
        ]);

        config(['services.groq.key' => 'test-key']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);

        $this->postJson('/api/chat', [
            'message'    => '¿Y cuánto dura esa carrera?',
            'session_id' => 'sess_follow',
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'careers')
            ->assertJsonPath('data.careers.0.id', $med->id);
    }
}
