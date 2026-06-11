<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de Archivos y Multimedia - Seven Rock Radio</title>
    <meta name="description" content="Panel interno para auditar el estado del almacenamiento local, conexión a Backblaze B2, y accesibilidad de los recursos multimedia.">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0c10;
            --surface-color: rgba(22, 26, 35, 0.65);
            --surface-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --accent-color: #ef4444;
            --accent-gradient: linear-gradient(135deg, #f87171, #ef4444);
            --success-color: #10b981;
            --success-gradient: linear-gradient(135deg, #34d399, #10b981);
            --warning-color: #f59e0b;
            --warning-gradient: linear-gradient(135deg, #fbbf24, #f59e0b);
            --info-color: #3b82f6;
            --info-gradient: linear-gradient(135deg, #60a5fa, #3b82f6);
            --font-display: 'Outfit', sans-serif;
            --font-sans: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            font-family: var(--font-sans);
            line-height: 1.6;
            padding: 2rem 1rem;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(239, 68, 68, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(59, 130, 246, 0.05) 0%, transparent 40%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        /* Header Layout */
        header {
            background: var(--surface-color);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-gradient);
        }

        .header-title-area {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        h1 {
            font-family: var(--font-display);
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(to right, #f3f4f6, #9ca3af);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            max-width: 800px;
        }

        .badge-system {
            font-family: var(--font-display);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .badge-system::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--accent-color);
            box-shadow: 0 0 10px var(--accent-color);
            display: inline-block;
        }

        /* Layout Grid */
        .grid-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: var(--surface-color);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .card:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }

        .card-title {
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 0.75rem;
        }

        /* Status colors */
        .status-success { color: var(--success-color); }
        .status-error { color: var(--accent-color); }
        .status-warning { color: var(--warning-color); }
        .status-info { color: var(--info-color); }

        .list-info {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .list-info li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
            padding-bottom: 0.5rem;
        }

        .list-info li span:first-child {
            color: var(--text-secondary);
        }

        .list-info li span:last-child {
            font-family: var(--font-mono);
            font-weight: 600;
        }

        .tag-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .tag-status.ok {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .tag-status.err {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .tag-status.warn {
            background: rgba(245, 158, 11, 0.1);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* DNS Status list */
        .dns-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .dns-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.03);
        }

        .dns-host {
            font-family: var(--font-mono);
            font-size: 0.85rem;
            word-break: break-all;
        }

        /* Database Audit Table Card */
        .audit-card {
            background: var(--surface-color);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-top: 1.5rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }

        th {
            background: rgba(10, 12, 16, 0.85);
            font-family: var(--font-display);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem;
            color: var(--text-secondary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .entity-cell {
            font-weight: 600;
        }

        .entity-sub {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .path-cell {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            word-break: break-all;
            max-width: 300px;
        }

        .badge-http {
            font-family: var(--font-mono);
            font-weight: 700;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        .badge-http.code-200 {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .badge-http.code-404 {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            font-weight: 800;
        }

        .badge-http.code-other {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        .recommendation-banner {
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.25);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .recommendation-title {
            font-family: var(--font-display);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--warning-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .recommendation-banner ul {
            padding-left: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .action-button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-family: var(--font-display);
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: opacity 0.2s ease;
            font-size: 0.9rem;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: white;
            border: none;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        .status-indicator.green { background-color: var(--success-color); box-shadow: 0 0 8px var(--success-color); }
        .status-indicator.red { background-color: var(--accent-color); box-shadow: 0 0 8px var(--accent-color); }
        .status-indicator.orange { background-color: var(--warning-color); box-shadow: 0 0 8px var(--warning-color); }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header id="audit-header">
            <div class="header-title-area">
                <h1>Auditoría y Diagnóstico de Archivos Multimedia</h1>
                <span class="badge-system">Modo de Diagnóstico Activo</span>
            </div>
            <p class="subtitle">Este panel audita la configuración de almacenamiento local, el enlace simbólico storage, la conexión a la API de Backblaze B2 y comprueba uno por uno si los recursos cargados en base de datos están disponibles para el público.</p>
        </header>

        <!-- TOP GRID (ENVIRONMENT, CONFIG, DNS) -->
        <div class="grid-summary">
            <!-- ENVIRONMENT CARD -->
            <section class="card" id="env-card">
                <h2 class="card-title">🖥️ Servidor y Entorno</h2>
                <ul class="list-info">
                    @foreach($env as $label => $value)
                        <li>
                            <span>{{ $label }}</span>
                            <span>{{ $value }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <!-- BACKBLAZE CONFIG CARD -->
            <section class="card" id="b2-config-card">
                <h2 class="card-title">
                    ☁️ Backblaze B2 Config
                    @if($isB2Configured)
                        <span class="tag-status ok">Configurado</span>
                    @else
                        <span class="tag-status err">No Configurado</span>
                    @endif
                </h2>
                <ul class="list-info">
                    @foreach($b2Config as $label => $value)
                        <li>
                            <span>{{ $label }}</span>
                            <span>{{ $value }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <!-- DNS RESOLUTION CARD -->
            <section class="card" id="dns-card">
                <h2 class="card-title">🌐 Resolución de Nombres (DNS)</h2>
                <div class="dns-list">
                    @foreach($dnsResults as $host => $result)
                        <div class="dns-item">
                            <span class="dns-host">{{ $host }}</span>
                            @if($result['resolves'])
                                <span class="tag-status ok" title="IP: {{ $result['ip'] }}">Resuelve ({{ $result['ip'] }})</span>
                            @else
                                <span class="tag-status err" title="No resuelve">Fallo de DNS</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- DATABASE CONNECTION CARD -->
            <section class="card" id="db-status-card">
                <h2 class="card-title">
                    🗄️ Base de Datos
                    @if($dbConnected)
                        <span class="tag-status ok">Conectado</span>
                    @else
                        <span class="tag-status err">Desconectado</span>
                    @endif
                </h2>
                <ul class="list-info">
                    <li style="flex-direction: column; align-items: flex-start; gap: 0.5rem;">
                        <span>Estado de Conexión</span>
                        <span style="font-family: var(--font-sans); font-size: 0.8rem; word-break: break-word; color: {{ $dbConnected ? 'var(--success-color)' : 'var(--accent-color)' }}">
                            {{ $dbMessage }}
                        </span>
                    </li>
                </ul>
            </section>
        </div>

        <!-- SYMLINKS & DIRECT B2 CONNECTION -->
        <div class="grid-summary">
            <!-- STORAGE SYMLINK CARD -->
            <section class="card" id="symlink-card">
                <h2 class="card-title">📂 Acceso Local & Enlace Simbólico</h2>
                <ul class="list-info">
                    <li>
                        <span>Enlace público (public/storage)</span>
                        @if($storageSymlink['exists'])
                            <span class="tag-status ok">Existe</span>
                        @else
                            <span class="tag-status err">No Existe</span>
                        @endif
                    </li>
                    <li>
                        <span>¿Es un enlace simbólico?</span>
                        @if($storageSymlink['is_link'])
                            <span class="tag-status ok">Sí (Symlink)</span>
                        @else
                            <span class="tag-status err">No (Directorio Físico)</span>
                        @endif
                    </li>
                    @if($storageSymlink['is_link'])
                        <li>
                            <span>Destino del enlace</span>
                            <span style="font-size: 0.75rem; word-break: break-all;">{{ $storageSymlink['target'] }}</span>
                        </li>
                        <li>
                            <span>¿Apunta al destino correcto?</span>
                            @if($storageSymlink['points_to_correct_target'])
                                <span class="tag-status ok">Sí</span>
                            @else
                                <span class="tag-status err">No (Incorrecto)</span>
                            @endif
                        </li>
                    @endif
                    <li>
                        <span>Lectura/Escritura en enlace</span>
                        <span>
                            {{ $storageSymlink['is_readable'] ? 'L' : '-' }}
                            /
                            {{ $storageSymlink['is_writable'] ? 'E' : '-' }}
                        </span>
                    </li>
                </ul>
            </section>

            <!-- FOLDERS PERMISSIONS -->
            <section class="card" id="folders-card">
                <h2 class="card-title">📁 Permisos de Carpetas</h2>
                <ul class="list-info">
                    @foreach($folderResults as $name => $res)
                        <li>
                            <span>{{ $name }}</span>
                            @if($res['exists'])
                                <span class="tag-status ok" title="Lectura: {{ $res['is_readable'] ? 'Sí':'No' }}, Escritura: {{ $res['is_writable'] ? 'Sí':'No' }}">
                                    Permisos: {{ $res['permissions'] }} ({{ $res['is_writable'] ? 'E':'R' }})
                                </span>
                            @else
                                <span class="tag-status err">No Existe</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <!-- B2 API CONNECTION CHECK -->
            <section class="card" id="b2-connection-card">
                <h2 class="card-title">🔒 Conexión API a Backblaze</h2>
                <ul class="list-info">
                    <li>
                        <span>Conectado con Éxito</span>
                        @if($b2Connection['connected'])
                            <span class="tag-status ok">Sí</span>
                        @else
                            <span class="tag-status err">No</span>
                        @endif
                    </li>
                    <li style="flex-direction: column; align-items: flex-start; gap: 0.5rem;">
                        <span>Resultado de la API</span>
                        <span style="font-family: var(--font-sans); font-size: 0.8rem; word-break: break-word; color: {{ $b2Connection['connected'] ? 'var(--success-color)' : 'var(--accent-color)' }}">
                            {{ $b2Connection['message'] }}
                        </span>
                    </li>
                    @if($b2Connection['api_url'])
                        <li>
                            <span>B2 API URL</span>
                            <span style="font-size: 0.75rem; font-family: var(--font-mono);">{{ $b2Connection['api_url'] }}</span>
                        </li>
                    @endif
                </ul>
            </section>
        </div>

        <!-- DATABASE MEDIA AUDIT TABLE -->
        <section class="audit-card" id="audit-table-card">
            <h2 class="card-title">🔍 Auditoría Detallada de Archivos en Base de Datos</h2>
            <p style="color: var(--text-secondary); font-size: 0.9rem;">Se analizan los campos de imágenes configurados en Tema Settings y los últimos registros de las tablas de contenidos.</p>
            
            <div class="table-responsive">
                <table id="audit-table">
                    <thead>
                        <tr>
                            <th>Origen y Elemento</th>
                            <th>Valor en DB / Nombre de Archivo</th>
                            <th>Estado Local</th>
                            <th>HTTP Pública</th>
                            <th>HTTP Friendly B2</th>
                            <th>HTTP S3 B2</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mediaAudit as $item)
                            <tr>
                                <td class="entity-cell">
                                    {{ $item['label'] }}
                                    <div class="entity-sub">{{ $item['entity'] }}</div>
                                </td>
                                <td class="path-cell" title="Key extraída: {{ $item['key'] }}">
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem; word-break: break-all;">{{ $item['db_value'] }}</div>
                                    <strong style="color: #60a5fa;">{{ basename($item['key']) }}</strong>
                                </td>
                                <td>
                                    @if($item['local']['exists'])
                                        <div class="tag-status ok" style="display:inline-block; font-size: 0.7rem;" title="Path: {{ $item['local']['path'] }}">
                                            Existe en {{ $item['local']['where'] }}
                                        </div>
                                    @else
                                        <div class="tag-status err" style="display:inline-block; font-size: 0.7rem;">
                                            No existe
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $pubCode = (int) $item['public_url_status'];
                                        $pubClass = $pubCode === 200 ? 'code-200' : ($pubCode === 404 ? 'code-404' : 'code-other');
                                    @endphp
                                    <span class="badge-http {{ $pubClass }}" title="URL: {{ $item['normalized_url'] }}">
                                        {{ $item['public_url_status'] }}
                                    </span>
                                </td>
                                <td>
                                    @if($item['b2_friendly_url'])
                                        @php
                                            $friendCode = (int) $item['b2_friendly_status'];
                                            $friendClass = $friendCode === 200 ? 'code-200' : ($friendCode === 404 ? 'code-404' : 'code-other');
                                        @endphp
                                        <span class="badge-http {{ $friendClass }}" title="URL: {{ $item['b2_friendly_url'] }}">
                                            {{ $item['b2_friendly_status'] }}
                                        </span>
                                    @else
                                        <span style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item['b2_s3_url'])
                                        @php
                                            $s3Code = (int) $item['b2_s3_status'];
                                            $s3Class = $s3Code === 200 ? 'code-200' : ($s3Code === 404 ? 'code-404' : 'code-other');
                                        @endphp
                                        <span class="badge-http {{ $s3Class }}" title="URL: {{ $item['b2_s3_url'] }}">
                                            {{ $item['b2_s3_status'] }}
                                        </span>
                                    @else
                                        <span style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No se encontraron registros de imágenes/media en la base de datos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- RECOMMENDATIONS AND ACTIONS -->
        <section class="card" id="recommendations-card">
            <h2 class="card-title">💡 Diagnósticos Comunes e Indicaciones</h2>
            
            <div class="recommendation-banner">
                <div class="recommendation-title">🔍 ¿Qué significan estos resultados?</div>
                <ul>
                    <li><strong>HTTP Pública es 404 o Falla de DNS:</strong> Si el subdominio proxy `media.sevenrockradio.com` no resuelve en DNS, esta URL pública siempre fallará. Si no han configurado el DNS en Cloudflare, el subdominio no existirá para el navegador.</li>
                    <li><strong>HTTP Friendly B2 es 404:</strong> Significa que el archivo no está en el bucket de Backblaze. Esto puede deberse a que el bucket real es diferente (con otro nombre), o que las credenciales de B2 son incorrectas en el `.env` de producción, por lo que nunca se llegó a subir a la nube.</li>
                    <li><strong>Estado Local es "No existe":</strong> Si falla la subida a B2, el sistema intenta guardarlo en local. Si el local tampoco existe, indica que la subida del formulario falló o la base de datos se actualizó sin guardar el archivo en disco.</li>
                    <li><strong>Estado Local "Existe" pero HTTP Pública da 404:</strong> Si el archivo físico está en el servidor pero no se ve en la web, el symlink de almacenamiento (`public/storage`) de Hostinger está roto o Apache no permite consultar esa ruta debido a directivas de redirección en shared hosting.</li>
                </ul>
            </div>

            <div class="action-button-group">
                <a href="{{ route('admin.settings.edit') }}" class="btn btn-primary">Ir a Ajustes del Tema</a>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        </section>
    </div>
</body>
</html>
