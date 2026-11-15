<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;

class DatabaseRestorer
{
    /**
     * Restore the active database from a dump file.
     *
     * @param string $dumpFilePath Path to database.sql or database.sqlite
     * @throws RuntimeException
     */
    public function restore(string $dumpFilePath): void
    {
        if (!file_exists($dumpFilePath)) {
            throw new RuntimeException("Database dump file not found at: {$dumpFilePath}");
        }

        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver", 'sqlite');

        match ($driver) {
            'sqlite' => $this->restoreSqlite($dumpFilePath),
            'mysql', 'mariadb' => $this->restoreMySql($dumpFilePath, $connectionName),
            default => $this->restoreGenericPdo($dumpFilePath, $connectionName),
        };
    }

    /**
     * Restore an SQLite database from an sqlite file or SQL script.
     */
    protected function restoreSqlite(string $dumpFilePath): void
    {
        $targetDbPath = config('database.connections.sqlite.database');

        // Disconnect and purge existing SQLite connection
        try {
            DB::purge('sqlite');
        } catch (\Throwable $e) {
            // Ignore
        }

        // If target is an in-memory database (:memory:)
        if ($targetDbPath === ':memory:') {
            $pdo = DB::connection('sqlite')->getPdo();
            if (str_ends_with($dumpFilePath, '.sqlite')) {
                // Attach the backup database and copy tables
                $escapedPath = str_replace("'", "''", $dumpFilePath);
                $pdo->exec("ATTACH DATABASE '{$escapedPath}' AS backup_db");
                
                $tables = $pdo->query("SELECT name FROM backup_db.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $pdo->exec("DROP TABLE IF EXISTS main.{$table}");
                    $pdo->exec("CREATE TABLE main.{$table} AS SELECT * FROM backup_db.{$table}");
                }
                $pdo->exec("DETACH DATABASE backup_db");
                return;
            } else {
                $this->executeSqlFile($pdo, $dumpFilePath);
                return;
            }
        }

        // If the dump is a binary SQLite file (.sqlite)
        if (str_ends_with($dumpFilePath, '.sqlite')) {
            // Remove stale SQLite WAL / shared memory files if present
            if (file_exists($targetDbPath . '-wal')) {
                @unlink($targetDbPath . '-wal');
            }
            if (file_exists($targetDbPath . '-shm')) {
                @unlink($targetDbPath . '-shm');
            }

            File::ensureDirectoryExists(dirname($targetDbPath));

            if (!@copy($dumpFilePath, $targetDbPath)) {
                throw new RuntimeException("Failed to overwrite SQLite database file at: {$targetDbPath}");
            }

            // Re-establish connection
            DB::reconnect('sqlite');
            return;
        }

        // If the dump is an SQL script
        $pdo = DB::connection('sqlite')->getPdo();
        $this->executeSqlFile($pdo, $dumpFilePath);
    }

    /**
     * Restore MySQL database using mysql CLI or streaming PDO execution.
     */
    protected function restoreMySql(string $dumpFilePath, string $connectionName): void
    {
        $config = config("database.connections.{$connectionName}");
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';

        // Check if mysql CLI binary is available
        if ($this->hasMysqlBinary()) {
            $passwordOption = $password !== '' ? "-p" . escapeshellarg($password) : "";
            $cmd = sprintf(
                'mysql --host=%s --port=%s --user=%s %s %s < %s 2>&1',
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($username),
                $passwordOption,
                escapeshellarg($database),
                escapeshellarg($dumpFilePath)
            );

            exec($cmd, $output, $returnCode);

            if ($returnCode === 0) {
                return;
            }

            Log::warning("mysql CLI restore returned code {$returnCode}. Falling back to PDO executor: " . implode("\n", $output));
        }

        // Fallback: Restore using PDO
        $pdo = DB::connection($connectionName)->getPdo();
        $this->executeSqlFile($pdo, $dumpFilePath);
    }

    /**
     * Restore database via generic PDO connection.
     */
    protected function restoreGenericPdo(string $dumpFilePath, string $connectionName): void
    {
        $pdo = DB::connection($connectionName)->getPdo();
        $this->executeSqlFile($pdo, $dumpFilePath);
    }

    /**
     * Execute SQL statements from file with chunked parsing.
     */
    protected function executeSqlFile(PDO $pdo, string $sqlFilePath): void
    {
        $handle = fopen($sqlFilePath, 'r');
        if (!$handle) {
            throw new RuntimeException("Unable to read SQL file: {$sqlFilePath}");
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        $query = '';
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }

            $trimmed = trim($line);

            // Skip comments and empty lines
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $query .= $line;

            // Execute when statement ends with semicolon
            if (str_ends_with($trimmed, ';')) {
                try {
                    $pdo->exec($query);
                } catch (\Throwable $e) {
                    Log::warning("Error executing restore SQL statement: " . $e->getMessage() . " Query snippet: " . substr($query, 0, 100));
                }
                $query = '';
            }
        }

        if (trim($query) !== '') {
            try {
                $pdo->exec($query);
            } catch (\Throwable $e) {
                // Ignore trailing comments or whitespace errors
            }
        }

        fclose($handle);
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }

    /**
     * Check if mysql binary is available in PATH.
     */
    protected function hasMysqlBinary(): bool
    {
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        if (in_array('exec', array_map('trim', $disabledFunctions), true)) {
            return false;
        }

        $command = PHP_OS_FAMILY === 'Windows' ? 'where mysql 2>NUL' : 'command -v mysql 2>/dev/null';
        $output = @shell_exec($command);

        return !empty($output);
    }
}
