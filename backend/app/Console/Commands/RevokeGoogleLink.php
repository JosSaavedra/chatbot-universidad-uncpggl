<?php

namespace App\Console\Commands;

use App\Models\GooglePreauthorization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokeGoogleLink extends Command
{
    protected $signature = 'qr-google:revoke {carnet}';
    protected $description = 'Revoca la vinculación Google de un carné y cierra sus sesiones';

    public function handle(): int
    {
        $code = strtolower(trim($this->argument('carnet')));
        $revoked = DB::transaction(function () use ($code) {
            $links = GooglePreauthorization::whereNull('revoked_at')->get()
                ->filter(fn ($link) => $link->allows($code));
            $affected = [$code];
            foreach ($links as $link) {
                $affected = array_merge($affected, array_map('strtolower', $link->carnets));
                $link->update(['revoked_at' => now()]);
            }
            if ($links->isNotEmpty()) {
                GooglePreauthorization::invalidateAccess(array_unique($affected));
            }

            return $links->isNotEmpty();
        }, 3);

        if (!$revoked) {
            $this->error('No hay una preautorización activa para ese carné.');
            return self::FAILURE;
        }

        $this->info('Vinculación revocada y sesiones cerradas para los carnés autorizados.');
        return 0;
    }
}
