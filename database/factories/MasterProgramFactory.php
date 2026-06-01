<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MasterProgram;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MasterProgram>
 */
final class MasterProgramFactory extends Factory
{
    protected $model = MasterProgram::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();
        $codePrefix = Str::upper(preg_replace('/[^A-Za-z0-9]+/', '', (string) Str::of($name)->ascii()) ?: '');
        $codePrefix = substr($codePrefix, 0, 12);
        $codePrefix = $codePrefix !== '' ? $codePrefix : 'PROGRAMA';

        return [
            'nombre' => $name,
            'program_code' => substr($codePrefix, 0, 12),
            'code_prefix' => substr($codePrefix, 0, 12),
            'conductor' => $this->faker->name(),
            'dia_transmision' => $this->faker->randomElement(['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO']),
            'hora_transmision' => $this->faker->time('H:i:s'),
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 120,
            'genero' => $this->faker->randomElement(['Rock', 'Metal', 'Pop', 'Indie']),
            'caratula_url' => null,
            'descripcion' => $this->faker->sentence(),
            'live_title' => null,
            'live_description' => null,
            'live_image_url' => null,
            'live_starts_at' => null,
            'live_ends_at' => null,
            'default_news_ids' => null,
            'live_news_ids' => null,
            'preview_news_ids' => null,
            'comentario_predeterminado' => null,
            'red_social1_url' => null,
            'red_social2_url' => null,
            'activo' => true,
            'archive_identifier' => null,
            'vistas_archive' => 0,
            'escuchas_locales' => 0,
            'vistas_totales' => 0,
            'stats_updated_at' => null,
            'ruta_ftp' => 'Programas',
            'email_notificacion' => $this->faker->safeEmail(),
            'email_copia_notificacion' => null,
        ];
    }
}
