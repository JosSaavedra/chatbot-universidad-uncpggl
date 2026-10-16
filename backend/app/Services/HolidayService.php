<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Obtiene y traduce al español los feriados nacionales de Nicaragua.
 *
 * La fuente (Nager.Date) solo entrega los nombres en inglés para Nicaragua,
 * por eso aquí se traducen a español. Si un feriado no está en el diccionario
 * se conserva el nombre original en inglés (nunca se inventan fechas).
 */
class HolidayService
{
    public const TIMEZONE = 'America/Managua';

    /**
     * Diccionario inglés -> español de los feriados.
     */
    private const TRANSLATIONS = [
        "New Year's Day"              => 'Año Nuevo',
        "New Year's Eve"              => 'Fin de Año',
        "New Year"                    => 'Año Nuevo',
        'Epiphany'                    => 'Día de Reyes',
        'Air Force Day'               => 'Día de la Fuerza Aérea',
        'Maundy Thursday'             => 'Jueves Santo',
        'Holy Thursday'               => 'Jueves Santo',
        'Good Friday'                 => 'Viernes Santo',
        'Holy Saturday'               => 'Sábado Santo',
        'Easter Sunday'               => 'Domingo de Resurrección',
        'Easter Monday'               => 'Lunes de Pascua',
        'Labour Day'                  => 'Día del Trabajo',
        'Labor Day'                   => 'Día del Trabajo',
        'Army Day'                    => 'Día del Ejército',
        'Liberation Day'              => 'Día de la Liberación',
        'Battle of San Jacinto'       => 'Batalla de San Jacinto',
        'Independence Day'            => 'Día de la Independencia',
        'Indigenous Resistance Day'   => 'Día de la Resistencia Indígena, Negra y Popular',
        "All Saints' Day"             => 'Día de Todos los Santos',
        'All Souls Day'               => 'Día de los Difuntos',
        "All Souls' Day"              => 'Día de los Difuntos',
        'Immaculate Conception'       => 'Día de la Purísima Concepción',
        'Christmas Day'               => 'Navidad',
        'Christmas Eve'               => 'Nochebuena',
        'Boxing Day'                  => 'Día de San Esteban',
        'Whit Monday'                 => 'Lunes de Pentecostés',
        'Ascension Day'               => 'Día de la Ascensión',
        'Assumption Day'              => 'Día de la Asunción',
        'Corpus Christi'              => 'Corpus Christi',
        'Carnival'                    => 'Carnaval',
        'Carnival Monday'             => 'Lunes de Carnaval',
        'Carnival Tuesday'            => 'Martes de Carnaval',
        'Mother\'s Day'               => 'Día de la Madre',
        'Father\'s Day'               => 'Día del Padre',
        'Children\'s Day'             => 'Día del Niño',
        'Teachers\' Day'              => 'Día del Maestro',
        'Workers\' Day'               => 'Día del Trabajador',
    ];

    /**
     * Devuelve los feriados de Nicaragua dentro del rango [from, to].
     *
     * @return array<int, array{date:string, name:string, original_name:string}>
     */
    public function getHolidays(Carbon $from, Carbon $to): array
    {
        $result = [];

        foreach (range($from->year, $to->year) as $year) {
            $cached = Cache::remember("holidays_ni_{$year}", now()->addDay(), function () use ($year) {
                return $this->fetchYear($year);
            });

            $result = array_merge($result, is_array($cached) ? $cached : []);
        }

        return collect($result)
            ->filter(fn ($h) => isset($h['date']) && $h['date'] >= $from->toDateString() && $h['date'] <= $to->toDateString())
            ->map(function ($h) {
                $original = $h['name'] ?? ($h['localName'] ?? 'Feriado');

                return [
                    'date'          => $h['date'],
                    'name'          => $this->translate($original),
                    'original_name' => $original,
                ];
            })
            ->sortBy('date')
            ->values()
            ->all();
    }

    /**
     * Traduce un nombre de feriado al español. Si no existe traducción
     * devuelve el nombre original.
     */
    public function translate(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'Feriado';
        }

        if (isset(self::TRANSLATIONS[$name])) {
            return self::TRANSLATIONS[$name];
        }

        // Búsqueda sin distinguir mayúsculas/minúsculas.
        foreach (self::TRANSLATIONS as $english => $spanish) {
            if (mb_strtolower($english) === mb_strtolower($name)) {
                return $spanish;
            }
        }

        return $name;
    }

    /**
     * Consulta Nager.Date para un año. Si el servicio falla se devuelve vacío
     * (no se inventan fechas).
     */
    private function fetchYear(int $year): array
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/NI");

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Throwable $e) {
            \Log::warning("HolidayService: no se pudieron obtener los feriados de Nicaragua {$year}: {$e->getMessage()}");
        }

        return [];
    }
}
