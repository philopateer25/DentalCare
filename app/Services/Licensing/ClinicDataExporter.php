<?php

namespace App\Services\Licensing;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Builds a ZIP of everything a clinic owns: one CSV per table (UTF-8 with
 * BOM so Excel shows Arabic correctly) plus the patient files.
 *
 * Tables are discovered automatically: a table belongs to the clinic if it
 * has `practice_id`, or a `<parent>_id` column pointing at a table that
 * does (followed up to 4 levels: tooth findings → examinations → patients).
 * Passwords, tokens and secrets are never exported.
 */
class ClinicDataExporter
{
    private const SKIP_TABLES = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'password_reset_tokens', 'password_resets', 'personal_access_tokens',
        'permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions',
        'license_clock_ticks', 'push_subscriptions', 'notifications', 'sqlite_sequence',
    ];

    private const SENSITIVE_COLUMN = '/password|remember_token|secret|token|api_key/i';

    /** @var array<string, list<string>> table => columns */
    private array $columns = [];

    /** @var array<string, array{0: string, 1: string}|string> table => 'practice' | [fk column, parent table] */
    private array $scopes = [];

    /**
     * @return string path to the temporary ZIP file (caller deletes it)
     */
    public function export(int $practiceId): string
    {
        $this->discover();

        $zipPath = tempnam(sys_get_temp_dir(), 'doxamigo-export-');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $tempFiles = [];

        foreach (array_keys($this->scopes) as $table) {
            $csv = $this->writeCsv($table, $practiceId);
            $tempFiles[] = $csv;
            $zip->addFile($csv, "data/{$table}.csv");
        }

        $this->addPatientFiles($zip, $practiceId);

        $zip->addFromString('README.txt', implode("\n", [
            'DOXamigo data export',
            'Created: '.now()->toDateTimeString(),
            '',
            'data/   one CSV file per table (open with Excel).',
            'files/  patient files (x-rays, photos, documents) by patient id.',
        ]));

        $zip->close();

        foreach ($tempFiles as $file) {
            @unlink($file);
        }

        return $zipPath;
    }

    /** Find every table that belongs to a practice and how to filter it. */
    private function discover(): void
    {
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (! in_array($name, self::SKIP_TABLES, true) && ! str_starts_with($name, 'telescope') && ! str_starts_with($name, 'pulse')) {
                $this->columns[$name] = Schema::getColumnListing($name);
            }
        }

        if (isset($this->columns['practices'])) {
            $this->scopes['practices'] = 'self';
        }

        foreach ($this->columns as $table => $cols) {
            if (in_array('practice_id', $cols, true)) {
                $this->scopes[$table] = 'practice';
            }
        }

        // Follow foreign keys to already-scoped tables, a few levels deep.
        for ($depth = 0; $depth < 4; $depth++) {
            $added = false;

            foreach ($this->columns as $table => $cols) {
                if (isset($this->scopes[$table])) {
                    continue;
                }

                foreach ($cols as $col) {
                    if (! str_ends_with($col, '_id')) {
                        continue;
                    }

                    $parent = Str::plural(Str::beforeLast($col, '_id'));

                    if ($parent !== $table && isset($this->scopes[$parent]) && in_array('id', $this->columns[$parent], true)) {
                        $this->scopes[$table] = [$col, $parent];
                        $added = true;
                        break;
                    }
                }
            }

            if (! $added) {
                break;
            }
        }
    }

    private function scopedQuery(string $table, int $practiceId): Builder
    {
        $scope = $this->scopes[$table];
        $query = DB::table($table);

        if ($scope === 'self') {
            return $query->where('id', $practiceId);
        }

        if ($scope === 'practice') {
            return $query->where('practice_id', $practiceId);
        }

        [$column, $parent] = $scope;

        return $query->whereIn($column, $this->scopedQuery($parent, $practiceId)->select("{$parent}.id"));
    }

    private function writeCsv(string $table, int $practiceId): string
    {
        $columns = array_values(array_filter(
            $this->columns[$table],
            fn (string $col) => preg_match(self::SENSITIVE_COLUMN, $col) !== 1,
        ));

        $path = tempnam(sys_get_temp_dir(), 'doxamigo-csv-');
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $columns);

        $query = $this->scopedQuery($table, $practiceId)->select(array_map(fn ($c) => "{$table}.{$c}", $columns));

        if (in_array('id', $columns, true)) {
            $query->orderBy("{$table}.id");
        }

        foreach ($query->cursor() as $row) {
            fputcsv($handle, array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value), (array) $row));
        }

        fclose($handle);

        return $path;
    }

    private function addPatientFiles(ZipArchive $zip, int $practiceId): void
    {
        if (! isset($this->scopes['patient_files'], $this->columns['patient_files']) || ! in_array('file_path', $this->columns['patient_files'], true)) {
            return;
        }

        $disks = array_unique([config('filesystems.default'), 'local', 'public']);

        foreach ($this->scopedQuery('patient_files', $practiceId)->select(['patient_files.patient_id', 'patient_files.file_path'])->cursor() as $file) {
            if (empty($file->file_path)) {
                continue;
            }

            foreach ($disks as $disk) {
                try {
                    if (Storage::disk($disk)->exists($file->file_path)) {
                        $zip->addFile(Storage::disk($disk)->path($file->file_path), "files/patient-{$file->patient_id}/".basename($file->file_path));
                        break;
                    }
                } catch (\Throwable) {
                    // Disk not configured — try the next one.
                }
            }
        }
    }
}
