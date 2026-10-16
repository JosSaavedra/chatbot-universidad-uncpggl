<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriz de intención del chatbot: asegura que cada tipo de consulta muestre la
 * tarjeta correcta (carreras, admisión, calendario, pagos, horario, opciones de
 * matrícula) o ninguna. Sirve de red de seguridad ante cambios en las reglas.
 */
class ChatbotIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $careers = [
            ['name' => 'Medicina y Cirugía',     'code' => 'MED-001', 'faculty' => 'Ciencias de la Salud',                  'duration_years' => 6, 'enrollment_fee' => 800, 'monthly_fee' => 500],
            ['name' => 'Ingeniería en Sistemas', 'code' => 'ISI-001', 'faculty' => 'Ciencias y Tecnología',                 'duration_years' => 5, 'enrollment_fee' => 700, 'monthly_fee' => 400],
            ['name' => 'Ingeniería Civil',       'code' => 'ICI-001', 'faculty' => 'Arquitectura e Ingeniería',             'duration_years' => 5, 'enrollment_fee' => 700, 'monthly_fee' => 420],
            ['name' => 'Enfermería',             'code' => 'ENF-001', 'faculty' => 'Ciencias de la Salud',                  'duration_years' => 4, 'enrollment_fee' => 600, 'monthly_fee' => 350],
            ['name' => 'Administración de Empresas', 'code' => 'ADM-001', 'faculty' => 'Ciencias Económicas y Administrativas', 'duration_years' => 4, 'enrollment_fee' => 600, 'monthly_fee' => 350],
        ];

        foreach ($careers as $career) {
            Career::create($career + ['is_active' => true]);
        }
    }

    private function suggestions(string $message): ?array
    {
        return app(ChatbotService::class)->chatSuggestions($message);
    }

    public function test_careers_question_returns_careers_card(): void
    {
        $result = $this->suggestions('¿Qué carreras ofrece la universidad?');

        $this->assertNotNull($result);
        $this->assertSame('careers', $result['type']);
    }

    public function test_price_of_specific_career_returns_that_career(): void
    {
        $result = $this->suggestions('¿Cuánto cuesta Medicina?');

        $this->assertSame('careers', $result['type']);
        $names = collect($result['careers'])->pluck('name');
        $this->assertTrue($names->contains('Medicina y Cirugía'));
    }

    public function test_requirements_question_returns_admission_card(): void
    {
        $result = $this->suggestions('¿Qué requisitos necesito para inscribirme?');

        $this->assertSame('admission', $result['type']);
    }

    public function test_enrollment_dates_question_returns_calendar_card(): void
    {
        $result = $this->suggestions('¿Cuándo son las fechas de matrícula?');

        $this->assertSame('calendar', $result['type']);
    }

    public function test_bare_enrollment_returns_options_card(): void
    {
        $result = $this->suggestions('matrícula');

        $this->assertSame('matricula_options', $result['type']);
    }

    public function test_personal_payments_without_session_returns_payments_card(): void
    {
        $result = $this->suggestions('¿Cuáles son mis pagos?');

        $this->assertSame('payments', $result['type']);
        $this->assertFalse($result['personalized']);
    }

    public function test_class_schedule_without_session_returns_schedule_picker(): void
    {
        $result = $this->suggestions('¿Cuál es el horario de clases de Medicina?');

        $this->assertSame('schedule_picker', $result['type']);
    }

    public function test_sedes_question_is_about_careers(): void
    {
        $result = $this->suggestions('¿En qué sedes se imparte Ingeniería en Sistemas?');

        $this->assertNotNull($result);
    }

    public function test_unrelated_message_returns_no_card(): void
    {
        $this->assertNull($this->suggestions('¿Cómo está el clima hoy?'));
    }
}
