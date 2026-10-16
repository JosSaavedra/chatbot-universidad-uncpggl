<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo "Mis notas": endpoint autenticado, agrupación por período y
 * autorización de GradeController.
 *
 * Se comprueba que cada estudiante autenticado ve SOLO sus propias
 * calificaciones y que un estudiante normal no puede leer, crear, modificar ni
 * eliminar notas de otros usuarios. Se usan exclusivamente carnés y datos
 * ficticios de demostración.
 */
class MyGradesTest extends TestCase
{
    use RefreshDatabase;

    private function career(): Career
    {
        return Career::create([
            'name' => 'Contabilidad Pública y Finanzas', 'code' => 'CON-001',
            'faculty' => 'Ciencias Económicas', 'duration_years' => 4,
            'enrollment_fee' => 600, 'monthly_fee' => 340, 'is_active' => true,
        ]);
    }

    private function makeStudent(Career $career, string $code, string $name): array
    {
        $user = User::factory()->create(['name' => $name]);

        $student = Student::create([
            'user_id'       => $user->id,
            'career_id'     => $career->id,
            'student_code'  => $code,
            'year_of_study' => 2,
            'group_code'    => 'A',
            'status'        => 'active',
        ]);

        return [$user, $student];
    }

    private function makeGrade(User $user, Career $career, array $overrides = []): Grade
    {
        return Grade::create(array_merge([
            'user_id'        => $user->id,
            'career_id'      => $career->id,
            'subject_name'   => 'Contabilidad General',
            'first_partial'  => 80,
            'second_partial' => 85,
            'final_exam'     => 90,
            'final_grade'    => 88,
            'status'         => 'approved',
            'period'         => '2025 · I',
        ], $overrides));
    }

    public function test_my_grades_requires_authentication(): void
    {
        $this->getJson('/api/my-grades')->assertStatus(401);
    }

    public function test_an_invalid_or_expired_token_is_rejected(): void
    {
        $this->withToken('token-invalido')->getJson('/api/my-grades')->assertStatus(401);
    }

    public function test_student_sees_only_their_own_grades(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, '2024-CON-001', 'Gabriela Castillo');
        [$userB] = $this->makeStudent($career, '2024-MED-001', 'María José Téllez');

        $this->makeGrade($userA, $career, ['subject_name' => 'Contabilidad General']);
        $this->makeGrade($userB, $career, ['subject_name' => 'Anatomía Humana']);

        $token = $userA->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/my-grades')->assertOk();

        $response->assertJsonPath('data.student.carnet', '2024-CON-001');
        $response->assertJsonPath('data.student.name', 'Gabriela Castillo');
        $response->assertJsonPath('data.student.career', 'Contabilidad Pública y Finanzas');
        $response->assertJsonCount(1, 'data.periods');
        $response->assertJsonCount(1, 'data.periods.0.subjects');
        $response->assertJsonPath('data.periods.0.subjects.0.subject_name', 'Contabilidad General');
        $response->assertJsonPath('data.summary.approved', 1);
        $response->assertJsonPath('data.summary.total_subjects', 1);

        // Nunca debe aparecer la asignatura del otro estudiante.
        $this->assertStringNotContainsString('Anatomía Humana', $response->getContent());
    }

    public function test_my_grades_ignores_another_user_id_or_carnet(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, '2024-CON-001', 'Gabriela Castillo');
        [$userB] = $this->makeStudent($career, '2024-MED-001', 'María José Téllez');

        $this->makeGrade($userA, $career, ['subject_name' => 'Contabilidad General']);
        $this->makeGrade($userB, $career, ['subject_name' => 'Anatomía Humana']);

        $token = $userA->createToken('test')->plainTextToken;

        // Intento de "cambiar de identidad" por query string: se ignora.
        $response = $this->withToken($token)
            ->getJson('/api/my-grades?user_id=' . $userB->id . '&carnet=2024-MED-001')
            ->assertOk();

        $response->assertJsonPath('data.student.carnet', '2024-CON-001');
        $this->assertStringNotContainsString('Anatomía Humana', $response->getContent());
    }

    public function test_my_grades_are_grouped_by_academic_period(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, '2023-ISI-001', 'Carlos Mendoza');

        $this->makeGrade($user, $career, ['subject_name' => 'Programación I', 'period' => '2025 · I']);
        $this->makeGrade($user, $career, ['subject_name' => 'Bases de Datos', 'period' => '2025 · II']);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/my-grades')
            ->assertOk()
            ->assertJsonCount(2, 'data.periods')
            ->assertJsonCount(1, 'data.periods.0.subjects')
            ->assertJsonCount(1, 'data.periods.1.subjects');
    }

    public function test_my_grades_reports_no_records_when_there_are_none(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, '2024-ADM-001', 'Estudiante Sin Notas');

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/my-grades')
            ->assertOk()
            ->assertJsonCount(0, 'data.periods')
            ->assertJsonPath('data.summary.total_subjects', 0);
    }

    public function test_grades_index_only_returns_the_authenticated_students_own_grades(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, '2024-CON-001', 'Gabriela Castillo');
        [$userB] = $this->makeStudent($career, '2024-MED-001', 'María José Téllez');

        $this->makeGrade($userA, $career);
        $this->makeGrade($userB, $career);

        $token = $userA->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/grades')->assertOk();

        foreach ($response->json('data') as $grade) {
            $this->assertSame($userA->id, $grade['user_id']);
        }
    }

    public function test_a_student_cannot_view_another_students_grade(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, '2024-CON-001', 'Gabriela Castillo');
        [$userB] = $this->makeStudent($career, '2024-MED-001', 'María José Téllez');

        $gradeB = $this->makeGrade($userB, $career);

        $token = $userA->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/grades/' . $gradeB->id)->assertStatus(403);
    }

    public function test_a_student_cannot_create_grades(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, '2024-CON-001', 'Gabriela Castillo');

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/grades', [
            'user_id'      => $user->id,
            'career_id'    => $career->id,
            'subject_name' => 'Matemática Financiera',
            'period'       => '2025 · I',
            'status'       => 'approved',
        ])->assertStatus(403);
    }

    public function test_a_student_cannot_update_or_delete_grades(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, '2024-CON-001', 'Gabriela Castillo');
        $grade = $this->makeGrade($user, $career);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/grades/' . $grade->id, ['final_grade' => 100])
            ->assertStatus(403);

        $this->withToken($token)
            ->deleteJson('/api/grades/' . $grade->id)
            ->assertStatus(403);
    }

    public function test_an_admin_can_manage_grades(): void
    {
        $career = $this->career();
        [$studentUser] = $this->makeStudent($career, '2024-CON-001', 'Gabriela Castillo');

        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('test')->plainTextToken;

        // Crear
        $created = $this->withToken($token)->postJson('/api/grades', [
            'user_id'      => $studentUser->id,
            'career_id'    => $career->id,
            'subject_name' => 'Auditoría',
            'period'       => '2025 · I',
            'final_grade'  => 91,
            'status'       => 'approved',
        ])->assertStatus(201)->json('data');

        // Ver cualquiera
        $this->withToken($token)->getJson('/api/grades/' . $created['id'])->assertOk();

        // Actualizar
        $this->withToken($token)
            ->putJson('/api/grades/' . $created['id'], ['final_grade' => 95])
            ->assertOk()
            ->assertJsonPath('data.final_grade', 95);

        // Eliminar
        $this->withToken($token)->deleteJson('/api/grades/' . $created['id'])->assertOk();
    }

    public function test_an_admin_can_list_all_grades(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, '2024-CON-001', 'Gabriela Castillo');
        [$userB] = $this->makeStudent($career, '2024-MED-001', 'María José Téllez');

        $this->makeGrade($userA, $career);
        $this->makeGrade($userB, $career);

        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/grades')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_chat_directs_grades_query_to_the_module_without_asking_to_log_in(): void
    {
        $service = app(ChatbotService::class);

        // Con sesión válida: no se pide iniciar sesión.
        $authReply = $service->processQuery('Quiero saber mis notas', 123);
        $this->assertSame('grades_module', $authReply['intent']);
        $this->assertStringNotContainsString('iniciar sesión', mb_strtolower($authReply['text']));

        // Sin sesión: se solicita autenticación.
        $anonReply = $service->processQuery('Quiero saber mis notas');
        $this->assertSame('fallback_auth_required', $anonReply['intent']);
        $this->assertStringContainsString('iniciar sesión', mb_strtolower($anonReply['text']));
    }

    public function test_chat_suggestions_offer_the_grades_module(): void
    {
        $service = app(ChatbotService::class);

        $suggestion = $service->chatSuggestions('¿Cuáles son mis notas?');
        $this->assertSame('grades', $suggestion['type']);
        $this->assertFalse($suggestion['personalized']);

        $career = $this->career();
        [, $student] = $this->makeStudent($career, '2024-CON-001', 'Gabriela Castillo');

        $personalized = $service->chatSuggestions('¿Cuáles son mis notas?', $student);
        $this->assertSame('grades', $personalized['type']);
        $this->assertTrue($personalized['personalized']);
    }

    public function test_admission_documents_about_high_school_grades_are_not_diverted(): void
    {
        $service = app(ChatbotService::class);

        // "notas de bachillerato" es documentación de admisión, no notas del
        // alumno: no debe abrir el módulo "Mis notas".
        $suggestion = $service->chatSuggestions('¿Debo llevar notas de bachillerato?');
        $this->assertNotSame('grades', $suggestion['type'] ?? null);
    }
}
