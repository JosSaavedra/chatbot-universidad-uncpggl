<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Revoca los tokens emitidos por los métodos de autenticación anteriores
 * (acceso por carné sin contraseña, tokens de chatbot, passkeys y aprobación
 * por correo).
 *
 * La política de nombres de token vigente ya los rechaza, pero esta orden
 * los elimina físicamente de la base de datos para que no quede ninguna
 * credencial utilizable de métodos retirados.
 */
class RevokeLegacyTokens extends Command
{
    protected $signature = 'auth:revoke-legacy-tokens {--dry-run : Sólo cuenta los tokens}';

    protected $description = 'Elimina los tokens de los métodos de autenticación ya retirados';

    /**
     * Nombres de token emitidos por métodos que ya no están autorizados.
     */
    private const LEGACY_NAMES = ['demo-session', 'chatbot-token', 'passkey', 'email-approval'];

    public function handle(): int
    {
        $query = DB::table('personal_access_tokens')->whereIn('name', self::LEGACY_NAMES);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info('Tokens heredados que se revocarían: '.$count.'.');

            return self::SUCCESS;
        }

        $query->delete();

        $this->info('Tokens heredados revocados: '.$count.'.');

        return self::SUCCESS;
    }
}
