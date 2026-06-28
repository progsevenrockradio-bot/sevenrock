<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Personas Desaparecidas</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #e32636; padding-bottom: 10px; }
        .header h1 { color: #e32636; margin: 0; }
        .person-card { border: 1px solid #ccc; margin-bottom: 20px; page-break-inside: avoid; border-radius: 8px; padding: 15px; background: #fafafa; }
        .person-info { width: 65%; float: left; }
        .person-photo { width: 30%; float: right; text-align: center; }
        .person-photo img { max-width: 100%; max-height: 200px; border-radius: 4px; }
        .clearfix::after { content: ""; clear: both; display: table; }
        h3 { margin-top: 0; color: #111; font-size: 18px; text-transform: uppercase; }
        p { margin: 5px 0; }
        .emergency { background: #fee; border: 1px solid #fcc; padding: 10px; margin-top: 15px; border-radius: 4px; color: #c00; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE PERSONAS DESAPARECIDAS</h1>
        <p>Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @foreach($missingPersons as $person)
        <div class="person-card clearfix">
            <div class="person-info">
                <h3>{{ $person->full_name }}</h3>
                <p><strong>Edad:</strong> {{ $person->age ? $person->age . ' años' : 'No especificada' }} ({{ ucfirst($person->sex) }})</p>
                <p><strong>Desaparecido desde:</strong> {{ $person->missing_since ? $person->missing_since->format('d/m/Y') : 'No especificado' }}</p>
                <p><strong>Última vez visto:</strong> {{ $person->last_seen_location ?: 'No especificada' }}</p>
                <p><strong>Residencia:</strong> {{ $person->place_of_residence ?: 'No especificada' }}</p>
                @if($person->description)
                    <p><strong>Descripción:</strong> {{ $person->description }}</p>
                @endif
                
                @if($person->emergency_contact_number)
                <div class="emergency">
                    CONTACTO DE EMERGENCIA: {{ $person->emergency_contact_number }}
                </div>
                @endif
            </div>
            <div class="person-photo">
                @if($person->photo_url)
                    <!-- We need to ensure the image URL is accessible to DOMPDF. Sometimes local URLs are tricky, but full URLs work if allow_url_fopen is true -->
                    <img src="{{ $person->photo_url }}" alt="Foto">
                @else
                    <div style="width: 100%; height: 150px; background: #eee; border: 1px solid #ddd; line-height: 150px; color: #999;">
                        Sin foto
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    <div class="footer">
        Este documento es un reporte oficial de Seven Rock Radio. Si tienes información sobre alguna de estas personas, comunícate con los números de emergencia indicados.
    </div>
</body>
</html>
