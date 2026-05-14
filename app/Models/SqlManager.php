<?php

namespace App\Models;

use App\Core\Model;

class SqlManager extends Model
{
    private ?array $tablesCache = null;
    private array $columnsCache = [];
    private array $uniqueIndexesCache = [];

    public function databaseName(): string
    {
        $stmt = $this->db->query('SELECT DATABASE() AS name');
        $row = $stmt->fetch();

        return trim((string) ($row['name'] ?? ''));
    }

    public function listTables(): array
    {
        if (is_array($this->tablesCache)) {
            return $this->tablesCache;
        }

        $stmt = $this->db->query('SELECT
                TABLE_NAME AS name,
                ENGINE AS engine,
                TABLE_ROWS AS estimated_rows,
                TABLE_COLLATION AS collation,
                AUTO_INCREMENT AS auto_increment,
                CREATE_TIME AS created_at,
                UPDATE_TIME AS updated_at
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = "BASE TABLE"
            ORDER BY TABLE_NAME ASC');

        $this->tablesCache = $stmt->fetchAll() ?: [];
        foreach ($this->tablesCache as &$table) {
            $table['estimated_rows'] = (int) ($table['estimated_rows'] ?? 0);
            $table['auto_increment'] = isset($table['auto_increment']) ? (int) $table['auto_increment'] : null;
        }

        return $this->tablesCache;
    }

    public function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        foreach ($this->listTables() as $meta) {
            if (($meta['name'] ?? '') === $table) {
                return true;
            }
        }

        return false;
    }

    public function browseTable(
        string $table,
        int $page = 1,
        int $perPage = 25,
        string $search = '',
        string $sortColumn = '',
        string $sortDirection = 'asc'
    ): array {
        $this->assertValidTable($table);

        $columns = $this->getTableColumns($table);
        $columnMap = $this->getTableColumnMap($table);
        $primaryKeys = $this->getPrimaryKeyColumns($table);
        $rowIdentity = $this->getRowIdentityDescriptor($table);
        $sortDirection = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
        $sortColumn = isset($columnMap[$sortColumn]) ? $sortColumn : (($rowIdentity['columns'][0] ?? '') ?: ($primaryKeys[0] ?? ($columns[0]['name'] ?? '')));
        $perPage = max(10, min($perPage, 100));

        $search = trim($search);
        $whereClauses = [];
        $params = [];
        $searchableColumns = array_filter($columns, fn(array $column): bool => !$this->isBinaryType((string) ($column['data_type'] ?? '')));

        if ($search !== '' && $searchableColumns) {
            foreach (array_values($searchableColumns) as $index => $column) {
                $paramName = ':search_' . $index;
                $whereClauses[] = 'CAST(' . $this->quoteIdentifier((string) $column['name']) . ' AS CHAR(255)) LIKE ' . $paramName;
                $params[$paramName] = '%' . $search . '%';
            }
        }

        $whereSql = $whereClauses ? ' WHERE ' . implode(' OR ', $whereClauses) : '';
        $tableSql = $this->quoteIdentifier($table);

        $countStmt = $this->db->prepare('SELECT COUNT(*) AS total FROM ' . $tableSql . $whereSql);
        foreach ($params as $paramName => $value) {
            $countStmt->bindValue($paramName, $value, \PDO::PARAM_STR);
        }
        $countStmt->execute();
        $countRow = $countStmt->fetch();
        $total = (int) ($countRow['total'] ?? 0);

        $pagination = paginate_meta($total, $page, $perPage);

        $dataSql = 'SELECT * FROM ' . $tableSql . $whereSql;
        if ($sortColumn !== '') {
            $dataSql .= ' ORDER BY ' . $this->quoteIdentifier($sortColumn) . ' ' . strtoupper($sortDirection);
        }
        $dataSql .= ' LIMIT :limit OFFSET :offset';

        $dataStmt = $this->db->prepare($dataSql);
        foreach ($params as $paramName => $value) {
            $dataStmt->bindValue($paramName, $value, \PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':limit', (int) $pagination['per_page'], \PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', (int) $pagination['offset'], \PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll() ?: [];

        return [
            'database' => $this->databaseName(),
            'table' => $table,
            'overview' => $this->getTableOverview($table, $total),
            'columns' => $columns,
            'primary_keys' => $primaryKeys,
            'indexes' => $this->getTableIndexes($table),
            'foreign_keys' => $this->getTableForeignKeys($table),
            'triggers' => $this->getTableTriggers($table),
            'row_identity_keys' => $rowIdentity['columns'],
            'row_identity_source' => $rowIdentity['source'],
            'row_actions' => [
                'supported' => $rowIdentity['supported'],
                'source' => $rowIdentity['source'],
                'label' => $rowIdentity['label'],
                'reason' => $rowIdentity['reason'],
            ],
            'rows' => $rows,
            'pagination' => $pagination,
            'filters' => [
                'search' => $search,
                'sort_column' => $sortColumn,
                'sort_direction' => $sortDirection,
            ],
        ];
    }

    public function runReadOnlyQuery(string $sql): array
    {
        $sql = $this->sanitizeQuery($sql);
        $queryKind = $this->detectAllowedQueryKind($sql);
        if ($queryKind === null) {
            throw new \InvalidArgumentException('Chỉ cho phép SELECT, SHOW, DESCRIBE hoặc EXPLAIN trong SQL Console.');
        }

        $startedAt = microtime(true);
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $columns = [];
        $columnCount = $stmt->columnCount();
        for ($index = 0; $index < $columnCount; $index++) {
            $meta = $stmt->getColumnMeta($index) ?: [];
            $columns[] = [
                'name' => (string) ($meta['name'] ?? ('column_' . ($index + 1))),
                'native_type' => (string) ($meta['native_type'] ?? ''),
                'table' => (string) ($meta['table'] ?? ''),
            ];
        }

        $rows = [];
        $truncated = false;
        while (($row = $stmt->fetch()) !== false) {
            $rows[] = $row;
            if (count($rows) > 200) {
                $truncated = true;
                array_pop($rows);
                break;
            }
        }

        return [
            'query' => $sql,
            'query_kind' => $queryKind,
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => count($rows),
            'truncated' => $truncated,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    public function exportSqlDump(array $options = []): array
    {
        $scope = strtolower(trim((string) ($options['scope'] ?? 'database')));
        $table = trim((string) ($options['table'] ?? ''));
        $includeSchema = array_key_exists('include_schema', $options) ? (bool) $options['include_schema'] : true;
        $includeData = array_key_exists('include_data', $options) ? (bool) $options['include_data'] : true;
        $addDrop = array_key_exists('add_drop', $options) ? (bool) $options['add_drop'] : true;
        $insertMode = strtoupper(trim((string) ($options['insert_mode'] ?? 'INSERT')));
        $insertMode = $insertMode === 'REPLACE' ? 'REPLACE INTO' : 'INSERT INTO';

        if (!$includeSchema && !$includeData) {
            throw new \InvalidArgumentException('Export SQL can include schema, data, or both.');
        }

        if ($scope === 'table') {
            $this->assertValidTable($table);
            $tables = [$table];
        } else {
            $tables = array_values(array_map(
                static fn(array $meta): string => (string) ($meta['name'] ?? ''),
                $this->listTables()
            ));
            $tables = array_values(array_filter($tables, static fn(string $name): bool => $name !== ''));
        }

        if ($tables === []) {
            throw new \RuntimeException('Database does not have any table to export.');
        }

        $tables = $this->sortTablesByForeignKeys($tables);
        $database = $this->databaseName();
        $lines = [];
        $totalRows = 0;

        $lines[] = '-- ZenoxDigital SQL dump';
        $lines[] = '-- Database: `' . str_replace('`', '``', $database) . '`';
        $lines[] = '-- Generated at: ' . date('Y-m-d H:i:s');
        $lines[] = '-- Compatible with phpMyAdmin import';
        $lines[] = '';
        $lines[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
        $lines[] = 'START TRANSACTION;';
        $lines[] = 'SET time_zone = "+00:00";';
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $lines[] = '';

        if ($includeSchema && $addDrop) {
            $lines[] = '-- Drop existing tables first to avoid phpMyAdmin #1050 errors.';
            foreach (array_reverse($tables) as $dropTable) {
                $lines[] = 'DROP TABLE IF EXISTS ' . $this->quoteIdentifier($dropTable) . ';';
            }
            $lines[] = '';
        }

        if ($includeSchema) {
            foreach ($tables as $createTable) {
                $lines[] = '--';
                $lines[] = '-- Table structure for table ' . $this->quoteIdentifier($createTable);
                $lines[] = '--';
                $lines[] = '';
                $lines[] = $this->createTableSql($createTable) . ';';
                $lines[] = '';
            }
        }

        if ($includeData) {
            foreach ($tables as $dataTable) {
                $lines[] = '--';
                $lines[] = '-- Data for table ' . $this->quoteIdentifier($dataTable);
                $lines[] = '--';
                $lines[] = '';

                $dump = $this->dumpTableDataSql($dataTable, $insertMode);
                $totalRows += $dump['row_count'];
                if ($dump['sql'] !== '') {
                    $lines[] = $dump['sql'];
                } else {
                    $lines[] = '-- No rows.';
                }
                $lines[] = '';
            }
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $lines[] = 'COMMIT;';
        $lines[] = '';

        return [
            'database' => $database,
            'tables' => $tables,
            'table_count' => count($tables),
            'row_count' => $totalRows,
            'include_schema' => $includeSchema,
            'include_data' => $includeData,
            'add_drop' => $addDrop,
            'insert_mode' => $insertMode,
            'sql' => implode("\n", $lines),
        ];
    }

    public function updateRow(string $table, array $rowKey, array $values): int
    {
        $this->assertValidTable($table);
        $columns = $this->getTableColumnMap($table);
        $rowLocatorColumns = $this->resolveRowLocatorColumns($table, $rowKey);

        $setClauses = [];
        $bindMap = [];
        $index = 0;

        foreach ($values as $columnName => $payload) {
            if (!isset($columns[$columnName])) {
                continue;
            }

            $columnMeta = $columns[$columnName];
            if ($this->isGeneratedColumn($columnMeta)) {
                continue;
            }

            $paramName = ':set_' . $index++;
            $setClauses[] = $this->quoteIdentifier($columnName) . ' = ' . $paramName;
            $bindMap[$paramName] = $this->normalizeValuePayload($columnMeta, $payload);
        }

        if (!$setClauses) {
            throw new \InvalidArgumentException('Không có trường hợp lệ để cập nhật.');
        }

        $whereClauses = [];
        foreach ($rowLocatorColumns as $locatorColumn) {
            if (!array_key_exists($locatorColumn, $rowKey)) {
                throw new \InvalidArgumentException('Thiếu row key để xác định bản ghi cần cập nhật.');
            }

            $paramName = ':where_' . $locatorColumn;
            $whereClauses[] = $this->quoteIdentifier($locatorColumn) . ' = ' . $paramName;
            $bindMap[$paramName] = $rowKey[$locatorColumn];
        }

        $sql = 'UPDATE ' . $this->quoteIdentifier($table)
            . ' SET ' . implode(', ', $setClauses)
            . ' WHERE ' . implode(' AND ', $whereClauses)
            . ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        foreach ($bindMap as $paramName => $value) {
            $this->bindValue($stmt, $paramName, $value);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function commitBatchOperations(array $operations): array
    {
        if ($operations === []) {
            return [
                'processed' => 0,
                'counts' => [
                    'insert' => 0,
                    'update' => 0,
                    'delete' => 0,
                ],
            ];
        }

        if (count($operations) > 300) {
            throw new \InvalidArgumentException('Một lần commit chỉ hỗ trợ tối đa 300 thao tác.');
        }

        $summary = [
            'processed' => 0,
            'counts' => [
                'insert' => 0,
                'update' => 0,
                'delete' => 0,
            ],
        ];

        $this->db->beginTransaction();

        try {
            foreach ($operations as $index => $operation) {
                if (!is_array($operation)) {
                    throw new \InvalidArgumentException('Dữ liệu transaction không hợp lệ ở thao tác #' . ($index + 1) . '.');
                }

                $type = strtolower(trim((string) ($operation['type'] ?? '')));
                $table = trim((string) ($operation['table'] ?? ''));

                if ($table === '') {
                    throw new \InvalidArgumentException('Thiếu tên bảng ở thao tác #' . ($index + 1) . '.');
                }

                if ($type === 'insert') {
                    $values = isset($operation['values']) && is_array($operation['values']) ? $operation['values'] : [];
                    $this->insertRow($table, $values);
                    $summary['processed']++;
                    $summary['counts']['insert']++;
                    continue;
                }

                if ($type === 'update') {
                    $rowKey = isset($operation['row_key']) && is_array($operation['row_key']) ? $operation['row_key'] : [];
                    $values = isset($operation['values']) && is_array($operation['values']) ? $operation['values'] : [];
                    $this->updateRow($table, $rowKey, $values);
                    $summary['processed']++;
                    $summary['counts']['update']++;
                    continue;
                }

                if ($type === 'delete') {
                    $rowKey = isset($operation['row_key']) && is_array($operation['row_key']) ? $operation['row_key'] : [];
                    $this->deleteRow($table, $rowKey);
                    $summary['processed']++;
                    $summary['counts']['delete']++;
                    continue;
                }

                throw new \InvalidArgumentException('Loại thao tác không được hỗ trợ: ' . $type . '.');
            }

            $this->db->commit();

            return $summary;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function insertRow(string $table, array $values): string
    {
        $this->assertValidTable($table);
        $columns = $this->getTableColumnMap($table);

        $insertColumns = [];
        $placeholders = [];
        $bindMap = [];
        $index = 0;

        foreach ($values as $columnName => $payload) {
            if (!isset($columns[$columnName])) {
                continue;
            }

            $columnMeta = $columns[$columnName];
            if ($this->isGeneratedColumn($columnMeta)) {
                continue;
            }

            if ($this->shouldSkipOnInsert($columnMeta, $payload)) {
                continue;
            }

            $paramName = ':insert_' . $index++;
            $insertColumns[] = $this->quoteIdentifier($columnName);
            $placeholders[] = $paramName;
            $bindMap[$paramName] = $this->normalizeValuePayload($columnMeta, $payload);
        }

        if (!$insertColumns) {
            throw new \InvalidArgumentException('Không có dữ liệu hợp lệ để thêm bản ghi mới.');
        }

        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table)
            . ' (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = $this->db->prepare($sql);
        foreach ($bindMap as $paramName => $value) {
            $this->bindValue($stmt, $paramName, $value);
        }
        $stmt->execute();

        return (string) $this->db->lastInsertId();
    }

    public function deleteRow(string $table, array $rowKey): int
    {
        $this->assertValidTable($table);
        $rowLocatorColumns = $this->resolveRowLocatorColumns($table, $rowKey);

        $whereClauses = [];
        $bindMap = [];
        foreach ($rowLocatorColumns as $locatorColumn) {
            if (!array_key_exists($locatorColumn, $rowKey)) {
                throw new \InvalidArgumentException('Thiếu row key để xóa bản ghi.');
            }

            $paramName = ':where_' . $locatorColumn;
            $whereClauses[] = $this->quoteIdentifier($locatorColumn) . ' = ' . $paramName;
            $bindMap[$paramName] = $rowKey[$locatorColumn];
        }

        $sql = 'DELETE FROM ' . $this->quoteIdentifier($table)
            . ' WHERE ' . implode(' AND ', $whereClauses)
            . ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        foreach ($bindMap as $paramName => $value) {
            $this->bindValue($stmt, $paramName, $value);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function getTableColumns(string $table): array
    {
        $this->assertValidTable($table);

        if (isset($this->columnsCache[$table])) {
            return $this->columnsCache[$table];
        }

        $stmt = $this->db->prepare('SELECT
                COLUMN_NAME AS name,
                DATA_TYPE AS data_type,
                COLUMN_TYPE AS column_type,
                IS_NULLABLE AS is_nullable,
                COLUMN_DEFAULT AS default_value,
                COLUMN_KEY AS column_key,
                EXTRA AS extra,
                COLUMN_COMMENT AS column_comment,
                CHARACTER_MAXIMUM_LENGTH AS max_length,
                NUMERIC_PRECISION AS numeric_precision,
                NUMERIC_SCALE AS numeric_scale
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
            ORDER BY ORDINAL_POSITION ASC');
        $stmt->execute(['table' => $table]);

        $columns = $stmt->fetchAll() ?: [];
        foreach ($columns as &$column) {
            $column['is_nullable'] = (string) ($column['is_nullable'] ?? '') === 'YES';
        }

        $this->columnsCache[$table] = $columns;

        return $columns;
    }

    public function getPrimaryKeyColumns(string $table): array
    {
        $columns = $this->getTableColumns($table);

        return array_values(array_map(
            static fn(array $column): string => (string) $column['name'],
            array_filter($columns, static fn(array $column): bool => (string) ($column['column_key'] ?? '') === 'PRI')
        ));
    }

    public function getUniqueIndexes(string $table): array
    {
        $this->assertValidTable($table);

        if (isset($this->uniqueIndexesCache[$table])) {
            return $this->uniqueIndexesCache[$table];
        }

        $stmt = $this->db->prepare('SELECT
                INDEX_NAME AS index_name,
                COLUMN_NAME AS column_name,
                SEQ_IN_INDEX AS seq_in_index
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND NON_UNIQUE = 0
              AND INDEX_NAME <> "PRIMARY"
            ORDER BY INDEX_NAME ASC, SEQ_IN_INDEX ASC');
        $stmt->execute(['table' => $table]);
        $rows = $stmt->fetchAll() ?: [];

        $indexes = [];
        foreach ($rows as $row) {
            $indexName = (string) ($row['index_name'] ?? '');
            $columnName = (string) ($row['column_name'] ?? '');
            if ($indexName === '' || $columnName === '') {
                continue;
            }

            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [];
            }

            $indexes[$indexName][] = $columnName;
        }

        $this->uniqueIndexesCache[$table] = $indexes;

        return $this->uniqueIndexesCache[$table];
    }

    private function getTableOverview(string $table, int $exactRows): array
    {
        $stmt = $this->db->prepare('SELECT
                TABLE_NAME AS name,
                ENGINE AS engine,
                TABLE_COLLATION AS collation,
                AUTO_INCREMENT AS auto_increment,
                CREATE_TIME AS created_at,
                UPDATE_TIME AS updated_at
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
            LIMIT 1');
        $stmt->execute(['table' => $table]);
        $overview = $stmt->fetch() ?: [];
        $overview['exact_rows'] = $exactRows;

        return $overview;
    }

    private function getRowIdentityDescriptor(string $table): array
    {
        $primaryKeys = $this->getPrimaryKeyColumns($table);
        if ($primaryKeys !== []) {
            return [
                'supported' => true,
                'source' => 'primary',
                'columns' => $primaryKeys,
                'label' => 'Primary key',
                'reason' => '',
            ];
        }

        foreach ($this->getUniqueIndexes($table) as $indexName => $columns) {
            if ($columns === []) {
                continue;
            }

            return [
                'supported' => true,
                'source' => 'unique',
                'columns' => array_values($columns),
                'label' => 'Unique index: ' . $indexName,
                'reason' => '',
            ];
        }

        return [
            'supported' => false,
            'source' => 'none',
            'columns' => [],
            'label' => 'Không có row key',
            'reason' => 'Bảng này không có primary key hoặc unique index nên SQL Manager không thể sửa/xóa trực tiếp an toàn.',
        ];
    }

    private function getTableColumnMap(string $table): array
    {
        $map = [];
        foreach ($this->getTableColumns($table) as $column) {
            $map[(string) $column['name']] = $column;
        }

        return $map;
    }

    private function getTableIndexes(string $table): array
    {
        $this->assertValidTable($table);

        $stmt = $this->db->prepare('SELECT
                INDEX_NAME AS index_name,
                COLUMN_NAME AS column_name,
                SEQ_IN_INDEX AS seq_in_index,
                NON_UNIQUE AS non_unique,
                INDEX_TYPE AS index_type,
                COLLATION AS collation,
                CARDINALITY AS cardinality
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
            ORDER BY INDEX_NAME = "PRIMARY" DESC, INDEX_NAME ASC, SEQ_IN_INDEX ASC');
        $stmt->execute(['table' => $table]);

        $indexes = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $name = (string) ($row['index_name'] ?? '');
            if ($name === '') {
                continue;
            }

            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'name' => $name,
                    'columns' => [],
                    'is_unique' => ((int) ($row['non_unique'] ?? 1)) === 0,
                    'type' => (string) ($row['index_type'] ?? ''),
                    'cardinality' => $row['cardinality'] ?? null,
                ];
            }

            $indexes[$name]['columns'][] = [
                'name' => (string) ($row['column_name'] ?? ''),
                'sequence' => (int) ($row['seq_in_index'] ?? 0),
                'collation' => (string) ($row['collation'] ?? ''),
            ];
        }

        return array_values($indexes);
    }

    private function getTableForeignKeys(string $table): array
    {
        $this->assertValidTable($table);

        $stmt = $this->db->prepare('SELECT
                kcu.CONSTRAINT_NAME AS constraint_name,
                kcu.COLUMN_NAME AS column_name,
                kcu.REFERENCED_TABLE_NAME AS referenced_table,
                kcu.REFERENCED_COLUMN_NAME AS referenced_column,
                rc.UPDATE_RULE AS update_rule,
                rc.DELETE_RULE AS delete_rule
            FROM information_schema.KEY_COLUMN_USAGE kcu
            LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
              ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
             AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
             AND rc.TABLE_NAME = kcu.TABLE_NAME
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.TABLE_NAME = :table
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY kcu.CONSTRAINT_NAME ASC, kcu.ORDINAL_POSITION ASC');
        $stmt->execute(['table' => $table]);

        return $stmt->fetchAll() ?: [];
    }

    private function getTableTriggers(string $table): array
    {
        $this->assertValidTable($table);

        $stmt = $this->db->prepare('SELECT
                TRIGGER_NAME AS name,
                EVENT_MANIPULATION AS event,
                ACTION_TIMING AS timing,
                ACTION_STATEMENT AS statement,
                CREATED AS created_at
            FROM information_schema.TRIGGERS
            WHERE TRIGGER_SCHEMA = DATABASE()
              AND EVENT_OBJECT_TABLE = :table
            ORDER BY TRIGGER_NAME ASC');
        $stmt->execute(['table' => $table]);

        return $stmt->fetchAll() ?: [];
    }

    private function createTableSql(string $table): string
    {
        $this->assertValidTable($table);

        $stmt = $this->db->query('SHOW CREATE TABLE ' . $this->quoteIdentifier($table));
        $row = $stmt->fetch() ?: [];
        $createSql = (string) ($row['Create Table'] ?? ($row[1] ?? ''));

        if ($createSql === '') {
            throw new \RuntimeException('Cannot read CREATE TABLE statement for ' . $table . '.');
        }

        return rtrim($createSql, " \t\n\r;");
    }

    private function dumpTableDataSql(string $table, string $insertMode): array
    {
        $this->assertValidTable($table);

        $columns = array_values(array_filter(
            $this->getTableColumns($table),
            fn(array $column): bool => !$this->isGeneratedColumn($column)
        ));

        if ($columns === []) {
            return [
                'sql' => '',
                'row_count' => 0,
            ];
        }

        $columnNames = array_map(static fn(array $column): string => (string) $column['name'], $columns);
        $columnSql = implode(', ', array_map(fn(string $name): string => $this->quoteIdentifier($name), $columnNames));
        $selectSql = 'SELECT ' . $columnSql . ' FROM ' . $this->quoteIdentifier($table);

        $primaryKeys = $this->getPrimaryKeyColumns($table);
        if ($primaryKeys !== []) {
            $selectSql .= ' ORDER BY ' . implode(', ', array_map(fn(string $name): string => $this->quoteIdentifier($name), $primaryKeys));
        }

        $stmt = $this->db->query($selectSql);
        $chunks = [];
        $batchRows = [];
        $rowCount = 0;
        $batchSize = 80;

        while (($row = $stmt->fetch()) !== false) {
            $values = [];
            foreach ($columns as $column) {
                $name = (string) $column['name'];
                $values[] = $this->sqlLiteral($row[$name] ?? null, $column);
            }

            $batchRows[] = '(' . implode(', ', $values) . ')';
            $rowCount++;

            if (count($batchRows) >= $batchSize) {
                $chunks[] = $this->buildInsertStatement($insertMode, $table, $columnSql, $batchRows);
                $batchRows = [];
            }
        }

        if ($batchRows !== []) {
            $chunks[] = $this->buildInsertStatement($insertMode, $table, $columnSql, $batchRows);
        }

        return [
            'sql' => implode("\n", $chunks),
            'row_count' => $rowCount,
        ];
    }

    private function buildInsertStatement(string $insertMode, string $table, string $columnSql, array $rows): string
    {
        return $insertMode . ' ' . $this->quoteIdentifier($table)
            . ' (' . $columnSql . ") VALUES\n"
            . implode(",\n", $rows)
            . ';';
    }

    private function sqlLiteral($value, array $columnMeta): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($this->isBinaryType((string) ($columnMeta['data_type'] ?? ''))) {
            return "X'" . bin2hex((string) $value) . "'";
        }

        $quoted = $this->db->quote((string) $value);
        if ($quoted !== false) {
            return $quoted;
        }

        return "'" . str_replace(
            ["\\", "\0", "\n", "\r", "'", "\x1a"],
            ["\\\\", "\\0", "\\n", "\\r", "\\'", "\\Z"],
            (string) $value
        ) . "'";
    }

    private function sortTablesByForeignKeys(array $tables): array
    {
        $tables = array_values(array_unique(array_filter($tables, static fn(string $table): bool => $table !== '')));
        if (count($tables) <= 1) {
            return $tables;
        }

        $tableSet = array_fill_keys($tables, true);
        $dependencies = array_fill_keys($tables, []);

        $stmt = $this->db->query('SELECT
                TABLE_NAME AS child_table,
                REFERENCED_TABLE_NAME AS parent_table
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL');

        foreach ($stmt->fetchAll() ?: [] as $row) {
            $child = (string) ($row['child_table'] ?? '');
            $parent = (string) ($row['parent_table'] ?? '');
            if ($child === '' || $parent === '' || $child === $parent || !isset($tableSet[$child], $tableSet[$parent])) {
                continue;
            }

            $dependencies[$child][$parent] = true;
        }

        $ordered = [];
        $visited = [];
        $visiting = [];
        $visit = function (string $table) use (&$visit, &$ordered, &$visited, &$visiting, $dependencies): void {
            if (isset($visited[$table])) {
                return;
            }

            if (isset($visiting[$table])) {
                return;
            }

            $visiting[$table] = true;
            foreach (array_keys($dependencies[$table] ?? []) as $parent) {
                $visit($parent);
            }
            unset($visiting[$table]);

            $visited[$table] = true;
            $ordered[] = $table;
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        foreach ($tables as $table) {
            if (!in_array($table, $ordered, true)) {
                $ordered[] = $table;
            }
        }

        return $ordered;
    }

    private function sanitizeQuery(string $sql): string
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new \InvalidArgumentException('Bạn chưa nhập câu lệnh SQL.');
        }

        $trimmed = trim($sql, " \t\n\r;");
        if (str_contains($trimmed, ';')) {
            throw new \InvalidArgumentException('SQL Console chỉ hỗ trợ một câu lệnh tại một thời điểm.');
        }

        return $trimmed;
    }

    private function detectAllowedQueryKind(string $sql): ?string
    {
        $normalized = preg_replace('/^\s*(\/\*.*?\*\/\s*|--[^\n]*\s*|#[^\n]*\s*)+/s', '', $sql) ?? $sql;
        $firstKeyword = strtoupper((string) strtok(ltrim($normalized), " \t\n\r("));

        return in_array($firstKeyword, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true) ? strtolower($firstKeyword) : null;
    }

    private function normalizeValuePayload(array $columnMeta, $payload)
    {
        $isNull = is_array($payload) && !empty($payload['is_null']);
        if ($isNull) {
            return null;
        }

        return is_array($payload) ? ($payload['value'] ?? '') : $payload;
    }

    private function shouldSkipOnInsert(array $columnMeta, $payload): bool
    {
        $rawValue = is_array($payload) ? (string) ($payload['value'] ?? '') : (string) $payload;
        $isNull = is_array($payload) && !empty($payload['is_null']);

        if ($isNull) {
            return false;
        }

        $extra = strtolower((string) ($columnMeta['extra'] ?? ''));
        if (str_contains($extra, 'auto_increment') && trim($rawValue) === '') {
            return true;
        }

        if (trim($rawValue) === '' && (($columnMeta['default_value'] ?? null) !== null || str_contains($extra, 'default_generated'))) {
            return true;
        }

        return false;
    }

    private function isGeneratedColumn(array $columnMeta): bool
    {
        $extra = strtolower((string) ($columnMeta['extra'] ?? ''));

        return str_contains($extra, 'generated');
    }

    private function isBinaryType(string $dataType): bool
    {
        return in_array(strtolower($dataType), ['blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary', 'bit'], true);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function bindValue(\PDOStatement $stmt, string $paramName, $value): void
    {
        if ($value === null) {
            $stmt->bindValue($paramName, null, \PDO::PARAM_NULL);
            return;
        }

        $stmt->bindValue($paramName, (string) $value, \PDO::PARAM_STR);
    }

    private function assertValidTable(string $table): void
    {
        if (!$this->tableExists($table)) {
            throw new \InvalidArgumentException('Bảng SQL không tồn tại hoặc không thuộc database hiện tại.');
        }
    }

    private function resolveRowLocatorColumns(string $table, array $rowKey): array
    {
        if ($rowKey === []) {
            throw new \InvalidArgumentException('Thiếu row key để xác định bản ghi.');
        }

        $primaryKeys = $this->getPrimaryKeyColumns($table);
        if ($primaryKeys !== [] && $this->rowKeyContainsAllColumns($rowKey, $primaryKeys)) {
            return $primaryKeys;
        }

        foreach ($this->getUniqueIndexes($table) as $columns) {
            if ($columns !== [] && $this->rowKeyContainsAllColumns($rowKey, $columns)) {
                return array_values($columns);
            }
        }

        throw new \RuntimeException('Bảng này không có row key hợp lệ để sửa/xóa trực tiếp an toàn.');
    }

    private function rowKeyContainsAllColumns(array $rowKey, array $columns): bool
    {
        foreach ($columns as $columnName) {
            if (!array_key_exists($columnName, $rowKey)) {
                return false;
            }
        }

        return true;
    }
}
