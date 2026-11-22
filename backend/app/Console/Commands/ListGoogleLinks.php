<?php

namespace App\Console\Commands;

use App\Models\GooglePreauthorization;
use Illuminate\Console\Command;

class ListGoogleLinks extends Command
{
    protected $signature = 'qr-google:links';
    protected $description = 'Muestra el estado de las vinculaciones sin revelar correos';

    public function handle(): int
    {
        $rows = GooglePreauthorization::all()->map(fn ($link) => [
            'carnes' => implode(', ', $link->carnets),
            'estado' => $link->revoked_at ? 'revocada' : ($link->google_sub ? 'vinculada' : 'pendiente'),
        ])->all();

        $this->table(['Carnés', 'Estado'], $rows);
        return 0;
    }
}
