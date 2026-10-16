<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Privacidad: qué se transmite al proveedor de IA (Groq).
 *
 * - La identidad (nombre, carné) NO se envía por defecto.
 * - Los datos académicos privados (notas, exámenes, becas, tutorías) solo se
 *   incluyen cuando la consulta los pide explícitamente, nunca en cada mensaje.
 */
class ChatbotPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): array
    {
        $career = Career::create([
            'name' => 'Ingeniería en Sistemas', 'code' => 'ISI-001',
            'faculty' => 'Ciencias y Tecnología', 'duration_years' => 5,
            'enrollment_fee' => 700, 'monthly_fee' => 400, 'is_active' => true,
        ]);

        $user = User::factory()->create(['name' => 'Estudiante Privado']);
        $student = Student::create([
            'user_id'       => $user->id,
            'career_id'     => $career->id,
            'student_code'  => '2020-9999U',
            'year_of_study' => 2,
            'group_code'    => 'A',
        ]);

        Grade::create([
            'user_id'        => $user->id,
            'career_id'      => $career->id,
            'subject_name'   => 'Álgebra Lineal',
            'first_partial'  => 80,
            'second_partial' => 75,
            'final_exam'     => 70,
            'final_grade'    => 75,
            'status'         => 'approved',
            'period'         => 'I Cuatrimestre 2026',
        ]);

        return [$user, $student];
    }

    private function buildContext(string $message, int $userId): string
    {
        $method = new ReflectionMethod(ChatbotService::class, 'buildContext');
        $method->setAccessible(true);

        return $method->invoke(app(ChatbotService::class), $message, $userId);
    }

    public function test_identity_is_not_sent_by_default(): void
    {
        [$user] = $this->makeStudent();

        $ctx = $this->buildContext('¿Qué carreras hay?', $user->id);

        $this->assertStringNotContainsString('Estudiante Privado', $ctx);
        $this->assertStringNotContainsString('2020-9999U', $ctx);
    }

    public function test_identity_is_sent_only_when_explicitly_enabled(): void
    {
        [$user] = $this->makeStudent();
        config(['chatbot.privacy.send_identity' => true]);

        $ctx = $this->buildContext('¿Qué carreras hay?', $user->id);

        $this->assertStringContainsString('Estudiante Privado', $ctx);
        $this->assertStringContainsString('2020-9999U', $ctx);
    }

    public function test_personal_grades_are_not_sent_for_unrelated_questions(): void
    {
        [$user] = $this->makeStudent();

        $ctx = $this->buildContext('¿Qué carreras hay?', $user->id);

        $this->assertStringNotContainsString('NOTAS DEL', $ctx);
        $this->assertStringNotContainsString('Álgebra Lineal', $ctx);
    }

    public function test_personal_grades_are_sent_when_the_question_asks_for_them(): void
    {
        [$user] = $this->makeStudent();

        $ctx = $this->buildContext('¿Cómo van mis notas?', $user->id);

        $this->assertStringContainsString('NOTAS DEL', $ctx);
        $this->assertStringContainsString('Álgebra Lineal', $ctx);
    }

    public function test_personal_data_is_not_sent_when_disabled(): void
    {
        [$user] = $this->makeStudent();
        config(['chatbot.privacy.send_personal_data' => false]);

        $ctx = $this->buildContext('¿Cómo van mis notas?', $user->id);

        $this->assertStringNotContainsString('NOTAS DEL', $ctx);
        $this->assertStringNotContainsString('Álgebra Lineal', $ctx);
    }
}
