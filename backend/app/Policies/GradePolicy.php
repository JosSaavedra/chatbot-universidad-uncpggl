<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

/**
 * Autorización de calificaciones.
 *
 * Impide que un estudiante normal lea, cree, modifique o elimine las
 * calificaciones de OTRO usuario. Las operaciones de administración
 * (crear/editar/eliminar) quedan reservadas a usuarios administradores.
 */
class GradePolicy
{
    /**
     * Cualquier usuario autenticado puede listar; el controlador filtra para que
     * un estudiante solo reciba sus propias calificaciones.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Solo el propio dueño o un administrador pueden ver una calificación.
     */
    public function view(User $user, Grade $grade): bool
    {
        return (bool) $user->is_admin || (int) $grade->user_id === (int) $user->id;
    }

    /**
     * Crear calificaciones: solo administradores.
     */
    public function create(User $user): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Actualizar calificaciones: solo administradores.
     */
    public function update(User $user, Grade $grade): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Eliminar calificaciones: solo administradores.
     */
    public function delete(User $user, Grade $grade): bool
    {
        return (bool) $user->is_admin;
    }
}
