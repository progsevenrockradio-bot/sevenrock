<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

class ProgramScheduleFormatter
{
    /**
     * @param Collection $programs
     * @return array
     */
    public function format(Collection $programs): array
    {
        $daysOrder = [
            'Lunes' => 1,
            'Martes' => 2,
            'Miércoles' => 3,
            'Jueves' => 4,
            'Viernes' => 5,
            'Sábado' => 6,
            'Domingo' => 7,
        ];

        // Group by day of transmission
        $grouped = $programs->groupBy(function ($program) {
            $day = trim((string) $program->dia_transmision);
            return $day !== '' ? ucfirst(strtolower($day)) : 'Sin Día Asignado';
        });

        $result = [];
        foreach ($grouped as $day => $items) {
            $sortedItems = $items->sortBy(function ($program) {
                // Try to sort by time if available
                return $program->hora_transmision ?: '23:59:59';
            })->map(function ($program) {
                return [
                    'id' => $program->id,
                    'name' => $program->name ?? $program->nombre,
                    'conductor' => $program->conductor ?? 'Sin conductor',
                    'time' => $program->hora_transmision ? date('H:i', strtotime($program->hora_transmision)) : 'Hora a confirmar',
                    'duration' => $program->duracion_minutos ? $program->duracion_minutos . ' min' : '-',
                    'description' => $program->descripcion,
                ];
            })->values()->all();

            $result[] = [
                'day' => $day,
                'order' => $daysOrder[$day] ?? 99,
                'programs' => $sortedItems,
            ];
        }

        // Sort days logically
        usort($result, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return $result;
    }
}
