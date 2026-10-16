<?php

namespace Database\Seeders;

use App\Models\Career;
use App\Models\Sede;
use Illuminate\Database\Seeder;

/**
 * Sedes (Centros Universitarios Regionales – CUR) y las carreras que ofrece
 * cada una.
 *
 * Reglas:
 *  - La única sede que ofrece TODAS las carreras es León (sede principal).
 *  - Las demás sedes ofrecen una buena cantidad de carreras; algunas se
 *    repiten entre sedes y otras son exclusivas.
 *
 * El seeder es idempotente: usa updateOrCreate por nombre y sincroniza las
 * carreras, así que puede ejecutarse varias veces sin duplicar ni borrar datos.
 */
class SedeSeeder extends Seeder
{
    public function run(): void
    {
        // Códigos de TODAS las carreras (la sede principal las imparte todas).
        $allCodes = Career::pluck('code')->all();

        $sedes = [
            [
                'name'     => 'CUR León',
                'code'     => 'CUR-LEON',
                'address'  => 'Posada del Sol, Planta 2, Yazaki, 300 metros al este',
                'city'     => 'León',
                'is_main'  => true,
                'careers'  => $allCodes,
            ],
            [
                'name'     => 'CUR Chinandega',
                'code'     => 'CUR-CHIN',
                'address'  => 'Iglesia San Antonio, media cuadra al sur',
                'city'     => 'Chinandega',
                'is_main'  => false,
                'careers'  => ['ENF-001', 'NUT-001', 'AGR-001', 'VET-001', 'IIN-001', 'ISI-001', 'ADM-001', 'CON-001', 'ICI-001'],
            ],
            [
                'name'     => 'CUR Estelí',
                'code'     => 'CUR-EST',
                'address'  => 'Semáforos de la antigua ENABAS, media cuadra al oeste',
                'city'     => 'Estelí',
                'is_main'  => false,
                'careers'  => ['ENF-001', 'NUT-001', 'AGR-001', 'FOR-001', 'ADM-001', 'CON-001', 'ECO-001', 'IIN-001', 'ISI-001'],
            ],
            [
                'name'     => 'CUR Masaya',
                'code'     => 'CUR-MAS',
                'address'  => 'Antigua Alcaldía Municipal, 25 varas al norte',
                'city'     => 'Masaya',
                'is_main'  => false,
                'careers'  => ['ENF-001', 'ADM-001', 'CON-001', 'ECO-001', 'ISI-001', 'IIN-001', 'ARQ-001', 'NUT-001'],
            ],
            [
                'name'     => 'CUR Granada',
                'code'     => 'CUR-GRA',
                'address'  => 'Contiguo a iglesia San Juan Bosco, frente al Palí de La Villa',
                'city'     => 'Granada',
                'is_main'  => false,
                'careers'  => ['ENF-001', 'ADM-001', 'CON-001', 'ECO-001', 'ISI-001', 'ARQ-001', 'NUT-001', 'ICI-001'],
            ],
            [
                'name'     => 'CUR Matagalpa',
                'code'     => 'CUR-MAT',
                'address'  => 'Frente al costado oeste de la Policía',
                'city'     => 'Matagalpa',
                'is_main'  => false,
                'careers'  => ['AGR-001', 'VET-001', 'FOR-001', 'ENF-001', 'ADM-001', 'CON-001', 'ECO-001', 'IIN-001'],
            ],
            [
                'name'     => 'CUR Ocotal',
                'code'     => 'CUR-OCO',
                'address'  => 'Barrio Monseñor Madrigal, cerca del Instituto Leonardo Matute',
                'city'     => 'Ocotal',
                'is_main'  => false,
                'careers'  => ['ENF-001', 'AGR-001', 'FOR-001', 'VET-001', 'ADM-001', 'CON-001', 'ISI-001'],
            ],
            [
                'name'     => 'CUR Juigalpa',
                'code'     => 'CUR-JUI',
                'address'  => 'Contiguo a parroquia San Martín',
                'city'     => 'Juigalpa',
                'is_main'  => false,
                'careers'  => ['VET-001', 'AGR-001', 'FOR-001', 'ENF-001', 'ADM-001', 'CON-001', 'ECO-001', 'ISI-001'],
            ],
        ];

        foreach ($sedes as $data) {
            $careerCodes = $data['careers'];
            unset($data['careers']);

            $sede = Sede::updateOrCreate(
                ['name' => $data['name']],
                $data + ['is_active' => true]
            );

            $careerIds = Career::whereIn('code', $careerCodes)->pluck('id')->all();
            $sede->careers()->sync($careerIds);
        }
    }
}
