<?php

namespace App\Providers;

use App\Models\Grade;
use App\Policies\GradePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Impide que un estudiante lea/modifique calificaciones ajenas y reserva
        // la administración de notas a usuarios autorizados.
        Gate::policy(Grade::class, GradePolicy::class);
    }
}
