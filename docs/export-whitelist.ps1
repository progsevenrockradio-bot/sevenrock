param(
    [string]$Database = $env:DB_DATABASE,
    [string]$User = $env:DB_USERNAME,
    [string]$Password = $env:DB_PASSWORD,
    [string]$OutputFile = (Join-Path $PSScriptRoot 'sevenrock_whitelist.sql'),
    [string]$MySqlDumpPath = 'mysqldump'
)

$tables = @(
    'wpsr_posts',
    'wpsr_postmeta',
    'wpsr_terms',
    'wpsr_term_taxonomy',
    'wpsr_term_relationships',
    'wpsr_users',
    'wpsr_usermeta',
    'wpsr_srr_audio',
    'wpsr_connections',
    'wpsr_connections_address',
    'wpsr_connections_date',
    'wpsr_connections_email',
    'wpsr_connections_link',
    'wpsr_connections_messenger',
    'wpsr_connections_meta',
    'wpsr_connections_phone',
    'wpsr_connections_social',
    'wpsr_connections_terms',
    'wpsr_connections_term_taxonomy',
    'wpsr_connections_term_relationships',
    'wpsr_ulike',
    'wpsr_ulike_meta',
    'wpsr_pmpro_groups',
    'wpsr_pmpro_membership_levels',
    'wpsr_pmpro_memberships_users',
    'wpsr_pmpro_subscriptions',
    'wpsr_pmpro_membership_ordermeta',
    'wpsr_pmpro_subscriptionmeta',
    'posts',
    'band_profiles',
    'bands',
    'radio_programs',
    'tracks',
    'now_playing',
    'settings',
    'subscriptions',
    'plans',
    'home_media',
    'social_links',
    'users'
)

if ([string]::IsNullOrWhiteSpace($Database)) {
    throw "Missing database name. Set DB_DATABASE or pass -Database."
}

if ([string]::IsNullOrWhiteSpace($User)) {
    throw "Missing database user. Set DB_USERNAME or pass -User."
}

if ($null -eq $Password) {
    $Password = ''
}

$args = @(
    '-u', $User,
    "-p$Password",
    $Database
) + $tables

$outDir = Split-Path -Parent $OutputFile
if (-not [string]::IsNullOrWhiteSpace($outDir) -and -not (Test-Path $outDir)) {
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null
}

Write-Host "Exporting whitelist tables from $Database ..."
Write-Host "Output: $OutputFile"

& $MySqlDumpPath @args | Set-Content -LiteralPath $OutputFile -Encoding UTF8

if ($LASTEXITCODE -ne 0) {
    throw "mysqldump failed with exit code $LASTEXITCODE"
}

Write-Host "Done."

