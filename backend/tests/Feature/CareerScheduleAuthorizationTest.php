<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Autorización de carreras y horarios.
 *
 * Comprueba que las consultas que ya usa el chatbot (catálogo público de
 * carreras y horarios generales) siguen funcionando, que el horario personal
 * devuelve únicamente el del estudiante autenticado y que toda escritura
 * (alta, edición y borrado) queda reservada a los administradores.
 */
class CareerScheduleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function career(array $overrides = []): Career
    {
        return Career::create(array_merge([
            'code'           => 'CON-AUT-001',
            'name'           => 'Contabilidad Pública y Finanzas',
            'faculty'        => 'Ciencias Económicas',
            'duration_years' => 4,
            'enrollment_fee' => 600,
            'monthly_fee'    => 340,
            'is_active'      => true,
        ], $overrides));
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

    private function makeSchedule(Career $career, string $subject = 'Contabilidad General'): Schedule
    {
        return Schedule::create([
            'career_id'     => $career->id,
            'subject_name'  => $subject,
            'teacher_name'  => 'Lic. Rosa Espinoza',
            'day_of_week'   => 'Lunes',
            'start_time'    => '08:00',
            'end_time'      => '10:00',
            'classroom'     => 'Aula 101',
            'year_of_study' => 2,
            'group_code'    => 'A',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Carreras                                                            */
    /* ------------------------------------------------------------------ */

    public function test_schedule_only_serializes_teacher_fields_needed_by_the_view(): void
    {
        $career = $this->career();
        [$student] = $this->makeStudent($career, 'TEST-DOC-001', 'Estudiante');
        $schedule = $this->makeSchedule($career);
        $teacher = \App\Models\Teacher::create([
            'full_name' => 'Docente',
            'email' => 'profesor@example.test',
            'phone' => '12345678',
        ]);
        $schedule->teacher()->associate($teacher)->save();

        $this->withToken($this->token($student))->getJson('/api/schedules/'.$schedule->id)
            ->assertOk()->assertJsonPath('data.teacher.full_name', 'Docente')
            ->assertJsonMissingPath('data.teacher.email')->assertJsonMissingPath('data.teacher.phone');
        $this->getJson('/api/schedules')->assertOk()->assertJsonPath('data.0.teacher.full_name', 'Docente')
            ->assertJsonMissingPath('data.0.teacher.email')->assertJsonMissingPath('data.0.teacher.phone');
    }

    public function test_a_student_cannot_create_a_career(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');

        $this->withToken($this->token($user))
            ->postJson('/api/careers', [
                'code'           => 'CON-AUT-002',
                'name'           => 'Carrera Inventada',
                'duration_years' => 4,
                'enrollment_fee' => 999,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('careers', ['code' => 'CON-AUT-002']);
    }

    public function test_a_student_cannot_update_a_career_or_its_fees(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');

        $this->withToken($this->token($user))
            ->putJson('/api/careers/' . $career->id, [
                'name'           => 'Carrera Renombrada',
                'enrollment_fee' => 1,
                'monthly_fee'    => 1,
                'is_active'      => false,
            ])
            ->assertStatus(403);

        $fresh = $career->fresh();
        $this->assertEquals(600, $fresh->enrollment_fee);
        $this->assertEquals(340, $fresh->monthly_fee);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertSame('Contabilidad Pública y Finanzas', $fresh->name);
    }

    public function test_a_student_cannot_delete_a_career(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');

        $this->withToken($this->token($user))
            ->deleteJson('/api/careers/' . $career->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('careers', ['id' => $career->id]);
    }

    public function test_an_admin_can_manage_careers(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->token($admin);

        $created = $this->withToken($token)
            ->postJson('/api/careers', [
                'code'           => 'CON-AUT-009',
                'name'           => 'Ingeniería en Sistemas',
                'faculty'        => 'Ciencias Exactas',
                'duration_years' => 5,
                'enrollment_fee' => 850,
                'monthly_fee'    => 420,
            ])
            ->assertStatus(201)
            ->json('data');

        $this->withToken($token)
            ->putJson('/api/careers/' . $created['id'], [
                'enrollment_fee' => 900,
                'is_active'      => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.enrollment_fee', 900)
            ->assertJsonPath('data.is_active', false);

        $this->withToken($token)
            ->deleteJson('/api/careers/' . $created['id'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('careers', ['id' => $created['id']]);
    }

    /* ------------------------------------------------------------------ */
    /* Horarios                                                            */
    /* ------------------------------------------------------------------ */

    public function test_a_student_cannot_create_a_schedule(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');

        $this->withToken($this->token($user))
            ->postJson('/api/schedules', [
                'career_id'     => $career->id,
                'subject_name'  => 'Programación I',
                'day_of_week'   => 'Martes',
                'start_time'    => '08:00',
                'end_time'      => '10:00',
                'classroom'     => 'Aula 102',
                'teacher_name'  => 'Ing. Pedro Gutiérrez',
                'year_of_study' => 2,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('schedules', ['subject_name' => 'Programación I']);
    }

    public function test_a_student_cannot_update_or_delete_a_schedule(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        $schedule = $this->makeSchedule($career);
        $token = $this->token($user);

        $this->withToken($token)
            ->putJson('/api/schedules/' . $schedule->id, [
                'subject_name' => 'Asignatura Renombrada',
                'classroom'    => 'Aula 999',
                'start_time'   => '14:00',
            ])
            ->assertStatus(403);

        $this->withToken($token)
            ->deleteJson('/api/schedules/' . $schedule->id)
            ->assertStatus(403);

        $fresh = $schedule->fresh();
        $this->assertSame('Contabilidad General', $fresh->subject_name);
        $this->assertSame('Aula 101', $fresh->classroom);
        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }

    public function test_an_admin_can_manage_schedules(): void
    {
        $career = $this->career();
        $admin = $this->makeAdmin();
        $token = $this->token($admin);

        $created = $this->withToken($token)
            ->postJson('/api/schedules', [
                'career_id'     => $career->id,
                'subject_name'  => 'Programación I',
                'day_of_week'   => 'Martes',
                'start_time'    => '08:00',
                'end_time'      => '10:00',
                'classroom'     => 'Aula 102',
                'teacher_name'  => 'Ing. Pedro Gutiérrez',
                'year_of_study' => 2,
            ])
            ->assertStatus(201)
            ->json('data');

        $this->withToken($token)
            ->putJson('/api/schedules/' . $created['id'], ['classroom' => 'Aula 303'])
            ->assertStatus(200)
            ->assertJsonPath('data.classroom', 'Aula 303');

        $this->withToken($token)
            ->deleteJson('/api/schedules/' . $created['id'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('schedules', ['id' => $created['id']]);
    }

    public function test_a_student_can_read_schedules_and_their_own_personal_schedule(): void
    {
        $career = $this->career();
        [$userA, $studentA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [, $studentB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $this->makeSchedule($career, 'Contabilidad General');
        $token = $this->token($userA);

        // Catálogo de bloques de horario: accesible para el estudiante.
        $this->withToken($token)->getJson('/api/schedules')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/schedules/1')->assertStatus(200);

        // Horario personal: sólo el del propio estudiante.
        $response = $this->withToken($token)->getJson('/api/my-schedule')->assertStatus(200);

        $response->assertJsonPath('data.student.carnet', $studentA->student_code);
        $response->assertJsonPath('data.student.group', 'A');
        $this->assertStringNotContainsString($studentB->student_code, $response->getContent());
    }

    public function test_the_personal_schedule_ignores_an_identity_sent_by_the_client(): void
    {
        $career = $this->career();
        [$userA] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        [$userB, $studentB] = $this->makeStudent($career, 'TEST-AUT-002', 'Otro Estudiante');

        $response = $this->withToken($this->token($userA))
            ->getJson('/api/my-schedule?user_id=' . $userB->id . '&carnet=' . $studentB->student_code)
            ->assertStatus(200);

        $response->assertJsonPath('data.student.carnet', 'TEST-AUT-001');
        $this->assertStringNotContainsString('TEST-AUT-002', $response->getContent());
    }

    public function test_the_policies_deny_every_write_to_a_student(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        $admin = $this->makeAdmin();
        $schedule = $this->makeSchedule($career);

        // La capa de Policy debe rechazar por sí sola, aunque el middleware
        // `admin` de la ruta se Retire o se agregue una ruta nueva.
        $this->assertFalse(Gate::forUser($user)->allows('create', Career::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $career));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $career));

        $this->assertFalse(Gate::forUser($user)->allows('create', Schedule::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $schedule));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $schedule));

        // El administrador conserva todas las capacidades.
        $this->assertTrue(Gate::forUser($admin)->allows('create', Career::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $career));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $career));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Schedule::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $schedule));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $schedule));
    }

    /* ------------------------------------------------------------------ */
    /* Endpoints públicos y acceso anónimo                                 */
    /* ------------------------------------------------------------------ */

    public function test_the_public_endpoints_used_by_the_chatbot_keep_working(): void
    {
        $career = $this->career();
        $this->makeSchedule($career);

        // Catálogo público de carreras (lo consume el chatbot y React).
        $this->getJson('/api/careers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CON-AUT-001');

        // Catálogo público de horarios.
        $this->getJson('/api/schedules/options')
            ->assertStatus(200)
            ->assertJsonPath('data.careers.0.id', $career->id);

        // Horario general público por carrera, año y grupo.
        $this->getJson('/api/schedules/general?career_id=' . $career->id . '&year=2')
            ->assertStatus(200)
            ->assertJsonPath('data.personalized', false)
            ->assertJsonPath('data.student', null)
            ->assertJsonCount(1, 'data.classes');
    }

    public function test_anonymous_users_cannot_access_the_protected_endpoints(): void
    {
        $career = $this->career();
        [$user] = $this->makeStudent($career, 'TEST-AUT-001', 'Estudiante Común');
        $schedule = $this->makeSchedule($career);

        $reads = [
            '/api/careers/' . $career->id,
            '/api/schedules',
            '/api/schedules/' . $schedule->id,
            '/api/my-schedule',
            '/api/scholarships',
            '/api/tutorings',
        ];

        foreach ($reads as $path) {
            $this->getJson($path)->assertStatus(401);
        }

        $writes = [
            ['postJson', '/api/careers', []],
            ['putJson', '/api/careers/' . $career->id, []],
            ['deleteJson', '/api/careers/' . $career->id, []],
            ['postJson', '/api/schedules', []],
            ['putJson', '/api/schedules/' . $schedule->id, []],
            ['deleteJson', '/api/schedules/' . $schedule->id, []],
            ['postJson', '/api/scholarships', []],
            ['putJson', '/api/scholarships/1', []],
            ['deleteJson', '/api/scholarships/1', []],
            ['postJson', '/api/tutorings', []],
            ['putJson', '/api/tutorings/1', []],
            ['deleteJson', '/api/tutorings/1', []],
        ];

        foreach ($writes as [$method, $path, $payload]) {
            $this->{$method}($path, $payload)->assertStatus(401);
        }
    }
}
