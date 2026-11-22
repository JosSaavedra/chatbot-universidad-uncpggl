<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DeploymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_rejects_unbounded_queries_and_fetches_at_most_two_years(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $this->getJson('/api/calendar?start=2025-01-01&end=2028-01-01')->assertUnprocessable();
        $this->getJson('/api/calendar/holidays?start=2025-01-01&end=2028-01-01')->assertUnprocessable();
        $this->getJson('/api/calendar/holidays?year=2500')->assertUnprocessable();
        \Illuminate\Support\Facades\Http::assertNothingSent();

        $this->getJson('/api/calendar?start=2025-12-30&end=2026-01-02')->assertOk();
        \Illuminate\Support\Facades\Http::assertSentCount(2);
        $this->getJson('/api/calendar/holidays?year=2026')->assertOk();
        \Illuminate\Support\Facades\Http::assertSentCount(2);
    }

    public function test_calendar_defers_uncached_fetches_while_another_worker_is_fetching(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $lock = Cache::lock('holidays:upstream', 5);
        $this->assertTrue($lock->get());
        try {
            $this->getJson('/api/calendar/holidays?year=2026')->assertOk()->assertJsonCount(0, 'holidays');
            \Illuminate\Support\Facades\Http::assertNothingSent();
        } finally {
            $lock->release();
        }
        $this->getJson('/api/calendar/holidays?year=2026')->assertOk();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_inactive_events_and_campuses_have_no_public_detail(): void
    {
        $event = \App\Models\UniversityEvent::create([
            'title' => 'Evento retirado', 'start_date' => '2026-01-01',
            'category' => 'academic', 'is_active' => false,
        ]);
        $campus = \App\Models\Sede::create([
            'name' => 'Sede retirada', 'code' => 'RET', 'address' => 'Dirección ficticia', 'city' => 'León', 'is_active' => false,
        ]);
        $this->getJson('/api/university-events/'.$event->id)->assertNotFound();
        $this->getJson('/api/sedes/'.$campus->id)->assertNotFound();
        $event->update(['is_active' => true]);
        $campus->update(['is_active' => true]);
        $this->getJson('/api/university-events/'.$event->id)->assertOk();
        $this->getJson('/api/sedes/'.$campus->id)->assertOk();
    }

    public function test_cors_allows_the_frontend_and_explicit_chat_headers_only(): void
    {
        $origin = 'https://chatbot-universidad.vercel.app';
        config(['cors.allowed_origins_patterns' => ['#^'.preg_quote($origin, '#').'$#D']]);
        $preflight = [
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization, content-type, x-chat-guest-token, x-approval-nonce',
        ];

        $allowed = $this->call('OPTIONS', '/api/chat', [], [], [], $this->transformHeadersToServerVars(
            $preflight + ['Origin' => $origin],
        ));
        $allowed->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        $allowedHeaders = strtolower($allowed->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('x-chat-guest-token', $allowedHeaders);
        $this->assertStringContainsString('x-approval-nonce', $allowedHeaders);

        $this->call('OPTIONS', '/api/chat', [], [], [], $this->transformHeadersToServerVars(
            $preflight + ['Origin' => 'https://otro.vercel.app'],
        ))->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_sensitive_api_and_qr_responses_are_not_cached(): void
    {
        $this->app->instance('env', 'production');

        foreach (['/api/my-grades', '/auth/google/callback'] as $path) {
            $response = $this->getJson('https://localhost'.$path);
            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_guest_chat_limits_requests_from_one_address(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/chat', ['message' => 'Mis notas'])->assertOk();
        }
        $this->postJson('/api/chat', ['message' => 'Mis notas'])->assertTooManyRequests();
        $this->assertDatabaseCount('conversations', 10);
    }

    public function test_saturated_chat_leaves_other_endpoints_available_and_recovers(): void
    {
        $first = Cache::lock('chat:slot:1', 30);
        $second = Cache::lock('chat:slot:2', 30);
        $this->assertTrue($first->get());
        $this->assertTrue($second->get());

        try {
            $this->postJson('/api/chat', ['message' => 'Mis notas'])
                ->assertServiceUnavailable()
                ->assertHeader('Retry-After', '5');
            $this->getJson('/up')->assertOk();
            $this->assertDatabaseCount('conversations', 0);
        } finally {
            $first->release();
            $second->release();
        }

        $this->postJson('/api/chat', ['message' => 'Mis notas'])->assertOk();
        $slot = Cache::lock('chat:slot:1', 30);
        $this->assertTrue($slot->get());
        $slot->release();
    }
}
