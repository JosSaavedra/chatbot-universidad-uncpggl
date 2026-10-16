<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Career;
use App\Models\Student;
use App\Models\Payment;

echo "CARRERAS:\n";
foreach (Career::where('is_active', true)->orderBy('name')->get() as $c) {
    echo "  {$c->id} | {$c->name} | {$c->code}\n";
}

echo "\nSTUDENTS (primeros 5):\n";
foreach (Student::with('user')->orderBy('id')->limit(5)->get() as $s) {
    echo "  {$s->student_code} | user_id={$s->user_id} | {$s->user?->email}\n";
}

echo "\nPAGOS por estudiante (top 5 por cantidad):\n";
foreach (Payment::selectRaw('student_id, COUNT(*) c')->groupBy('student_id')->orderByDesc('c')->limit(5)->get() as $row) {
    echo "  student_id={$row->student_id} -> {$row->c} pagos\n";
}
echo "Total pagos: " . Payment::count() . "\n";
