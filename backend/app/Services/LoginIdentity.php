<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

final readonly class LoginIdentity
{
    private function __construct(
        public string $identifier,
        public ?User $user,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $identity = $request->attributes->get(self::class);
        if ($identity instanceof self) {
            return $identity;
        }

        $carnet = self::normalize($request->input('carnet'), 40);
        $email = self::normalize($request->input('email'), 255);
        $user = match (true) {
            $carnet !== '' => Student::whereRaw('LOWER(student_code) = ?', [$carnet])->first()?->user,
            $email !== '' => User::whereRaw('LOWER(email) = ?', [$email])->first(),
            default => null,
        };

        $identity = new self($carnet !== '' ? $carnet : $email, $user);
        $request->attributes->set(self::class, $identity);

        return $identity;
    }

    private static function normalize(mixed $value, int $maxLength): string
    {
        $value = is_string($value) ? trim($value) : '';

        return strlen($value) <= $maxLength ? mb_strtolower($value) : '';
    }
}
