<?php

namespace App\Policies;

use App\Models\Career;
use App\Models\User;

/**
 * Autorización de carreras.
 *
 * El catálogo de carreras es público (lo consume el chatbot y la vista
 * "Ver carreras" sin necesidad de sesión), pero cualquier escritura —incluidos
 * los aranceles y los demás datos administrativos— queda reservada a los
 * administradores.
 */
class CareerPolicy
{
    /**
     * El listado de carreras es público: no requiere sesión.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * La ficha de una carrera es visible para cualquier usuario autenticado.
     */
    public function view(User $user, Career $career): bool
    {
        return true;
    }

    /**
     * Crear carreras: sólo administradores.
     */
    public function create(User $user): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Modificar una carrera (aranceles, estado, datos administrativos):
     * sólo administradores.
     */
    public function update(User $user, Career $career): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Eliminar una carrera: sólo administradores.
     */
    public function delete(User $user, Career $career): bool
    {
        return (bool) $user->is_admin;
    }
}
