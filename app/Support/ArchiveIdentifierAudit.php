<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ArchiveIdentifierAudit
{
    /**
     * @return array{
     *   summary: array{master_programs_checked:int, radio_programs_checked:int, issues:int},
     *   master_programs: array<int, array<string, mixed>>,
     *   radio_programs: array<int, array<string, mixed>>
     * }
     */
    public function buildReport(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));

        $masterPrograms = $this->auditMasterPrograms($limit);
        $radioPrograms = $this->auditRadioPrograms($limit);

        return [
            'summary' => [
                'master_programs_checked' => $masterPrograms['checked'],
                'radio_programs_checked' => $radioPrograms['checked'],
                'issues' => count($masterPrograms['issues']) + count($radioPrograms['issues']),
            ],
            'master_programs' => $masterPrograms['issues'],
            'radio_programs' => $radioPrograms['issues'],
        ];
    }

    /**
     * @return array{checked:int, issues:array<int, array<string, mixed>>}
     */
    private function auditMasterPrograms(int $limit): array
    {
        if (! Schema::hasTable('master_programs')) {
            return ['checked' => 0, 'issues' => []];
        }

        $rows = DB::table('master_programs')
            ->select(['id', 'nombre', 'archive_identifier', 'activo', 'updated_at'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $issues = [];
        foreach ($rows as $row) {
            $identifier = trim((string) ($row->archive_identifier ?? ''));
            $reasons = [];

            if ($identifier === '') {
                $reasons[] = 'missing_identifier';
            } elseif (! $this->isPlausibleIdentifier($identifier)) {
                $reasons[] = 'invalid_identifier_format';
            }

            if ($reasons === []) {
                continue;
            }

            $issues[] = [
                'table' => 'master_programs',
                'id' => (int) $row->id,
                'name' => trim((string) ($row->nombre ?? '')),
                'identifier' => $identifier,
                'issues' => $reasons,
                'status' => $this->statusLabel((bool) ($row->activo ?? false)),
                'updated_at' => $row->updated_at ?? null,
            ];
        }

        return [
            'checked' => $rows->count(),
            'issues' => $issues,
        ];
    }

    /**
     * @return array{checked:int, issues:array<int, array<string, mixed>>}
     */
    private function auditRadioPrograms(int $limit): array
    {
        if (! Schema::hasTable('radio_programs')) {
            return ['checked' => 0, 'issues' => []];
        }

        $rows = DB::table('radio_programs as rp')
            ->leftJoin('master_programs as mp', 'mp.id', '=', 'rp.master_program_id')
            ->select([
                'rp.id',
                'rp.titulo_programa',
                'rp.fecha_emision',
                'rp.archive_org_status',
                'rp.archive_org_remote_path',
                'rp.archive_org_uploaded_at',
                'rp.archive_org_metadata',
                'rp.sync_archive_org',
                'mp.archive_identifier as master_archive_identifier',
                'mp.nombre as master_name',
            ])
            ->where(function ($query): void {
                $query->where('rp.sync_archive_org', true)
                    ->orWhereIn('rp.archive_org_status', ['archive_verified', 'archive_pending_indexing', 'archive_uploaded', 'uploaded'])
                    ->orWhereNotNull('rp.archive_org_uploaded_at')
                    ->orWhereNotNull('rp.archive_org_remote_path')
                    ->orWhereNotNull('rp.archive_org_metadata');
            })
            ->orderByDesc('rp.updated_at')
            ->limit($limit)
            ->get();

        $issues = [];
        foreach ($rows as $row) {
            $metadata = $this->decodeMetadata($row->archive_org_metadata ?? null);
            $identifier = trim((string) data_get($metadata, 'identifier', ''));
            $remotePath = trim((string) data_get($metadata, 'remote_path', ''));
            $masterIdentifier = trim((string) ($row->master_archive_identifier ?? ''));
            $status = trim((string) ($row->archive_org_status ?? ''));
            $reasons = [];
            $hasCompletedSyncSignal = in_array($status, ['archive_verified', 'archive_pending_indexing', 'archive_uploaded', 'uploaded'], true)
                || trim((string) ($row->archive_org_uploaded_at ?? '')) !== '';

            if (in_array($status, ['skipped', 'archive_skipped'], true)) {
                continue;
            }

            if ($hasCompletedSyncSignal || (bool) ($row->sync_archive_org ?? false)) {
                if ($identifier === '' && $masterIdentifier === '' && $remotePath === '') {
                    $reasons[] = 'missing_identifier';
                }

                if ($hasCompletedSyncSignal && $remotePath === '') {
                    $reasons[] = 'missing_remote_path';
                }

                if ($hasCompletedSyncSignal && empty($metadata)) {
                    $reasons[] = 'missing_metadata';
                }
            } elseif ($status !== '') {
                $reasons[] = 'sync_not_completed';
            }

            if ($identifier !== '' && $masterIdentifier !== '' && $this->normalizeIdentifier($identifier) !== $this->normalizeIdentifier($masterIdentifier)) {
                $reasons[] = 'identifier_mismatch';
            }

            if ($reasons === []) {
                continue;
            }

            $issues[] = [
                'table' => 'radio_programs',
                'id' => (int) $row->id,
                'name' => trim((string) ($row->titulo_programa ?: $row->master_name ?: '')),
                'identifier' => $identifier !== '' ? $identifier : $masterIdentifier,
                'remote_path' => $remotePath,
                'status' => $status !== '' ? $status : 'archive_pending',
                'issues' => $reasons,
                'date' => $row->fecha_emision ?? null,
            ];
        }

        return [
            'checked' => $rows->count(),
            'issues' => $issues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function isPlausibleIdentifier(string $identifier): bool
    {
        $identifier = trim($identifier);

        return $identifier !== ''
            && preg_match('/\A[a-z0-9][a-z0-9._-]*\z/i', $identifier) === 1
            && ! preg_match('/[._-]\z/', $identifier);
    }

    private function normalizeIdentifier(string $identifier): string
    {
        return preg_replace('/[^a-z0-9]+/i', '', mb_strtolower(trim(Str::ascii($identifier)))) ?: '';
    }

    private function statusLabel(bool $active): string
    {
        return $active ? 'active' : 'inactive';
    }
}
