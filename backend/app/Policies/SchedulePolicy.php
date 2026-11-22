<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

/**
 * Autorización de horarios.
 *
 * Los horarios son datos de clase (no datos personales del estudiante): su
 * consulta está disponible para cualquier usuario autenticado y los horarios
 * públicos se sirven por endpoints separados sin sesión. Crear, modificar y
 * eliminar bloques de horario es una tarea administrativa.
 */
class SchedulePolicy
{
    /**
     * Listar horarios: cualquier usuario autenticado.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver un bloque de horario: cualquier usuario autenticado.
     */
    public function view(User $user, Schedule $schedule): bool
    {
        return true;
    }

    /**
     * Crear horarios: sólo administradores.
     */
    public function create(User $user): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Modificar horarios: sólo administradores.
     */
    public function update(User $user, Schedule $schedule): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Eliminar horarios: sólo administradores.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        return (bool) $user->is_admin;
    }
}
