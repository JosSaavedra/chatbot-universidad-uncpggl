<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Student;
use App\Services\ChatbotService;

$svc = app(ChatbotService::class);
$med = Student::where('student_code', '2024-MED-001')->first();

echo "=== MATCH DE CARRERAS (público) ===\n";
$msgs = [
    'medicina',
    'medicina veterinaria',
    'ingeniería',
    'ingeniería civil',
    'ingeniería en sistemas',
    'sistemas de tercer año',
    'física',
    'administración',
    'economía',
    'horario de medicina de segundo año',
    'horario de ingeniería',
];
foreach ($msgs as $m) {
    $s = $svc->chatSuggestions($m, null);
    $type = $s['type'] ?? 'null';
    $extra = '';
    if ($type === 'schedule') {
        $extra = ' career=' . ($s['schedule']['career'] ?? '-') . ' year=' . ($s['schedule']['year'] ?? '-');
    } elseif ($type === 'schedule_picker') {
        $extra = ' picker career_id=' . ($s['schedule_picker']['career_id'] ?? '-');
    } elseif ($type === 'careers') {
        $extra = ' n=' . count($s['careers'] ?? []) . ' names=' . implode(', ', array_column($s['careers'] ?? [], 'name'));
    }
    echo "  [{$type}]{$extra}  <= \"{$m}\"\n";
}

echo "\n=== PAGOS EN EL CHAT ===\n";
foreach (['mis pagos', 'cuánto debo', 'estado de cuenta', '¿cuánto cuesta la inscripción?'] as $m) {
    $s = $svc->chatSuggestions($m, $med);
    $type = $s['type'] ?? 'null';
    $pers = $s['personalized'] ?? null;
    $sum = $s['payments']['summary'] ?? null;
    echo "  [{$type}] personalized=" . var_export($pers, true)
        . ($sum ? " pendiente={$sum['total_pending']} pagado={$sum['total_paid']} items=" . count($s['payments']['items']) : '')
        . "  <= \"{$m}\"\n";
}
echo "\n--- mismo mensaje SIN sesión ---\n";
foreach (['mis pagos', 'cuánto debo'] as $m) {
    $s = $svc->chatSuggestions($m, null);
    echo "  [{$s['type']}] personalized=" . var_export($s['personalized'] ?? null, true) . "  <= \"{$m}\"\n";
}
