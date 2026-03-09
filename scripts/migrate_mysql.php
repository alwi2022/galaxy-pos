<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$mode = $argv[1] ?? null;

if (! in_array($mode, ['inspect', 'clone'], true)) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php scripts/migrate_mysql.php inspect\n");
    fwrite(STDERR, "  php scripts/migrate_mysql.php clone\n");
    fwrite(STDERR, "\nRequired environment variables:\n");
    fwrite(STDERR, "  SOURCE_DATABASE_URL\n");
    fwrite(STDERR, "Optional environment variables:\n");
    fwrite(STDERR, "  SOURCE_SSL_CA\n");
    fwrite(STDERR, "  TARGET_DATABASE_URL (required for clone)\n");
    fwrite(STDERR, "  TARGET_SSL_CA\n");
    fwrite(STDERR, "  COPY_BATCH_SIZE (default: 500)\n");
    exit(1);
}

$sourceUrl = getenv('SOURCE_DATABASE_URL') ?: '';
$targetUrl = getenv('TARGET_DATABASE_URL') ?: '';
$sourceSslCa = getenv('SOURCE_SSL_CA') ?: null;
$targetSslCa = getenv('TARGET_SSL_CA') ?: null;
$batchSize = max(1, (int) (getenv('COPY_BATCH_SIZE') ?: 500));

if ($sourceUrl === '') {
    fwrite(STDERR, "SOURCE_DATABASE_URL is required.\n");
    exit(1);
}

if ($mode === 'clone' && $targetUrl === '') {
    fwrite(STDERR, "TARGET_DATABASE_URL is required for clone mode.\n");
    exit(1);
}

function buildConnection(string $url, ?string $sslCa): PDO
{
    $parts = parse_url($url);

    if ($parts === false) {
        throw new RuntimeException('Invalid database URL.');
    }

    $host = $parts['host'] ?? null;
    $port = $parts['port'] ?? 3306;
    $database = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
    $username = isset($parts['user']) ? rawurldecode($parts['user']) : '';
    $password = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';

    if (! $host || $database === '') {
        throw new RuntimeException('Database URL must include host and database name.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 15,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ];

    if ($sslCa) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    }

    return new PDO($dsn, $username, $password, $options);
}

function getTables(PDO $pdo): array
{
    return $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
}

function getTableNames(PDO $pdo): array
{
    return array_map(
        static fn (array $row): string => (string) $row[0],
        getTables($pdo)
    );
}

function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function inspectDatabase(PDO $pdo): void
{
    $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT table_name, table_rows, ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
         FROM information_schema.tables
         WHERE table_schema = :schema
         ORDER BY (data_length + index_length) DESC, table_name ASC'
    );
    $stmt->execute(['schema' => $databaseName]);
    $rows = $stmt->fetchAll();

    echo "Database: {$databaseName}\n";
    echo "Tables:\n";

    $totalSize = 0.0;

    foreach ($rows as $row) {
        $size = (float) $row['size_mb'];
        $totalSize += $size;
        printf(
            "  - %-30s rows=%-10s size=%s MB\n",
            $row['table_name'],
            (string) $row['table_rows'],
            number_format($size, 2, '.', '')
        );
    }

    echo 'Total size: ' . number_format($totalSize, 2, '.', '') . " MB\n";
}

function createTargetSchema(PDO $source, PDO $target): array
{
    $createdTables = [];

    $target->exec('SET FOREIGN_KEY_CHECKS=0');

    foreach (getTableNames($source) as $table) {
        $create = $source->query('SHOW CREATE TABLE ' . quoteIdentifier($table))->fetch(PDO::FETCH_ASSOC);
        $sql = $create['Create Table'] ?? array_values($create)[1] ?? null;

        if (! is_string($sql)) {
            throw new RuntimeException("Unable to fetch CREATE TABLE statement for {$table}.");
        }

        $target->exec('DROP TABLE IF EXISTS ' . quoteIdentifier($table));
        $target->exec($sql);
        $createdTables[] = $table;
    }

    $target->exec('SET FOREIGN_KEY_CHECKS=1');

    return $createdTables;
}

function copyTableRows(PDO $source, PDO $target, string $table, int $batchSize): void
{
    $count = (int) $source->query('SELECT COUNT(*) FROM ' . quoteIdentifier($table))->fetchColumn();
    echo "Copying {$table} ({$count} rows)\n";

    if ($count === 0) {
        return;
    }

    $columns = $source->query('SHOW COLUMNS FROM ' . quoteIdentifier($table))->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_map(static fn (array $column): string => $column['Field'], $columns);

    $quotedColumns = array_map('quoteIdentifier', $columnNames);
    $placeholders = '(' . implode(', ', array_fill(0, count($columnNames), '?')) . ')';
    $insertSql = sprintf(
        'INSERT INTO %s (%s) VALUES %s',
        quoteIdentifier($table),
        implode(', ', $quotedColumns),
        $placeholders
    );

    $insert = $target->prepare($insertSql);
    $offset = 0;

    while ($offset < $count) {
        $selectSql = sprintf(
            'SELECT * FROM %s LIMIT %d OFFSET %d',
            quoteIdentifier($table),
            $batchSize,
            $offset
        );

        $rows = $source->query($selectSql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $insert->execute(array_map(
                static fn (string $column) => $row[$column],
                $columnNames
            ));
        }

        $offset += count($rows);
        echo "  copied {$offset}/{$count}\n";

        if ($rows === []) {
            break;
        }
    }
}

try {
    $source = buildConnection($sourceUrl, $sourceSslCa);

    if ($mode === 'inspect') {
        inspectDatabase($source);
        exit(0);
    }

    $target = buildConnection($targetUrl, $targetSslCa);

    inspectDatabase($source);
    echo "Creating target schema...\n";
    $tables = createTargetSchema($source, $target);

    $target->beginTransaction();
    $target->exec('SET FOREIGN_KEY_CHECKS=0');

    foreach ($tables as $table) {
        copyTableRows($source, $target, $table, $batchSize);
    }

    $target->exec('SET FOREIGN_KEY_CHECKS=1');
    $target->commit();

    echo "Clone completed successfully.\n";
} catch (Throwable $exception) {
    if (isset($target) && $target instanceof PDO && $target->inTransaction()) {
        $target->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
