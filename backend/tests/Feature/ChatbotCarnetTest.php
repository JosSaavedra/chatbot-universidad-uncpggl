<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Student;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PROBLEMA 1 — Reconocimiento del carné estudiantil.
 *
 * Un carné (p. ej. "2024-CON-001") debe reconocerse como identificación del
 * estudiante y responder con un saludo personalizado generado por Laravel
 * (nombre desde la base de datos), NUNCA interpretando "CON-001" como el código
 * de la carrera de Contabilidad. La detección tiene prioridad sobre cualquier
 * otra intención y no emite tokens ni datos personales.
 */
class ChatbotCarnetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $careers = [
            ['name' => 'Contabilidad Pública y Finanzas', 'code' => 'CON-001', 'faculty' => 'Ciencias Económicas y Administrativas', 'duration_years' => 4, 'enrollment_fee' => 600, 'monthly_fee' => 340],
            ['name' => 'Medicina y Cirugía',              'code' => 'MED-001', 'faculty' => 'Ciencias de la Salud',                  'duration_years' => 6, 'enrollment_fee' => 800, 'monthly_fee' => 500],
            ['name' => 'Ingeniería en Sistemas',          'code' => 'ISI-001', 'faculty' => 'Ciencias y Tecnología',                 'duration_years' => 5, 'enrollment_fee' => 700, 'monthly_fee' => 400],
        ];

        foreach ($careers as $career) {
            Career::create($career + ['is_active' => true]);
        }

        $students = [
            ['name' => 'Gabriela Castillo', 'email' => 'gabriela@uncpggl.edu.ni', 'code' => 'CON-001', 'student_code' => '2024-CON-001'],
            ['name' => 'María José Téllez', 'email' => 'maria@uncpggl.edu.ni',    'code' => 'MED-001', 'student_code' => '2024-MED-001'],
            ['name' => 'Carlos Mendoza',    'email' => 'carlos@uncpggl.edu.ni',   'code' => 'ISI-001', 'student_code' => '2023-ISI-001'],
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

    public function test_known_carnet_returns_personalized_greeting(): void
    {
        $reply = $this->service()->processQuery('2024-CON-001');

        $this->assertSame('carnet_greeting', $reply['intent']);
        $this->assertStringContainsString('Gabriela Castillo', $reply['text']);
        $this->assertStringContainsString('Asistente Académico', $reply['text']);
    }

    public function test_carnet_greeting_matches_the_test_matrix(): void
    {
        $this->assertStringContainsString(
            'María José Téllez',
            $this->service()->processQuery('2024-MED-001')['text']
        );
        $this->assertStringContainsString(
            'Carlos Mendoza',
            $this->service()->processQuery('2023-ISI-001')['text']
        );
    }

    public function test_carnet_accepts_lowercase_and_accidental_spaces(): void
    {
        foreach (['2024-con-001', '2024 - CON - 001', '  2024-CON-001  '] as $input) {
            $this->assertSame(
                'carnet_greeting',
                $this->service()->processQuery($input)['intent'],
                "Falló para: {$input}"
            );
        }
    }

    public function test_carnet_with_lead_in_phrase_is_recognized(): void
    {
        foreach (['Mi carné es 2024-CON-001', 'mi carnet: 2024-CON-001', 'Hola, mi carné es 2024-con-001'] as $input) {
            $reply = $this->service()->processQuery($input);
            $this->assertSame('carnet_greeting', $reply['intent'], "Falló para: {$input}");
            $this->assertStringContainsString('Gabriela Castillo', $reply['text']);
        }
    }

    public function test_unknown_carnet_is_neutral_and_reveals_no_identity(): void
    {
        $reply = $this->service()->processQuery('2024-XXX-999');

        $this->assertSame('carnet_not_found', $reply['intent']);
        $this->assertStringNotContainsString('Gabriela', $reply['text']);
        $this->assertStringNotContainsString('Téllez', $reply['text']);
        $this->assertStringNotContainsString('Mendoza', $reply['text']);
    }

    public function test_carnet_shows_no_cards(): void
    {
        $this->assertNull($this->service()->chatSuggestions('2024-CON-001'));
        $this->assertNull($this->service()->chatSuggestions('Mi carné es 2024-MED-001'));
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
        $this->assertFalse($this->service()->isCarnetMessage('2024-CON-001 ¿cuánto cuesta?'));
        $this->assertTrue($this->service()->isCarnetMessage('2024-CON-001'));
    }

    public function test_carnet_greeting_name_is_not_sent_to_ai_memory(): void
    {
        // El nombre del saludo no debe viajar a la IA en la ventana de memoria.
        $greeting = $this->service()->processQuery('2024-CON-001')['text'];
        $this->assertStringContainsString('Gabriela Castillo', $greeting);

        $history = [
            ['role' => 'user', 'content' => '2024-CON-001', 'data' => null],
            ['role' => 'bot', 'content' => $greeting, 'data' => null],
        ];

        $method = new \ReflectionMethod(ChatbotService::class, 'memoryWindow');
        $method->setAccessible(true);
        $window = $method->invoke($this->service(), $history);

        $joined = implode(' ', array_column($window, 'content'));
        $this->assertStringNotContainsString('Gabriela Castillo', $joined);

        // El saludo se conserva (solo sin el nombre), no se elimina la memoria.
        $this->assertStringContainsString('Asistente Académico', $joined);
    }

    public function test_http_carnet_does_not_call_groq_and_returns_no_token(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'no usar']]]], 200)]);

        $response = $this->postJson('/api/chat', [
            'message'    => '2024-CON-001',
            'session_id' => 'sess_carnet_http',
        ]);

        $response->assertOk()
            ->assertJsonPath('intent', 'carnet_greeting')
            ->assertJsonPath('data', null);

        $this->assertStringContainsString('Gabriela Castillo', $response->json('reply'));
        $this->assertArrayNotHasKey('token', $response->json());

        // El saludo se genera en Laravel: no se consulta a la IA.
        Http::assertNothingSent();
    }
}
