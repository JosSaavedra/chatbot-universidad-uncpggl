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
    public static function supportsRange(Carbon $from, Carbon $to): bool
    {
        return $from->year >= 2000 && $to->year <= 2100 && $to->gte($from)
            && $to->year - $from->year <= 1
            && $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) < 366;
    }

    public function getHolidays(Carbon $from, Carbon $to): array
    {
        if (!self::supportsRange($from, $to)) {
            return [];
        }

        return $this->fetchRange($from, $to);
    }

    public function getHolidaysForCurrentAndNextYear(Carbon $today): array
    {
        if ($today->year < 2000 || $today->year >= 2100) {
            return $this->getHolidays($today->copy()->startOfYear(), $today->copy()->endOfYear());
        }

        return $this->fetchRange($today->copy()->startOfYear(), $today->copy()->addYear()->endOfYear());
    }

    private function fetchRange(Carbon $from, Carbon $to): array
    {
        $result = [];
        $deadline = hrtime(true) + 3_000_000_000;
        foreach (range($from->year, $to->year) as $year) {
            $key = "holidays_ni_{$year}";
            $cached = Cache::get($key);
            if (!is_array($cached) && hrtime(true) < $deadline) {
                // One worker may contact Nager.Date at a time. Others use cached results.
                $cached = Cache::lock('holidays:upstream', 5)->get(function () use ($key, $year, $deadline) {
                    $cached = Cache::get($key);
                    if (is_array($cached)) {
                        return $cached;
                    }

                    $seconds = min(1.5, ($deadline - hrtime(true)) / 1_000_000_000);
                    if ($seconds < 0.1 || !\Illuminate\Support\Facades\RateLimiter::attempt(
                        'holidays:upstream-budget', 12, static fn () => true, 60
                    )) {
                        return [];
                    }

                    $holidays = $this->fetchYear($year, $seconds);
                    Cache::put($key, $holidays, $holidays === [] ? 60 : 86400);

                    return $holidays;
                });
            }

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
    private function fetchYear(int $year, float $seconds): array
    {
        try {
            $response = Http::connectTimeout(min(1, $seconds))->timeout($seconds)
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
