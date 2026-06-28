<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MissingPerson;
use Illuminate\Support\Facades\File;

class ImportPdfDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonFilePath = __DIR__ . '/parsed_data.json';
        
        if (!File::exists($jsonFilePath)) {
            $this->command->error("Archivo JSON no encontrado en: {$jsonFilePath}");
            return;
        }

        $jsonData = File::get($jsonFilePath);
        $records = json_decode($jsonData, true);

        if (!$records) {
            $this->command->error("Error al decodificar el archivo JSON");
            return;
        }

        $this->command->info("Iniciando importación de " . count($records) . " registros...");

        $inserted = 0;
        $skipped = 0;

        foreach ($records as $record) {
            // Check if cedula exists and already in db to avoid duplicates
            if (!empty($record['cedula'])) {
                $exists = MissingPerson::where('cedula', $record['cedula'])->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }
            }

            // Create record
            MissingPerson::create([
                'full_name' => $record['full_name'] ?? 'Desconocido',
                'cedula' => $record['cedula'] ?? null,
                'age' => $record['age'] ?? null,
                'sex' => $record['sex'] ?? null,
                'place_of_residence' => $record['place_of_residence'] ?? null,
                'hospital_admitted_to' => $record['hospital_admitted_to'] ?? null,
                'date_update' => $record['date_update'] ?? null,
                'service_provided' => $record['service_provided'] ?? null,
                'is_approved' => true,
                'status' => 'active',
            ]);

            $inserted++;
        }

        $this->command->info("Importación finalizada. Insertados: {$inserted}. Omitidos por cédula duplicada: {$skipped}.");
    }
}
