<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Horarios de Programas - Seven Rock Radio</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            background-color: #ffffff;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #c32720;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #c32720;
            margin: 0;
            font-size: 24px;
            text-transform: uppercase;
        }
        .day-section {
            margin-bottom: 30px;
        }
        .day-title {
            font-size: 18px;
            color: #151515;
            background-color: #f4f4f4;
            padding: 8px 12px;
            border-left: 4px solid #c32720;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #dddddd;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #fafafa;
            font-weight: bold;
            color: #555555;
            font-size: 14px;
        }
        td {
            font-size: 14px;
        }
        .time-col {
            width: 80px;
            font-weight: bold;
            color: #c32720;
        }
        .program-name {
            font-weight: bold;
            font-size: 15px;
            color: #151515;
            margin-bottom: 4px;
        }
        .program-desc {
            color: #666666;
            font-size: 12px;
            margin-top: 4px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #999999;
            border-top: 1px solid #eeeeee;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Seven Rock Radio</h1>
        <p>Programación Oficial</p>
    </div>

    @foreach($groupedPrograms as $group)
        <div class="day-section">
            <div class="day-title">{{ $group['day'] }}</div>
            <table>
                <thead>
                    <tr>
                        <th class="time-col">Hora</th>
                        <th>Programa y Detalles</th>
                        <th style="width: 120px;">Conductor</th>
                        <th style="width: 80px;">Duración</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($group['programs'] as $program)
                        <tr>
                            <td class="time-col">{{ $program['time'] }}</td>
                            <td>
                                <div class="program-name">{{ $program['name'] }}</div>
                                @if($program['description'])
                                    <div class="program-desc">{{ Str::limit($program['description'], 100) }}</div>
                                @endif
                            </td>
                            <td>{{ $program['conductor'] }}</td>
                            <td>{{ $program['duration'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center;">No hay programas registrados para este día.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="footer">
        Generado el {{ date('d/m/Y') }} a las {{ date('H:i') }} - Seven Rock Radio
    </div>
</body>
</html>
