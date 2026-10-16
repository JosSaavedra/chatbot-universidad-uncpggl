<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('chatbot-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('chatbot-token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * Inicio de sesión mediante el código del carné.
     *
     * Pensado para el prototipo de la tesis: el estudiante se identifica con su
     * carné y se emite un token Sanctum asociado a su cuenta. La consulta de
     * horarios personales exige esta sesión y sólo devuelve la información del
     * propio estudiante.
     */
    public function loginCarnet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'carnet' => 'required|string|max:40',
        ]);

        $carnet = trim($data['carnet']);

        $student = Student::whereRaw('LOWER(student_code) = ?', [mb_strtolower($carnet)])->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'carnet' => ['No encontramos un estudiante con ese carné.'],
            ]);
        }

        $user = User::find($student->user_id);

        if (! $user) {
            throw ValidationException::withMessages([
                'carnet' => ['La cuenta asociada a ese carné no está disponible.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('chatbot-token')->plainTextToken;

        return response()->json([
            'user'    => $user,
            'student' => [
                'carnet' => $student->student_code,
                'year'   => $student->year_of_study,
                'group'  => $student->group_code,
            ],
            'token'   => $token,
        ]);
    }
}
