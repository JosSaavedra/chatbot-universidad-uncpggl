<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * El historial de una conversación personal contiene datos privados (horario,
 * pagos, notas). Estas pruebas garantizan que solo su dueño puede leerlo y que
 * una petición anónima nunca escribe dentro de la conversación de un usuario.
 */
class ChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(string $carnet): User
    {
        $career = Career::firstOrCreate(['code' => 'TEST-HISTORY'], [
            'name' => 'Carrera de prueba',
            'faculty' => 'Facultad de prueba',
            'duration_years' => 4,
            'enrollment_fee' => 100,
            'monthly_fee' => 100,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['is_admin' => false]);
        Student::create([
            'user_id' => $user->id,
            'career_id' => $career->id,
            'student_code' => $carnet,
            'year_of_study' => 1,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_anonymous_conversation_history_is_readable(): void
    {
        $conversation = Conversation::create([
            'session_id' => 'sess_anon_1',
            'user_id' => null,
            'title' => 'Hola',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hola',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'bot',
            'content' => 'Buenos días',
        ]);

        $this->getJson('/api/chat/history?session_id=sess_anon_1', $this->guestChatHeaders('sess_anon_1'))
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.content', 'Hola');
    }

    public function test_owner_can_read_their_own_personal_conversation(): void
    {
        $user = $this->makeStudent('TEST-HISTORY-OWNER');

        $conversation = Conversation::create([
            'session_id' => 'sess_owner',
            'user_id' => $user->id,
            'title' => 'Mis pagos',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'bot',
            'content' => 'Tu saldo es C$0',
        ]);

        $token = $user->createToken('password-login')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/chat/history?session_id=sess_owner')
            ->assertOk()
            ->assertJsonCount(1, 'messages');
    }

    public function test_another_user_cannot_read_a_personal_conversation(): void
    {
        $owner = $this->makeStudent('TEST-HISTORY-OWNER');
        $intruder = $this->makeStudent('TEST-HISTORY-OTHER');

        $conversation = Conversation::create([
            'session_id' => 'sess_private',
            'user_id' => $owner->id,
            'title' => 'Mis notas',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
            'role' => 'bot',
            'content' => 'Nota final: 95',
        ]);

        $token = $intruder->createToken('password-login')->plainTextToken;
        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('is_admin', false);

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/chat/history?session_id=sess_private')
            ->assertStatus(403);

        Auth::forgetGuards();
        $this->getJson('/api/conversations?user_id='.$owner->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/conversations/'.$conversation->id)->assertNotFound();
        $this->deleteJson('/api/conversations/'.$conversation->id)->assertNotFound();
        $this->postJson('/api/chat/auth', ['message' => 'Mis notas', 'session_id' => 'sess_private'])
            ->assertForbidden();
        $this->assertDatabaseHas('conversations', ['id' => $conversation->id, 'user_id' => $owner->id]);
    }

    public function test_guest_cannot_read_a_personal_conversation(): void
    {
        $owner = User::factory()->create();

        $conversation = Conversation::create([
            'session_id' => 'sess_private_2',
            'user_id' => $owner->id,
            'title' => 'Mis notas',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'bot',
            'content' => 'Nota final: 95',
        ]);

        $this->getJson('/api/chat/history?session_id=sess_private_2')
            ->assertStatus(403);
    }

    public function test_authenticated_message_cannot_claim_a_previous_anonymous_conversation(): void
    {
        Conversation::create([
            'session_id' => 'sess_shared',
            'user_id' => null,
            'title' => 'Hola',
        ]);

        $user = $this->makeStudent('TEST-HISTORY-CLAIM');
        $token = $user->createToken('password-login')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/chat/auth', [
                'message' => 'mis pagos',
                'session_id' => 'sess_shared',
            ], $this->guestChatHeaders('sess_shared'))
            ->assertForbidden();

        $this->assertDatabaseHas('conversations', [
            'session_id' => 'sess_shared',
            'user_id' => null,
        ]);

        $new = $this->postJson('/api/chat/auth', ['message' => 'Mis notas'])->assertOk()
            ->assertJsonMissingPath('guest_token');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $new->json('session_id'));
        $this->assertDatabaseHas('conversations', ['session_id' => $new->json('session_id'), 'user_id' => $user->id]);
    }

    public function test_anonymous_message_does_not_write_into_a_user_conversation(): void
    {
        $owner = User::factory()->create();

        $conversation = Conversation::create([
            'session_id' => 'sess_mixed',
            'user_id' => $owner->id,
            'title' => 'Privada',
        ]);

        $this->postJson('/api/chat', [
            'message' => 'hola',
            'session_id' => 'sess_mixed',
        ])->assertForbidden();

        // No se añadió ningún mensaje a la conversación del usuario.
        $this->assertSame(0, Message::where('conversation_id', $conversation->id)->count());

        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_guest_can_delete_its_history_and_credentials_expire(): void
    {
        $first = $this->postJson('/api/chat', ['message' => 'Mis notas'])->assertOk()->json();
        $second = $this->postJson('/api/chat', ['message' => 'Mis notas'])->assertOk()->json();
        $url = '/api/chat/history?session_id='.$first['session_id'];
        $headers = ['X-Chat-Guest-Token' => $first['guest_token']];
        $id = Conversation::where('session_id', $first['session_id'])->firstOrFail()->id;

        $this->deleteJson($url, [], ['X-Chat-Guest-Token' => $second['guest_token']])->assertForbidden();
        $this->deleteJson($url, [], $headers)->assertNoContent();
        $this->assertDatabaseMissing('messages', ['conversation_id' => $id]);
        $this->getJson($url, $headers)->assertForbidden();

        $this->travel(24)->hours();
        $expired = '/api/chat/history?session_id='.$second['session_id'];
        $expiredHeaders = ['X-Chat-Guest-Token' => $second['guest_token']];
        $this->getJson($expired, $expiredHeaders)->assertForbidden();
        $this->deleteJson($expired, [], $expiredHeaders)->assertForbidden();
        $this->assertDatabaseHas('conversations', ['session_id' => $second['session_id']]);
    }

    public function test_guest_chat_requires_the_proof_issued_for_that_conversation(): void
    {
        $newGuest = fn () => $this->postJson('/api/chat', ['message' => 'Mis notas'])->assertOk();
        $first = $newGuest();
        $second = $newGuest();
        $id = $first->json('session_id');
        $proof = $first->json('guest_token');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $id);
        $this->assertNotSame($id, $second->json('session_id'));
        $this->assertNotEmpty($proof);
        $url = '/api/chat/history?session_id='.$id;
        foreach ([[], ['X-Chat-Guest-Token' => $second->json('guest_token')], ['X-Chat-Guest-Token' => 'fake']] as $headers) {
            $this->getJson($url, $headers)->assertForbidden();
            $this->postJson('/api/chat', ['message' => 'Mis notas', 'session_id' => $id], $headers)->assertForbidden();
        }
        $headers = ['X-Chat-Guest-Token' => $proof];
        $this->postJson('/api/chat', ['message' => 'Mis notas', 'session_id' => $id], $headers)
            ->assertOk()->assertJsonPath('session_id', $id)->assertJsonMissingPath('guest_token');
        $this->getJson($url, $headers)->assertOk()->assertJsonCount(4, 'messages');
        $this->postJson('/api/chat', ['message' => 'Mis notas', 'session_id' => 'chosen-by-client'])
            ->assertForbidden();
        $this->assertDatabaseCount('conversations', 2);
    }
}
