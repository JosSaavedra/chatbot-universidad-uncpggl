<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * PROBLEMA 2 — Respuestas profesionales de la IA.
 *
 * La salida del modelo se sanea (sin HTML/JSX visible) y el systemPrompt
 * prohíbe generar etiquetas de interfaz, obliga a no ofrecer el módulo de pagos
 * para costos públicos de carreras y evita repetir advertencias largas.
 */
class ChatbotResponseFormatTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ChatbotService
    {
        return app(ChatbotService::class);
    }

    public function test_model_html_tags_are_stripped_from_the_reply(): void
    {
        config(['services.groq.key' => 'test-key']);

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => "Contabilidad Pública y Finanzas ofrece:\n<button>Ver información de pagos</button>\nFin.",
                    ],
                ]],
            ], 200),
        ]);

        $reply = $this->service()->processQuery('¿Cuánto cuesta Contabilidad?');

        $this->assertSame('groq', $reply['intent']);
        $this->assertStringNotContainsString('<button>', $reply['text']);
        $this->assertStringNotContainsString('</button>', $reply['text']);
        $this->assertStringNotContainsString('<', $reply['text']);
        // El texto interno se conserva (no se elimina contenido indiscriminadamente).
        $this->assertStringContainsString('Ver información de pagos', $reply['text']);
        $this->assertStringContainsString('Contabilidad Pública y Finanzas', $reply['text']);
    }

    public function test_markdown_is_preserved_by_the_sanitizer(): void
    {
        $method = new ReflectionMethod(ChatbotService::class, 'sanitizeModelText');
        $method->setAccessible(true);

        $markdown = "Costos:\n\n- Inscripción: C\$600.00\n- Mensualidad: C\$340.00\n- Duración: 4 años";

        $clean = $method->invoke($this->service(), $markdown);

        $this->assertSame($markdown, $clean);
    }

    public function test_block_tags_become_line_breaks(): void
    {
        $method = new ReflectionMethod(ChatbotService::class, 'sanitizeModelText');
        $method->setAccessible(true);

        $clean = $method->invoke($this->service(), '<p>Uno</p><p>Dos</p>');

        $this->assertSame("Uno\nDos", $clean);
    }

    private function systemPrompt(): string
    {
        $method = new ReflectionMethod(ChatbotService::class, 'systemPrompt');
        $method->setAccessible(true);

        return $method->invoke($this->service(), 'CONTEXTO DE PRUEBA');
    }

    public function test_system_prompt_forbids_html_and_interface_tags(): void
    {
        $prompt = $this->systemPrompt();

        $this->assertStringContainsString('PROHIBIDO generar HTML', $prompt);
        $this->assertStringContainsString('<button>', $prompt); // aparece como ejemplo prohibido
    }

    public function test_system_prompt_does_not_offer_the_payments_button_or_repeat_warnings(): void
    {
        $prompt = $this->systemPrompt();

        // Ya no instruye ofrecer el botón de pagos para costos de carreras.
        $this->assertStringNotContainsString('ofrécele el botón', $prompt);
        // Evita repetir advertencias largas (lo comunica el DemoBadge del frontend).
        $this->assertStringContainsString('NO repitas advertencias largas', $prompt);
        // Los costos de una carrera son públicos y no deben confundirse con pagos.
        $this->assertStringContainsString('los costos de las carreras son información pública', $prompt);
    }

    public function test_specific_career_price_fallback_shows_costs_not_payments(): void
    {
        $career = Career::create([
            'name' => 'Contabilidad Pública y Finanzas', 'code' => 'CON-001',
            'faculty' => 'Ciencias Económicas y Administrativas', 'duration_years' => 4,
            'enrollment_fee' => 600, 'monthly_fee' => 340, 'is_active' => true,
        ]);
        $this->assertNotNull($career->id);

        // Sin clave de Groq se usa el respaldo local: una carrera concreta debe
        // mostrar sus costos, no recomendar el módulo de pagos.
        config(['services.groq.key' => '']);

        $reply = $this->service()->processQuery('¿Cuánto cuesta Contabilidad Pública y Finanzas?');

        $this->assertStringContainsString('Inscripción: C$600.00', $reply['text']);
        $this->assertStringContainsString('Mensualidad: C$340.00', $reply['text']);
        $this->assertStringNotContainsString('Pagos del estudiante', $reply['text']);
    }

    public function test_personal_grades_question_requests_secure_authentication(): void
    {
        // Sin Groq (o si falla) la pregunta por datos personales pide inicio de sesión.
        config(['services.groq.key' => '']);

        $reply = $this->service()->processQuery('¿Cuáles son mis notas?');

        $this->assertSame('fallback_auth_required', $reply['intent']);
        $this->assertStringContainsString('iniciar sesión', $reply['text']);
    }
}
