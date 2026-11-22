<?php

namespace App\Policies;

use App\Models\Scholarship;
use App\Models\User;

/**
 * Autorización de becas.
 *
 * Una beca es un registro personal del estudiante: sólo su dueño o un
 * administrador pueden consultarla. Crear, modificar y eliminar becas es una
 * operación administrativa; además, la identidad del beneficiario nunca se
 * toma de un `user_id` enviado por el cliente fuera del alta administrativa.
 */
class ScholarshipPolicy
{
    /**
     * Listar becas: cualquier usuario autenticado. El controlador se encarga
     * de acotar el resultado a las becas del propio estudiante.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver una beca: sólo su dueño o un administrador.
     */
    public function view(User $user, Scholarship $scholarship): bool
    {
        return (bool) $user->is_admin || (int) $scholarship->user_id === (int) $user->id;
    }

    /**
     * Crear becas: sólo administradores (asignan el beneficiario).
     */
    public function create(User $user): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Modificar becas: sólo administradores. Un estudiante nunca puede
     * cambiar el estado ni la cobertura de su beca.
     */
    public function update(User $user, Scholarship $scholarship): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Eliminar becas: sólo administradores.
     */
    public function delete(User $user, Scholarship $scholarship): bool
    {
        return (bool) $user->is_admin;
    }
}
