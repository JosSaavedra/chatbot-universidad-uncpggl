<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'role', 'content', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    protected function content(): Attribute
    {
        // El modo demostración antiguo guardaba saludos con nombre sin dueño.
        return Attribute::get(fn (string $content, array $attributes): string =>
            $attributes['role'] === 'bot' && $attributes['user_id'] === null
                ? self::redactStudentGreeting($content)
                : $content);
    }

    public static function redactStudentGreeting(string $content): string
    {
        return (string) preg_replace(
            '/¡Hola,[^\n]*!\s*👋\s*\n\s*\nSoy el Asistente Académico/u',
            "¡Hola! 👋\n\nSoy el Asistente Académico",
            $content,
        );
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
