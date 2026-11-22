<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Scholarship;
use App\Models\Student;
use App\Models\Tutoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Autorización de becas y tutorías.
 *
 * Las becas y las tutorías son registros personales del estudiante: sólo su
 * dueño puede consultarlos y las operaciones administrativas quedan reservadas
 * a los administradores. La identidad se toma siempre del token de Sanctum,
 * nunca de un `user_id` enviado por React.
 */
class ScholarshipTutoringAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function career(): Career
    {
        return Career::create([
            'code'           => 'CON-AUT-001',
            'name'           => 'Contabilidad Pública y Finanzas',
            'faculty'        => 'Ciencias Económicas',
            'duration_years' => 4,
            'enrollment_fee' => 600,
            'monthly_fee'    => 340,
            'is_active'      => true,
        ]);
    }

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\Student}
     */
    private function makeStudent(Career $career, string $code, string $name): array
    {
        $user = User::factory()->create(['name' => $name, 'is_admin' => false]);

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

    private function makeAdmin(): User
    {
        return User::factory()->create(['name' => 'Administrador', 'is_admin' => true]);
    }

    private function token(User $user): string
    {
        return $user->createToken('password-login')->plainTextToken;
    }

    private function makeScholarship(User $user, array $overrides = []): Scholarship
    {
        return Scholarship::create(array_merge([
            'user_id'             => $user->id,
            'type'                => 'academic',
            'coverage_percentage' => 50,
            'status'              => 'active',
            'start_date'          => '2026-01-01',
            'end_date'            => '2026-12-31',
            'description'         => 'Beca de prueba',
        ], $overrides));
    }

    private function makeTutoring(User $user, array $overrides = []): Tutoring
    {
        return Tutoring::create(array_merge([
            'user_id'               => $user->id,
            'subject_name'          => 'Anatomía Humana',
            'tutor_name'            => 'Dr. Roberto Chavarría',
            'scheduled_at'          => now()->addWeek()->toDateTimeString(),
            'status'                => 'scheduled',
            'meeting_link_or_place' => 'Aula 201',
        ], $overrides));
    }

    /* ------------------------------------------------------------------ */
    /* Becas                                                               */
    /* ------------------------------------------------------------------ */

    public function test_a_student_only_sees_their_own_scholarships(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $this->makeScholarship($userA, ['description' => 'Beca propia']);
        $this->makeScholarship($userB, ['description' => 'Beca ajena']);

        $response = $this->withToken($this->token($userA))
            ->getJson('/api/scholarships')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.user_id', $userA->id);
        $this->assertStringNotContainsString('Beca ajena', $response->getContent());
    }

    public function test_a_student_cannot_read_another_students_scholarship(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $scholarshipB = $this->makeScholarship($userB);

        $this->withToken($this->token($userA))
            ->getJson('/api/scholarships/' . $scholarshipB->id)
            ->assertStatus(403);
    }

    public function test_a_student_can_read_their_own_scholarship(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');

        $scholarship = $this->makeScholarship($userA);

        $this->withToken($this->token($userA))
            ->getJson('/api/scholarships/' . $scholarship->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $scholarship->id)
            ->assertJsonPath('data.user_id', $userA->id);
    }

    public function test_the_scholarship_list_ignores_a_foreign_user_id(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $this->makeScholarship($userA);
        $this->makeScholarship($userB);

        $response = $this->withToken($this->token($userA))
            ->getJson('/api/scholarships?user_id=' . $userB->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.user_id', $userA->id);
    }

    public function test_a_student_cannot_create_a_scholarship(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');

        $this->withToken($this->token($userA))
            ->postJson('/api/scholarships', [
                'user_id'             => $userA->id,
                'type'                => 'academic',
                'coverage_percentage' => 100,
                'status'              => 'active',
                'start_date'          => '2026-01-01',
                'end_date'            => '2026-12-31',
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('scholarships', 0);
    }

    public function test_a_student_cannot_update_or_delete_a_scholarship(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $own = $this->makeScholarship($userA);
        $foreign = $this->makeScholarship($userB);
        $token = $this->token($userA);

        // Ni siquiera su propia beca puede modificarla o eliminarla.
        $this->withToken($token)
            ->putJson('/api/scholarships/' . $own->id, ['coverage_percentage' => 100, 'status' => 'cancelled'])
            ->assertStatus(403);

        $this->withToken($token)
            ->deleteJson('/api/scholarships/' . $own->id)
            ->assertStatus(403);

        // Tampoco las de otro estudiante.
        $this->withToken($token)
            ->putJson('/api/scholarships/' . $foreign->id, ['coverage_percentage' => 100])
            ->assertStatus(403);

        $this->withToken($token)
            ->deleteJson('/api/scholarships/' . $foreign->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('scholarships', ['id' => $own->id, 'coverage_percentage' => 50]);
        $this->assertDatabaseHas('scholarships', ['id' => $foreign->id]);
    }

    public function test_an_admin_can_manage_scholarships(): void
    {
        $career = $this->career();
        [$studentUser] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        $admin = $this->makeAdmin();
        $token = $this->token($admin);

        $created = $this->withToken($token)
            ->postJson('/api/scholarships', [
                'user_id'             => $studentUser->id,
                'type'                => 'academic',
                'coverage_percentage' => 60,
                'status'              => 'active',
                'start_date'          => '2026-01-01',
                'end_date'            => '2026-12-31',
                'description'         => 'Beca asignada por la administración',
            ])
            ->assertStatus(201)
            ->json('data');

        // El administrador lista todas y puede filtrar por estudiante.
        $this->withToken($token)
            ->getJson('/api/scholarships')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson('/api/scholarships?user_id=' . $studentUser->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson('/api/scholarships/' . $created['id'])
            ->assertStatus(200);

        $this->withToken($token)
            ->putJson('/api/scholarships/' . $created['id'], ['coverage_percentage' => 75])
            ->assertStatus(200)
            ->assertJsonPath('data.coverage_percentage', 75);

        $this->withToken($token)
            ->deleteJson('/api/scholarships/' . $created['id'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('scholarships', ['id' => $created['id']]);
    }

    public function test_the_scholarship_owner_cannot_be_changed_from_the_client(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $scholarship = $this->makeScholarship($userA);
        $admin = $this->makeAdmin();

        $this->withToken($this->token($admin))
            ->putJson('/api/scholarships/' . $scholarship->id, [
                'coverage_percentage' => 80,
                'user_id'             => $userB->id,
            ])
            ->assertStatus(200);

        $this->assertSame((int) $userA->id, (int) $scholarship->fresh()->user_id);
    }

    /* ------------------------------------------------------------------ */
    /* Tutorías                                                            */
    /* ------------------------------------------------------------------ */

    public function test_a_student_only_sees_their_own_tutorings(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $this->makeTutoring($userA, ['subject_name' => 'Anatomía Humana']);
        $this->makeTutoring($userB, ['subject_name' => 'Base de Datos II']);

        $response = $this->withToken($this->token($userA))
            ->getJson('/api/tutorings')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.user_id', $userA->id);
        $this->assertStringNotContainsString('Base de Datos II', $response->getContent());
    }

    public function test_a_student_cannot_read_another_students_tutoring(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $tutoringB = $this->makeTutoring($userB);

        $this->withToken($this->token($userA))
            ->getJson('/api/tutorings/' . $tutoringB->id)
            ->assertStatus(403);
    }

    public function test_a_student_can_read_their_own_tutoring(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');

        $tutoring = $this->makeTutoring($userA);

        $this->withToken($this->token($userA))
            ->getJson('/api/tutorings/' . $tutoring->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $tutoring->id)
            ->assertJsonPath('data.user_id', $userA->id);
    }

    public function test_the_tutoring_list_ignores_a_foreign_user_id(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $this->makeTutoring($userA);
        $this->makeTutoring($userB);

        $response = $this->withToken($this->token($userA))
            ->getJson('/api/tutorings?user_id=' . $userB->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.user_id', $userA->id);
    }

    public function test_a_student_cannot_create_a_tutoring(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');

        $this->withToken($this->token($userA))
            ->postJson('/api/tutorings', [
                'user_id'               => $userA->id,
                'subject_name'          => 'Anatomía Humana',
                'tutor_name'            => 'Dr. Roberto Chavarría',
                'scheduled_at'          => now()->addWeek()->toDateTimeString(),
                'status'                => 'scheduled',
                'meeting_link_or_place' => 'Aula 201',
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('tutorings', 0);
    }

    public function test_a_student_cannot_update_or_delete_a_tutoring(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $own = $this->makeTutoring($userA);
        $foreign = $this->makeTutoring($userB);
        $token = $this->token($userA);

        // Ni siquiera su propia tutoría puede reprogramarla o cancelarla.
        $this->withToken($token)
            ->putJson('/api/tutorings/' . $own->id, ['status' => 'cancelled', 'subject_name' => 'Cambiada'])
            ->assertStatus(403);

        $this->withToken($token)
            ->deleteJson('/api/tutorings/' . $own->id)
            ->assertStatus(403);

        // Tampoco las de otro estudiante.
        $this->withToken($token)
            ->putJson('/api/tutorings/' . $foreign->id, ['status' => 'cancelled'])
            ->assertStatus(403);

        $this->withToken($token)
            ->deleteJson('/api/tutorings/' . $foreign->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('tutorings', ['id' => $own->id, 'status' => 'scheduled']);
        $this->assertDatabaseHas('tutorings', ['id' => $foreign->id]);
    }

    public function test_an_admin_can_manage_tutorings(): void
    {
        $career = $this->career();
        [$studentUser] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        $admin = $this->makeAdmin();
        $token = $this->token($admin);

        $created = $this->withToken($token)
            ->postJson('/api/tutorings', [
                'user_id'               => $studentUser->id,
                'subject_name'          => 'Anatomía Humana',
                'tutor_name'            => 'Dr. Roberto Chavarría',
                'scheduled_at'          => now()->addWeek()->toDateTimeString(),
                'status'                => 'scheduled',
                'meeting_link_or_place' => 'Aula 201',
            ])
            ->assertStatus(201)
            ->json('data');

        $this->withToken($token)
            ->getJson('/api/tutorings')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson('/api/tutorings?user_id=' . $studentUser->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson('/api/tutorings/' . $created['id'])
            ->assertStatus(200);

        $this->withToken($token)
            ->putJson('/api/tutorings/' . $created['id'], ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->withToken($token)
            ->deleteJson('/api/tutorings/' . $created['id'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('tutorings', ['id' => $created['id']]);
    }

    public function test_the_tutoring_owner_cannot_be_changed_from_the_client(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $tutoring = $this->makeTutoring($userA);
        $admin = $this->makeAdmin();

        $this->withToken($this->token($admin))
            ->putJson('/api/tutorings/' . $tutoring->id, [
                'status'  => 'completed',
                'user_id' => $userB->id,
            ])
            ->assertStatus(200);

        $this->assertSame((int) $userA->id, (int) $tutoring->fresh()->user_id);
    }

    public function test_the_policies_deny_every_write_and_foreign_read_to_a_student(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');
        $admin = $this->makeAdmin();

        $ownScholarship = $this->makeScholarship($userA);
        $foreignScholarship = $this->makeScholarship($userB);
        $ownTutoring = $this->makeTutoring($userA);
        $foreignTutoring = $this->makeTutoring($userB);

        // Lectura: sólo el propio dueño o el administrador.
        $this->assertTrue(Gate::forUser($userA)->allows('view', $ownScholarship));
        $this->assertFalse(Gate::forUser($userA)->allows('view', $foreignScholarship));
        $this->assertTrue(Gate::forUser($userA)->allows('view', $ownTutoring));
        $this->assertFalse(Gate::forUser($userA)->allows('view', $foreignTutoring));

        // Escritura: reservada al administrador, incluso sobre sus propios
        // registros.
        $this->assertFalse(Gate::forUser($userA)->allows('create', Scholarship::class));
        $this->assertFalse(Gate::forUser($userA)->allows('update', $ownScholarship));
        $this->assertFalse(Gate::forUser($userA)->allows('delete', $ownScholarship));
        $this->assertFalse(Gate::forUser($userA)->allows('create', Tutoring::class));
        $this->assertFalse(Gate::forUser($userA)->allows('update', $ownTutoring));
        $this->assertFalse(Gate::forUser($userA)->allows('delete', $ownTutoring));

        // El administrador conserva todas las capacidades.
        $this->assertTrue(Gate::forUser($admin)->allows('create', Scholarship::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $foreignScholarship));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $foreignScholarship));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $foreignScholarship));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Tutoring::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $foreignTutoring));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $foreignTutoring));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $foreignTutoring));
    }
}
