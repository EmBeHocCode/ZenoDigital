<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

$config = require BASE_PATH . '/config/config.php';
require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

$fix = in_array('--fix', $argv, true);
$limit = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int) substr($arg, 8));
    }
}

$pdo = Database::getConnection($config['db']);
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

function u(int $codepoint): string
{
    return json_decode('"\\u' . str_pad(dechex($codepoint), 4, '0', STR_PAD_LEFT) . '"');
}

function quote_ident(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function bad_markers(): array
{
    return [
        u(0x2500), u(0x2502), u(0x2510), u(0x2514), u(0x251c), u(0x2524),
        u(0x252c), u(0x2534), u(0x253c), u(0x2550), u(0x2551), u(0x2554),
        u(0x2557), u(0x255a), u(0x255d), u(0x2560), u(0x2563), u(0x2566),
        u(0x2569), u(0x256c), u(0x2591), u(0x2592), u(0x2593),
    ];
}

function search_markers(): array
{
    return array_merge(bad_markers(), ['Ã', 'Ä', 'Æ', 'áº', 'á»']);
}

function bad_score(string $text): int
{
    $score = 0;
    foreach (bad_markers() as $marker) {
        $score += substr_count($text, $marker) * 5;
    }

    foreach (['Ã', 'Ä', 'Æ', 'â', 'áº', 'á»'] as $marker) {
        $score += substr_count($text, $marker);
    }

    return $score;
}

function looks_bad(string $text): bool
{
    if ($text === '') {
        return false;
    }

    if (bad_score($text) >= 5) {
        return true;
    }

    return str_contains($text, 'ß' . u(0x2557))
        || str_contains($text, 'ß' . u(0x00ba))
        || substr_count($text, 'Ã') >= 2
        || str_contains($text, 'áº')
        || str_contains($text, 'á»');
}

function repair_cp850_mojibake(string $text): string
{
    if (!looks_bad($text)) {
        return $text;
    }

    $best = $text;
    foreach (['CP850', 'Windows-1252'] as $encoding) {
        $candidate = @iconv('UTF-8', $encoding . '//IGNORE', $text);
        if (!is_string($candidate) || $candidate === '' || !preg_match('//u', $candidate)) {
            continue;
        }

        if (bad_score($candidate) < bad_score($best)) {
            $best = $candidate;
        }
    }

    return $best;
}

function preview(string $text): string
{
    $text = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $text);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 140, 'UTF-8');
    }

    return substr($text, 0, 140);
}

$columnStmt = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json')
    ORDER BY TABLE_NAME, ORDINAL_POSITION
");
$columns = $columnStmt->fetchAll(PDO::FETCH_ASSOC);

$pkStmt = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'PRIMARY'
    ORDER BY TABLE_NAME, ORDINAL_POSITION
");
$primaryKeys = [];
foreach ($pkStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $primaryKeys[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
}

$markers = search_markers();
$samples = [];
$updated = 0;
$matched = 0;
$skipped = 0;
$columnsWithHits = [];

foreach ($columns as $column) {
    $tableName = $column['TABLE_NAME'];
    $columnName = $column['COLUMN_NAME'];
    $pkColumns = $primaryKeys[$tableName] ?? [];

    $whereParts = array_fill(0, count($markers), quote_ident($columnName) . ' COLLATE utf8mb4_bin LIKE ?');
    $selectFields = array_merge($pkColumns, [$columnName]);
    $selectFields = array_values(array_unique($selectFields));
    $sql = 'SELECT ' . implode(', ', array_map('quote_ident', $selectFields))
        . ' FROM ' . quote_ident($tableName)
        . ' WHERE ' . quote_ident($columnName) . ' IS NOT NULL AND (' . implode(' OR ', $whereParts) . ')';

    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_map(static fn (string $marker): string => '%' . $marker . '%', $markers));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        continue;
    }

    $columnsWithHits[] = $tableName . '.' . $columnName . ' = ' . count($rows);

    foreach ($rows as $row) {
        $old = (string) $row[$columnName];
        if (!looks_bad($old)) {
            continue;
        }

        $new = repair_cp850_mojibake($old);
        if ($new === $old) {
            $skipped++;
            continue;
        }

        $matched++;
        if (count($samples) < 12) {
            $samples[] = [
                'cell' => $tableName . '.' . $columnName,
                'old' => preview($old),
                'new' => preview($new),
            ];
        }

        if (!$fix) {
            continue;
        }

        if (!$pkColumns) {
            $skipped++;
            continue;
        }

        $pkWhere = implode(' AND ', array_map(static fn (string $pk): string => quote_ident($pk) . ' <=> ?', $pkColumns));
        $update = $pdo->prepare('UPDATE ' . quote_ident($tableName) . ' SET ' . quote_ident($columnName) . ' = ? WHERE ' . $pkWhere . ' LIMIT 1');
        $values = [$new];
        foreach ($pkColumns as $pk) {
            $values[] = $row[$pk];
        }
        $update->execute($values);
        $updated += $update->rowCount();
    }
}

echo $fix ? "MODE=FIX\n" : "MODE=DRY_RUN\n";
echo 'COLUMNS_WITH_HITS=' . count($columnsWithHits) . "\n";
foreach ($columnsWithHits as $hit) {
    echo ' - ' . $hit . "\n";
}
echo 'MATCHED_CELLS=' . $matched . "\n";
echo 'UPDATED_CELLS=' . $updated . "\n";
echo 'SKIPPED_CELLS=' . $skipped . "\n";
if ($samples) {
    echo "SAMPLES:\n";
    foreach ($samples as $sample) {
        echo '[' . $sample['cell'] . "]\n";
        echo 'OLD: ' . $sample['old'] . "\n";
        echo 'NEW: ' . $sample['new'] . "\n";
    }
}
