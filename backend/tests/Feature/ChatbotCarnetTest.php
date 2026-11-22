<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PROBLEMA 1 — Reconocimiento del carné estudiantil.
 *
 * Un carné (p. ej. "2024-CON-3189") debe reconocerse como identificación del
 * estudiante sin confundirlo con un código de carrera. El saludo con nombre
 * sólo se genera para el dueño autenticado del carné.
 */
class ChatbotCarnetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['student_access.demo' => true]);

        $careers = [
            ['name' => 'Contabilidad Pública y Finanzas', 'code' => 'CON-001', 'faculty' => 'Ciencias Económicas y Administrativas', 'duration_years' => 4, 'enrollment_fee' => 600, 'monthly_fee' => 340],
            ['name' => 'Medicina y Cirugía',              'code' => 'MED-001', 'faculty' => 'Ciencias de la Salud',                  'duration_years' => 6, 'enrollment_fee' => 800, 'monthly_fee' => 500],
            ['name' => 'Ingeniería en Sistemas',          'code' => 'ISI-001', 'faculty' => 'Ciencias y Tecnología',                 'duration_years' => 5, 'enrollment_fee' => 700, 'monthly_fee' => 400],
        ];

        foreach ($careers as $career) {
            Career::create($career + ['is_active' => true]);
        }

        $students = [
            ['name' => 'Gabriela Castillo', 'email' => 'estudiante11@example.com', 'code' => 'CON-001', 'student_code' => '2024-CON-3189'],
            ['name' => 'María José Téllez', 'email' => 'estudiante12@example.com', 'code' => 'MED-001', 'student_code' => '2024-MED-5837'],
            ['name' => 'Carlos Mendoza',    'email' => 'estudiante13@example.com', 'code' => 'ISI-001', 'student_code' => '2023-ISI-9264'],
        ];

        foreach ($students as $s) {
            $user = User::factory()->create(['name' => $s['name'], 'email' => $s['email']]);
            Student::create([
                'user_id'       => $user->id,
                'career_id'     => Career::where('code', $s['code'])->value('id'),
                'student_code'  => $s['student_code'],
                'year_of_study' => 1,
                'status'        => 'active',
            ]);
        }
    }

    private function service(): ChatbotService
    {
        return app(ChatbotService::class);
    }

    private function owner(): User
    {
        return User::where('email', 'estudiante11@example.com')->firstOrFail();
    }

    public function test_the_owner_can_receive_a_personalized_greeting(): void
    {
        $reply = $this->service()->processQuery('2024-CON-3189', $this->owner()->id);

        $this->assertSame('carnet_greeting', $reply['intent']);
        $this->assertStringContainsString('Gabriela Castillo', $reply['text']);
        $this->assertStringContainsString('Asistente Académico', $reply['text']);
    }

    public function test_carnet_greeting_matches_the_test_matrix(): void
    {
        foreach (['2024-MED-5837', '2023-ISI-9264'] as $carnet) {
            $reply = $this->service()->processQuery($carnet, $this->owner()->id);
            $this->assertSame('carnet_not_found', $reply['intent']);
            $this->assertStringNotContainsString('María José Téllez', $reply['text']);
            $this->assertStringNotContainsString('Carlos Mendoza', $reply['text']);
        }
    }

    public function test_carnet_accepts_lowercase_and_accidental_spaces(): void
    {
        foreach (['2024-con-3189', '2024 - CON - 3189', '  2024-CON-3189  '] as $input) {
            $this->assertSame(
                'carnet_greeting',
                $this->service()->processQuery($input, $this->owner()->id)['intent'],
                "Falló para: {$input}"
            );
        }
    }

    public function test_carnet_with_lead_in_phrase_is_recognized(): void
    {
        foreach (['Mi carné es 2024-CON-3189', 'mi carnet: 2024-CON-3189', 'Hola, mi carné es 2024-con-3189'] as $input) {
            $reply = $this->service()->processQuery($input, $this->owner()->id);
            $this->assertSame('carnet_greeting', $reply['intent'], "Falló para: {$input}");
            $this->assertStringContainsString('Gabriela Castillo', $reply['text']);
        }
    }

    public function test_unknown_carnet_is_neutral_and_reveals_no_identity(): void
    {
        $reply = $this->service()->processQuery('2024-XXX-9876');

        $this->assertSame('carnet_not_found', $reply['intent']);
        $this->assertStringNotContainsString('Gabriela', $reply['text']);
        $this->assertStringNotContainsString('Téllez', $reply['text']);
        $this->assertStringNotContainsString('Mendoza', $reply['text']);
    }

    public function test_carnet_shows_no_cards(): void
    {
        $this->assertNull($this->service()->chatSuggestions('2024-CON-3189'));
        $this->assertNull($this->service()->chatSuggestions('Mi carné es 2024-MED-5837'));
    }

    public function test_career_code_is_recognized_when_it_is_not_a_carnet(): void
    {
        // "CON-001" (sin año) sí debe reconocerse como la carrera de Contabilidad.
        $result = $this->service()->chatSuggestions('CON-001');

        $this->assertNotNull($result);
        $this->assertSame('careers', $result['type']);
        $this->assertSame('Contabilidad Pública y Finanzas', $result['careers'][0]['name']);
    }

    public function test_carnet_with_additional_question_is_not_treated_as_carnet_only(): void
    {
        // Con una pregunta adicional NO se secuestra como saludo.
        $this->assertFalse($this->service()->isCarnetMessage('2024-CON-3189 ¿cuánto cuesta?'));
        $this->assertTrue($this->service()->isCarnetMessage('2024-CON-3189'));
    }

    public function test_carnet_greeting_name_is_not_sent_to_ai_memory(): void
    {
        // El nombre del saludo no debe viajar a la IA en la ventana de memoria.
        $greeting = $this->service()->processQuery('2024-CON-3189', $this->owner()->id)['text'];
        $this->assertStringContainsString('Gabriela Castillo', $greeting);

        $history = [
            ['role' => 'user', 'content' => '2024-CON-3189', 'data' => null],
            ['role' => 'bot', 'content' => $greeting, 'data' => null],
        ];

        $redacted = \App\Models\Message::redactStudentGreeting($greeting);
        $this->assertStringNotContainsString('Gabriela Castillo', $redacted);
        $this->assertStringContainsString('Asistente Académico', $redacted);

        config(['services.groq.key' => 'test-key']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Catálogo']]]])]);
        $this->service()->processQuery('¿Qué carreras ofrecen?', null, $history);
        Http::assertSent(function ($request) {
            $this->assertCount(2, $request['messages']);
            $this->assertStringNotContainsString('Gabriela Castillo', json_encode($request['messages']));
            return true;
        });
    }

    public function test_public_chat_rejects_carnet_identity_even_in_demo_mode(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'no usar']]]], 200)]);

        $response = $this->postJson('/api/chat', [
            'message'    => '2024-CON-3189',
            'user_id' => $this->owner()->id,
            'carnet' => '2024-CON-3189',
        ]);

        $response->assertOk()
            ->assertJsonPath('intent', 'carnet_not_found')
            ->assertJsonPath('data', null);

        $this->assertStringNotContainsString('Gabriela Castillo', $response->json('reply'));
        $this->assertArrayNotHasKey('token', $response->json());

        // El saludo se genera en Laravel: no se consulta a la IA.
        Http::assertNothingSent();
    }

    public function test_public_history_redacts_legacy_greetings_and_excludes_owned_messages(): void
    {
        $owner = $this->owner();
        $conversation = Conversation::create(['session_id' => 'legacy-demo', 'title' => 'Consulta']);
        $greeting = $this->service()->processQuery('2024-CON-3189', $owner->id)['text'];

        $conversation->messages()->create(['role' => 'bot', 'content' => $greeting]);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
            'role' => 'bot',
            'content' => 'Dato privado del estudiante',
        ]);

        $this->getJson('/api/chat/history?session_id=legacy-demo')->assertForbidden();
        $this->getJson('/api/chat/history?session_id=legacy-demo', $this->guestChatHeaders('legacy-demo'))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', str_replace('¡Hola, Gabriela Castillo!', '¡Hola!', $greeting))
            ->assertJsonMissingPath('messages.0.user_id');
    }
}
