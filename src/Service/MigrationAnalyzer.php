<?php

namespace Qd\SchemaBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

/**
 * Analyzes Doctrine migration files to extract entity change history.
 *
 * This service parses migration SQL statements to build a timeline of schema changes
 * per entity, enabling visualization of entity evolution over time.
 */
final class MigrationAnalyzer
{
    private const CACHE_KEY_PREFIX = 'qd_schema_migration_history_';

    public function __construct(
        private string $projectDir,
        private LoggerInterface $logger,
        private ?string $migrationDir = null
    ) {
        $this->migrationDir = $migrationDir ?? $projectDir . '/migrations';
    }

    /**
     * Get migration history for a specific entity.
     *
     * @param string $entityFqcn Entity fully qualified class name
     * @param string|null $tableName Table name (if different from entity name)
     * @return array<int, array{
     *   migration: string,
     *   timestamp: int,
     *   description: string,
     *   changes: array<int, array{type: string, sql: string, description: string}>
     * }>
     */
    public function getEntityHistory(string $entityFqcn, ?string $tableName = null): array
    {
        $tableName = $tableName ?? $this->extractTableName($entityFqcn);
        $migrations = $this->getAllMigrations();
        $history = [];

        foreach ($migrations as $migrationData) {
            $changes = $this->extractEntityChanges($migrationData['content'], $tableName);

            if (!empty($changes)) {
                $history[] = [
                    'migration' => $migrationData['className'],
                    'version' => $migrationData['version'],
                    'timestamp' => $migrationData['timestamp'],
                    'description' => $migrationData['description'],
                    'changes' => $changes,
                    'file' => $migrationData['file'],
                ];
            }
        }

        usort($history, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return $history;
    }

    /**
     * Get complete migration history for all entities.
     *
     * @return array<string, array> Map of table name => history
     */
    public function getAllEntitiesHistory(): array
    {
        $migrations = $this->getAllMigrations();
        $entitiesHistory = [];

        foreach ($migrations as $migrationData) {
            $allTables = $this->extractAllTables($migrationData['content']);

            foreach ($allTables as $tableName) {
                $changes = $this->extractEntityChanges($migrationData['content'], $tableName);

                if (!empty($changes)) {
                    if (!isset($entitiesHistory[$tableName])) {
                        $entitiesHistory[$tableName] = [];
                    }

                    $entitiesHistory[$tableName][] = [
                        'migration' => $migrationData['className'],
                        'version' => $migrationData['version'],
                        'timestamp' => $migrationData['timestamp'],
                        'description' => $migrationData['description'],
                        'changes' => $changes,
                    ];
                }
            }
        }

        foreach ($entitiesHistory as &$history) {
            usort($history, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        }

        return $entitiesHistory;
    }

    /**
     * Extract all migration files and parse their metadata.
     *
     * @return array<int, array{
     *   file: string,
     *   className: string,
     *   version: string,
     *   timestamp: int,
     *   description: string,
     *   content: string
     * }>
     */
    private function getAllMigrations(): array
    {
        if (!is_dir($this->migrationDir)) {
            $this->logger->warning('Migrations directory not found', ['dir' => $this->migrationDir]);
            return [];
        }

        $finder = new Finder();
        $finder->files()->in($this->migrationDir)->name('Version*.php')->sortByName();

        $migrations = [];
        foreach ($finder as $file) {
            try {
                $content = $file->getContents();
                $className = $this->extractClassName($content);
                $version = $this->extractVersion($file->getFilename());
                $timestamp = $this->extractTimestamp($version);
                $description = $this->extractDescription($content);

                $migrations[] = [
                    'file' => $file->getRealPath(),
                    'className' => $className,
                    'version' => $version,
                    'timestamp' => $timestamp,
                    'description' => $description,
                    'content' => $content,
                ];
            } catch (\Exception $e) {
                $this->logger->error('Failed to parse migration file', [
                    'file' => $file->getFilename(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $migrations;
    }

    /**
     * Extract changes for a specific table from migration content.
     *
     * @return array<int, array{type: string, sql: string, description: string}>
     */
    private function extractEntityChanges(string $content, string $tableName): array
    {
        $changes = [];

        preg_match_all('/\$this->addSql\([\'"](.+?)[\'"]\)/s', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        foreach ($matches[1] as $sql) {
            $sql = stripslashes($sql);

            if (!$this->sqlAffectsTable($sql, $tableName)) {
                continue;
            }

            $change = $this->parseSqlChange($sql, $tableName);
            if ($change) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    /**
     * Check if SQL statement affects the given table.
     */
    private function sqlAffectsTable(string $sql, string $tableName): bool
    {
        $sql = strtoupper($sql);
        $table = strtoupper($tableName);

        return (
            str_contains($sql, "TABLE $table ") ||
            str_contains($sql, "TABLE $table(") ||
            str_contains($sql, "INTO $table ") ||
            str_contains($sql, "FROM $table ") ||
            str_contains($sql, "UPDATE $table ") ||
            str_contains($sql, "ALTER TABLE $table ") ||
            str_contains($sql, "DROP TABLE $table")
        );
    }

    /**
     * Parse SQL change and return structured data.
     *
     * @return array{type: string, sql: string, description: string}|null
     */
    private function parseSqlChange(string $sql, string $tableName): ?array
    {
        $sql = trim($sql);
        $sqlUpper = strtoupper($sql);

        if (preg_match('/^CREATE TABLE/i', $sql)) {
            return [
                'type' => 'create_table',
                'sql' => $sql,
                'description' => "Created table $tableName",
            ];
        }

        if (preg_match('/^DROP TABLE/i', $sql)) {
            return [
                'type' => 'drop_table',
                'sql' => $sql,
                'description' => "Dropped table $tableName",
            ];
        }

        if (preg_match('/ALTER TABLE .+ ADD COLUMN? (\w+)/i', $sql, $matches)) {
            $columnName = $matches[1];
            $columnType = $this->extractColumnType($sql);

            return [
                'type' => 'add_column',
                'sql' => $sql,
                'description' => "Added column `$columnName`" . ($columnType ? " ($columnType)" : ''),
                'column' => $columnName,
                'columnType' => $columnType,
            ];
        }

        if (preg_match('/DROP COLUMN? (\w+)/i', $sql, $matches)) {
            $columnName = $matches[1];

            return [
                'type' => 'drop_column',
                'sql' => $sql,
                'description' => "Removed column `$columnName`",
                'column' => $columnName,
            ];
        }

        if (preg_match('/RENAME COLUMN (\w+) TO (\w+)/i', $sql, $matches)) {
            return [
                'type' => 'rename_column',
                'sql' => $sql,
                'description' => "Renamed column `{$matches[1]}` to `{$matches[2]}`",
                'oldColumn' => $matches[1],
                'newColumn' => $matches[2],
            ];
        }

        if (preg_match('/(?:MODIFY|CHANGE) COLUMN? (\w+)/i', $sql, $matches)) {
            $columnName = $matches[1];
            $columnType = $this->extractColumnType($sql);

            return [
                'type' => 'modify_column',
                'sql' => $sql,
                'description' => "Modified column `$columnName`" . ($columnType ? " to $columnType" : ''),
                'column' => $columnName,
                'columnType' => $columnType,
            ];
        }

        if (preg_match('/CREATE (?:UNIQUE )?INDEX (\w+)/i', $sql, $matches)) {
            $indexName = $matches[1];
            $isUnique = stripos($sql, 'UNIQUE') !== false;

            return [
                'type' => 'add_index',
                'sql' => $sql,
                'description' => "Added " . ($isUnique ? 'unique ' : '') . "index `$indexName`",
                'index' => $indexName,
                'unique' => $isUnique,
            ];
        }

        if (preg_match('/DROP INDEX (\w+)/i', $sql, $matches)) {
            $indexName = $matches[1];

            return [
                'type' => 'drop_index',
                'sql' => $sql,
                'description' => "Dropped index `$indexName`",
                'index' => $indexName,
            ];
        }

        if (preg_match('/ADD (?:CONSTRAINT|FOREIGN KEY)/i', $sql)) {
            return [
                'type' => 'add_constraint',
                'sql' => $sql,
                'description' => 'Added foreign key constraint',
            ];
        }

        if (preg_match('/DROP (?:CONSTRAINT|FOREIGN KEY) (\w+)/i', $sql, $matches)) {
            return [
                'type' => 'drop_constraint',
                'sql' => $sql,
                'description' => "Dropped constraint `{$matches[1]}`",
                'constraint' => $matches[1],
            ];
        }

        if (preg_match('/^ALTER TABLE/i', $sql)) {
            return [
                'type' => 'alter_table',
                'sql' => $sql,
                'description' => 'Modified table structure',
            ];
        }

        return null;
    }

    /**
     * Extract column type from SQL statement.
     */
    private function extractColumnType(string $sql): ?string
    {
        if (preg_match('/\b(VARCHAR|INT|INTEGER|TEXT|DATETIME|BOOLEAN|DECIMAL|FLOAT|DOUBLE|BLOB|CLOB|JSON)\b[^\)]*(?:\([^\)]+\))?/i', $sql, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Extract all table names mentioned in migration content.
     *
     * @return array<string>
     */
    private function extractAllTables(string $content): array
    {
        $tables = [];

        preg_match_all('/(?:CREATE|ALTER|DROP|INTO|FROM|UPDATE)\s+TABLE\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?([a-zA-Z0-9_]+)/i', $content, $matches);

        if (!empty($matches[1])) {
            $tables = array_unique($matches[1]);
        }

        return array_values($tables);
    }

    /**
     * Extract class name from migration file content.
     */
    private function extractClassName(string $content): string
    {
        if (preg_match('/final class (Version\d+)/i', $content, $matches)) {
            return $matches[1];
        }

        return 'Unknown';
    }

    /**
     * Extract version number from filename.
     */
    private function extractVersion(string $filename): string
    {
        if (preg_match('/(Version\d+)/', $filename, $matches)) {
            return $matches[1];
        }

        return $filename;
    }

    /**
     * Extract timestamp from version string (VersionYYYYMMDDHHIISS format).
     */
    private function extractTimestamp(string $version): int
    {
        if (preg_match('/Version(\d{14})/', $version, $matches)) {
            $dateString = $matches[1];

            try {
                $date = \DateTime::createFromFormat('YmdHis', $dateString);
                return $date ? $date->getTimestamp() : 0;
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse migration timestamp', [
                    'version' => $version,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return 0;
    }

    /**
     * Extract description from migration getDescription() method.
     */
    private function extractDescription(string $content): string
    {
        if (preg_match('/public function getDescription\(\):\s*string\s*\{[^}]*return\s+[\'"]([^\'"]*)[\'"];/s', $content, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Extract table name from entity FQCN.
     */
    private function extractTableName(string $entityFqcn): string
    {
        $parts = explode('\\', $entityFqcn);
        $className = end($parts);

        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className));
    }

    /**
     * Set custom migrations directory.
     */
    public function setMigrationDir(string $dir): self
    {
        $this->migrationDir = $dir;
        return $this;
    }

    /**
     * Get configured migrations directory.
     */
    public function getMigrationDir(): string
    {
        return $this->migrationDir;
    }
}
