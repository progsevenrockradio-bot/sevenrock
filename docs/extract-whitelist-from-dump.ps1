param(
    [string]$SourceDump = 'C:\Users\JOSE FONT\Desktop\7RR LOCAL\00.- New Web 7RR\u531780502_BD_7rr.sql\u531780502_BD_7rr.sql',
    [string]$OutputFile = (Join-Path $PSScriptRoot 'sevenrock_whitelist_extracted.sql')
)

$whitelist = @(
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

if (-not (Test-Path -LiteralPath $SourceDump)) {
    throw "Source dump not found: $SourceDump"
}

$reader = [System.IO.StreamReader]::new($SourceDump)
$writer = [System.IO.StreamWriter]::new($OutputFile, $false, [System.Text.Encoding]::UTF8)

try {
    $inTableSection = $false
    $capturing = $false
    $headerWritten = $false

    while (-not $reader.EndOfStream) {
        $line = $reader.ReadLine()

        if ($line -match '^-- Table structure for table `([^`]+)`') {
            $table = $Matches[1]
            $inTableSection = $true
            $capturing = $whitelist -contains $table

            if ($capturing) {
                $writer.WriteLine($line)
            }

            continue
        }

        if (-not $inTableSection) {
            $writer.WriteLine($line)
            continue
        }

        if ($capturing) {
            $writer.WriteLine($line)
        }
    }

    $writer.WriteLine('COMMIT;')
}
finally {
    $writer.Flush()
    $writer.Close()
    $reader.Close()
}

Write-Host "Whitelisted dump written to: $OutputFile"

