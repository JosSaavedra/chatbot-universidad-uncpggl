<?php

namespace App\Policies;

use App\Models\Tutoring;
use App\Models\User;

/**
 * Autorización de tutorías.
 *
 * Una tutoría es un registro personal del estudiante (docente, fecha, lugar y
 * estado de pago): sólo su dueño o un administrador pueden consultarla.
 * Programar, modificar y cancelar tutorías es una operación administrativa.
 */
class TutoringPolicy
{
    /**
     * Listar tutorías: cualquier usuario autenticado. El controlador se
     * encarga de acotar el resultado a las tutorías del propio estudiante.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver una tutoría: sólo su dueño o un administrador.
     */
    public function view(User $user, Tutoring $tutoring): bool
    {
        return (bool) $user->is_admin || (int) $tutoring->user_id === (int) $user->id;
    }

    /**
     * Crear tutorías: sólo administradores (asignan el estudiante).
     */
    public function create(User $user): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Modificar tutorías: sólo administradores. Un estudiante nunca puede
     * reprogramar ni cancelar una tutoría.
     */
    public function update(User $user, Tutoring $tutoring): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Eliminar tutorías: sólo administradores.
     */
    public function delete(User $user, Tutoring $tutoring): bool
    {
        return (bool) $user->is_admin;
    }
}
