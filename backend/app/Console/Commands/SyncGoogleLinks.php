<?php

namespace App\Console\Commands;

use App\Models\GooglePreauthorization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncGoogleLinks extends Command
{
    protected $signature = 'qr-google:sync-links {--reactivate= : Reactiva el vínculo configurado para este carné}';
    protected $description = 'Sincroniza las preautorizaciones privadas de Google con la base de datos';

    public function handle(): int
    {
        $accounts = config('qr_auth.accounts');
        $reactivate = $this->option('reactivate');

        if (!config('qr_auth.accounts_present')) {
            $this->warn('Sin configuración de vínculos Google. Se conservaron las autorizaciones existentes.');
            return $reactivate === null ? self::SUCCESS : self::FAILURE;
        }

        if (!is_array($accounts) || !config('qr_auth.accounts_valid')) {
            $this->error('Configuración de preautorizaciones inválida.');
            return self::FAILURE;
        }

        $desired = [];
        $owners = [];
        foreach ($accounts as $account) {
            $hash = hash('sha256', strtolower($account['email']));
            $carnets = array_values(array_unique(array_map('strtolower', $account['carnets'])));
            if (isset($desired[$hash]) || array_intersect($carnets, array_keys($owners))) {
                $this->error('Hay titulares o carnés duplicados en las preautorizaciones.');
                return self::FAILURE;
            }
            $desired[$hash] = ['owner_label' => $account['owner'] ?: null, 'carnets' => $carnets];
            foreach ($carnets as $carnet) {
                $owners[$carnet] = $hash;
            }
        }

        if ($reactivate !== null) {
            $hash = $owners[strtolower(trim($reactivate))] ?? null;
            if ($hash === null || !$this->reactivateLink($hash, $desired[$hash])) {
                $this->error('No se pudo reactivar el vínculo. Comprueba que existe, está revocado y no hay titulares activos en conflicto.');
                return self::FAILURE;
            }

            $this->info('Vínculo reactivado. El titular debe completar un nuevo acceso QR.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($desired) {
            $invalidate = [];
            foreach (GooglePreauthorization::all() as $link) {
                $replacement = $desired[$link->email_hash]['carnets'] ?? [];
                if (!$link->revoked_at) {
                    $removed = array_diff(array_map('strtolower', $link->carnets), $replacement);
                    $invalidate = array_merge($invalidate, $removed);
                    if ($replacement === []) {
                        $link->update(['revoked_at' => now()]);
                    }
                }
            }
            foreach ($desired as $hash => $attributes) {
                GooglePreauthorization::updateOrCreate(['email_hash' => $hash], $attributes);
            }
            if ($invalidate !== []) {
                GooglePreauthorization::invalidateAccess(array_unique($invalidate));
            }
        }, 3);

        $carnets = array_sum(array_map(static fn (array $account): int => count($account['carnets']), $accounts));
        $this->info(count($accounts).' preautorizaciones sincronizadas ('.$carnets.' carnés). No se mostraron correos.');
        return self::SUCCESS;
    }

    private function reactivateLink(string $hash, array $attributes): bool
    {
        return DB::transaction(function () use ($hash, $attributes) {
            $link = GooglePreauthorization::where('email_hash', $hash)->whereNotNull('revoked_at')->first();
            if (!$link) {
                return false;
            }

            $conflict = GooglePreauthorization::whereNull('revoked_at')->get()->contains(
                fn (GooglePreauthorization $active) => array_intersect(
                    $attributes['carnets'], array_map('strtolower', $active->carnets)
                ) !== []
            );
            if ($conflict) {
                return false;
            }

            GooglePreauthorization::invalidateAccess($attributes['carnets']);
            $link->update($attributes + ['revoked_at' => null]);

            return true;
        }, 3);
    }
}
