<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El historial de una conversación personal contiene datos privados (horario,
 * pagos, notas). Estas pruebas garantizan que solo su dueño puede leerlo y que
 * una petición anónima nunca escribe dentro de la conversación de un usuario.
 */
class ChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_conversation_history_is_readable(): void
    {
        $conversation = Conversation::create([
            'session_id' => 'sess_anon_1',
            'user_id'    => null,
            'title'      => 'Hola',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => 'Hola',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'bot',
            'content'         => 'Buenos días',
        ]);

        $this->getJson('/api/chat/history?session_id=sess_anon_1')
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.content', 'Hola');
    }

    public function test_owner_can_read_their_own_personal_conversation(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::create([
            'session_id' => 'sess_owner',
            'user_id'    => $user->id,
            'title'      => 'Mis pagos',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'role'            => 'bot',
            'content'         => 'Tu saldo es C$0',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/chat/history?session_id=sess_owner')
            ->assertOk()
            ->assertJsonCount(1, 'messages');
    }

    public function test_another_user_cannot_read_a_personal_conversation(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();

        $conversation = Conversation::create([
            'session_id' => 'sess_private',
            'user_id'    => $owner->id,
            'title'      => 'Mis notas',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $owner->id,
            'role'            => 'bot',
            'content'         => 'Nota final: 95',
        ]);

        $token = $intruder->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/chat/history?session_id=sess_private')
            ->assertStatus(403);
    }

    public function test_guest_cannot_read_a_personal_conversation(): void
    {
        $owner = User::factory()->create();

        $conversation = Conversation::create([
            'session_id' => 'sess_private_2',
            'user_id'    => $owner->id,
            'title'      => 'Mis notas',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'bot',
            'content'         => 'Nota final: 95',
        ]);

        $this->getJson('/api/chat/history?session_id=sess_private_2')
            ->assertStatus(403);
    }

    public function test_authenticated_message_claims_a_previous_anonymous_conversation(): void
    {
        Conversation::create([
            'session_id' => 'sess_shared',
            'user_id'    => null,
            'title'      => 'Hola',
        ]);

        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/chat/auth', [
                'message'    => 'mis pagos',
                'session_id' => 'sess_shared',
            ])
            ->assertOk();

        $this->assertDatabaseHas('conversations', [
            'session_id' => 'sess_shared',
            'user_id'    => $user->id,
        ]);
    }

    public function test_anonymous_message_does_not_write_into_a_user_conversation(): void
    {
        $owner = User::factory()->create();

        $conversation = Conversation::create([
            'session_id' => 'sess_mixed',
            'user_id'    => $owner->id,
            'title'      => 'Privada',
        ]);

        $this->postJson('/api/chat', [
            'message'    => 'hola',
            'session_id' => 'sess_mixed',
        ])->assertOk();

        // No se añadió ningún mensaje a la conversación del usuario.
        $this->assertSame(0, Message::where('conversation_id', $conversation->id)->count());

        $this->assertDatabaseHas('conversations', [
            'session_id' => 'sess_mixed_guest',
            'user_id'    => null,
        ]);
    }
}
