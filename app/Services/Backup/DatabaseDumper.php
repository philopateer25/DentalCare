<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

class DatabaseDumper
{
    /**
     * Dump the active database to the specified target file path.
     *
     * @param string $targetFilePath The destination path for the dump
     * @return string The filename or path of the created dump
     * @throws \Exception
     */
    public function dump(string $targetFilePath): string
    {
        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver", 'sqlite');

        match ($driver) {
            'sqlite' => $this->dumpSqlite($targetFilePath),
            'mysql', 'mariadb' => $this->dumpMySql($targetFilePath, $connectionName),
            default => $this->dumpGenericPdo($targetFilePath, $connectionName),
        };

        return $targetFilePath;
    }

    /**
     * Safely dump an SQLite database using VACUUM INTO or atomic copy.
     */
    protected function dumpSqlite(string $targetFilePath): void
    {
        $databasePath = config('database.connections.sqlite.database');

        // SQLite 3.27+ native VACUUM INTO creates a consistent snapshot for both disk and in-memory (:memory:) databases
        try {
            $pdo = DB::connection()->getPdo();
            if (file_exists($targetFilePath)) {
                @unlink($targetFilePath);
            }
            $escapedPath = str_replace("'", "''", $targetFilePath);
            $pdo->exec("VACUUM INTO '{$escapedPath}'");

            if (file_exists($targetFilePath) && filesize($targetFilePath) > 0) {
                return;
            }
        } catch (\Throwable $e) {
            Log::warning("SQLite VACUUM INTO failed, checking fallback copy: " . $e->getMessage());
        }

        // Fallback for file-based databases if VACUUM INTO was unsupported
        if ($databasePath !== ':memory:' && file_exists($databasePath)) {
            if (!@copy($databasePath, $targetFilePath)) {
                throw new \RuntimeException("Failed to copy SQLite database file to: {$targetFilePath}");
            }
            return;
        }

        throw new \RuntimeException("Unable to snapshot SQLite database (path: {$databasePath})");
    }

    /**
     * Dump MySQL database using mysqldump if available, or pure PHP PDO streaming dumper.
     */
    protected function dumpMySql(string $targetFilePath, string $connectionName): void
    {
        $config = config("database.connections.{$connectionName}");
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';

        // Check if mysqldump binary exists and is accessible
        if ($this->hasMysqldumpBinary()) {
            $passwordOption = $password !== '' ? "-p" . escapeshellarg($password) : "";
            $cmd = sprintf(
                'mysqldump --host=%s --port=%s --user=%s %s --single-transaction --quick --routines --triggers --skip-lock-tables %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($username),
                $passwordOption,
                escapeshellarg($database),
                escapeshellarg($targetFilePath)
            );

            exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && file_exists($targetFilePath) && filesize($targetFilePath) > 0) {
                return;
            }

            Log::info("mysqldump binary execution unsuccessful (exit code {$returnCode}). Falling back to native PDO exporter.");
        }

        // Fallback to pure PHP PDO dumper
        $this->dumpMySqlViaPdo($targetFilePath, $connectionName);
    }

    /**
     * Pure PHP PDO streaming MySQL exporter with chunked row fetching.
     */
    protected function dumpMySqlViaPdo(string $targetFilePath, string $connectionName): void
    {
        $pdo = DB::connection($connectionName)->getPdo();
        $database = config("database.connections.{$connectionName}.database");

        $handle = fopen($targetFilePath, 'w');
        if (!$handle) {
            throw new \RuntimeException("Unable to open target file for writing: {$targetFilePath}");
        }

        // Header
        fwrite($handle, "-- DentalCare Clinic Database Backup\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Database: {$database}\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        // Fetch all tables
        $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // Write DROP and CREATE TABLE
            fwrite($handle, "\n-- Table structure for `{$table}`\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
            $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? null;

            if ($createSql) {
                fwrite($handle, $createSql . ";\n\n");
            }

            // Dump data in chunks to prevent memory bloat
            fwrite($handle, "-- Dumping data for `{$table}`\n");

            $selectStmt = $pdo->prepare("SELECT * FROM `{$table}`");
            $selectStmt->execute();

            $chunkSize = 500;
            $rows = [];

            while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
                $values = array_map(function ($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    if (is_numeric($value)) {
                        return $value;
                    }
                    return $pdo->quote($value);
                }, array_values($row));

                $rows[] = '(' . implode(', ', $values) . ')';

                if (count($rows) >= $chunkSize) {
                    $insertSql = "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $rows) . ";\n";
                    fwrite($handle, $insertSql);
                    $rows = [];
                }
            }

            if (!empty($rows)) {
                $insertSql = "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $rows) . ";\n";
                fwrite($handle, $insertSql);
            }

            fwrite($handle, "\n");
        }

        // Footer
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fwrite($handle, "-- Backup completed.\n");

        fclose($handle);
    }

    /**
     * Fallback PDO exporter for generic databases.
     */
    protected function dumpGenericPdo(string $targetFilePath, string $connectionName): void
    {
        $this->dumpMySqlViaPdo($targetFilePath, $connectionName);
    }

    /**
     * Check if the mysqldump binary is present on the host system.
     */
    protected function hasMysqldumpBinary(): bool
    {
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        if (in_array('exec', array_map('trim', $disabledFunctions), true)) {
            return false;
        }

        $command = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump 2>NUL' : 'command -v mysqldump 2>/dev/null';
        $output = @shell_exec($command);

        return !empty($output);
    }
}
