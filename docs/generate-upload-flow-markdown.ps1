$root = 'C:\laragon\www\Plantilla\SevenRockRadio'
$out = Join-Path $root 'docs\podcast-upload-flow-full.md'

$files = @(
    'app/Http/Controllers/Admin/PodcastUploadController.php',
    'app/Jobs/UploadMp3Job.php',
    'app/Services/ArchiveOrgPodcastService.php',
    'resources/views/admin/podcast-uploads/index.blade.php',
    'resources/js/app.js',
    'config/filesystems.php',
    'config/services.php',
    'tests/Feature/AdminPodcastUploadTest.php'
)

$languageMap = @{
    '.php' = 'php'
    '.js' = 'js'
    '.json' = 'json'
    '.css' = 'css'
    '.md' = 'md'
}

$builder = [System.Text.StringBuilder]::new()
[void]$builder.AppendLine('# Flujo completo de subida de podcasts')
[void]$builder.AppendLine()
[void]$builder.AppendLine('Este archivo contiene, sin omitir contenido, la lógica completa del flujo de subida de MP3 a RadioBOSS y Archive.org, junto con el formulario, la validación del cliente y las pruebas asociadas.')
[void]$builder.AppendLine()

foreach ($rel in $files) {
    $path = Join-Path $root $rel
    $content = Get-Content -LiteralPath $path -Raw
    $lang = ''

    if ($rel -like '*.blade.php') {
        $lang = 'blade'
    } else {
        $ext = [System.IO.Path]::GetExtension($rel)
        if ($languageMap.ContainsKey($ext)) {
            $lang = $languageMap[$ext]
        }
    }

    [void]$builder.AppendLine("## $rel")
    [void]$builder.AppendLine()
    [void]$builder.AppendLine("```$lang")
    [void]$builder.AppendLine($content)
    [void]$builder.AppendLine('```')
    [void]$builder.AppendLine()
}

[System.IO.File]::WriteAllText($out, $builder.ToString(), [System.Text.UTF8Encoding]::new($false))

Write-Output "created: $out"
