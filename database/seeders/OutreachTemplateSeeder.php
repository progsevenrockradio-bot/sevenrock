<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OutreachTemplate;
use Illuminate\Database\Seeder;

class OutreachTemplateSeeder extends Seeder
{
    public function run(): void
    {
        OutreachTemplate::query()->updateOrCreate(
            ['name' => 'Convocatoria para Productores'],
            [
                'subject' => 'Invita bandas a promocionarse - Código {program_code}',
                'body' => <<<'HTML'
<p>Estimado/a {producer_name},</p>
<p>Espero que estés bien.</p>
<p>Quiero proponerte una idea muy interesante: que invites a bandas musicales a promocionarse en nuestra web. Cada banda que tú recomiendes podrá subir su contenido, y nosotros lo publicaremos con tu código de referencia.</p>
<p><strong>¿CÓMO FUNCIONA?</strong></p>
<p>Cada programa de radio tiene un código único. El tuyo es: <strong>{program_code}</strong></p>
<p>Tú invitas a las bandas que quieras promocionar. Les pides que nos envíen el material exactamente con estas especificaciones:</p>
<p><strong>IMÁGENES</strong><br>- Tamaño fijo: {image_specs}<br>- Una foto grupal y, si es posible, fotos individuales de los miembros</p>
<p><strong>AUDIO</strong><br>- Archivo MP3 con el stack completo (canción, demo o cuña promocional)<br>- Calidad mínima: {audio_specs}</p>
<p><strong>INFORMACIÓN DE LA BANDA</strong><br>- Nombre de la banda<br>- Género musical<br>- Breve biografía<br>- Miembros: nombres, apellidos e instrumento<br>- Redes sociales oficiales</p>
<p><strong>CÓDIGO DEL PROGRAMA</strong><br>Indícale a la banda que incluya {program_code} en el asunto o mensaje.</p>
<p><strong>PLAZOS IMPORTANTES</strong><br>- Nuestra web estará activa: {launch_date}<br>- Queremos recibir la información en un plazo de {submission_days} (contando desde hoy)<br>- Nosotros nos encargamos de subir todo y probar la estructura</p>
<p><strong>¿QUÉ GANAS TÚ?</strong><br>- Tu programa tendrá visibilidad asociada a nuevas bandas<br>- Ayudarás a músicos independientes<br>- Automatizamos la llegada de contenido</p>
<p>Gracias por tu colaboración.</p>
<p>Saludos,<br>{radio_name}</p>
HTML,
                'variables' => OutreachTemplate::defaultVariables(),
                'is_active' => true,
            ]
        );
    }
}
