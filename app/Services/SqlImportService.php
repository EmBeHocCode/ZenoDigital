<?php

namespace App\Services;

use App\Core\Database;

class SqlImportService
{
    private \PDO $db;
    private SchemaHealthService $schemaHealth;

    public function __construct(array $config)
    {
        $this->db = Database::getConnection($config['db']);
        $this->schemaHealth = new SchemaHealthService($config);
    }

    public function importUploadedFile(array $file, bool $confirmRisky = false): array
    {
        $validated = $this->validateUploadedFile($file);
        $sql = (string) file_get_contents((string) $validated['tmp_name']);

        return $this->importSqlText($sql, (string) $validated['name'], (int) $validated['size'], $confirmRisky);
    }

    public function importSqlText(string $sqlText, string $filename = 'import.sql', int $size = 0, bool $confirmRisky = false): array
    {
        $statements = $this->parseStatements($sqlText);
        if ($statements === []) {
            throw new \InvalidArgumentException('File SQL không có statement hợp lệ để chạy.');
        }

        $analysis = $this->analyzeStatements($statements);
        $summary = [
            'success' => false,
            'requires_confirmation' => false,
            'filename' => $filename,
            'file_size' => $size,
            'analysis' => $analysis,
            'execution' => null,
            'module_health' => $this->schemaHealth->summary(),
        ];

        if (!empty($analysis['has_control_statements'])) {
            throw new \RuntimeException('File SQL chứa câu lệnh điều khiển transaction hoặc session không được SQL Manager hỗ trợ an toàn.');
        }

        if (empty($analysis['rollback_supported']) && !$confirmRisky) {
            $summary['requires_confirmation'] = true;
            $summary['message'] = 'File SQL không phải DML thuần hoặc có chứa DDL/mixed statements. MySQL có thể auto-commit nên không đảm bảo rollback đầy đủ.';

            return $summary;
        }

        $startedAt = microtime(true);
        $successfulStatements = 0;
        $rollbackAttempted = false;
        $rollbackSucceeded = false;
        $rollbackSupported = (bool) $analysis['rollback_supported'];
        $rollbackGuaranteed = $rollbackSupported;

        if ($rollbackSupported) {
            $this->db->beginTransaction();
        }

        try {
            foreach ($statements as $index => $statement) {
                $this->db->exec((string) $statement['sql']);
                $successfulStatements = $index + 1;
            }

            if ($rollbackSupported && $this->db->inTransaction()) {
                $this->db->commit();
            }

            $summary['success'] = true;
            $summary['message'] = 'Đã import SQL thành công.';
            $summary['execution'] = [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'successful_statements' => $successfulStatements,
                'failed_statement_number' => null,
                'failed_line' => null,
                'failed_sql_preview' => null,
                'database_message' => null,
                'sqlstate' => null,
                'error_code' => null,
                'rollback_attempted' => false,
                'rollback_succeeded' => false,
                'rollback_supported' => $rollbackSupported,
                'rollback_guaranteed' => $rollbackGuaranteed,
                'partial_changes_possible' => false,
            ];
            $summary['module_health'] = $this->schemaHealth->summary();

            return $summary;
        } catch (\Throwable $exception) {
            if ($rollbackSupported && $this->db->inTransaction()) {
                $rollbackAttempted = true;
                try {
                    $this->db->rollBack();
                    $rollbackSucceeded = true;
                } catch (\Throwable $rollbackException) {
                    $rollbackSucceeded = false;
                }
            }

            $statementNumber = min($successfulStatements + 1, count($statements));
            $failedStatement = $statements[$statementNumber - 1] ?? null;
            $dbError = $this->normalizeDatabaseError($exception);
            $summary['success'] = false;
            $summary['message'] = 'Import SQL thất bại ở statement #' . $statementNumber . '.';
            $summary['execution'] = [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'successful_statements' => $successfulStatements,
                'failed_statement_number' => $statementNumber,
                'failed_line' => $failedStatement['start_line'] ?? null,
                'failed_sql_preview' => $failedStatement ? $this->sqlPreview((string) $failedStatement['sql']) : null,
                'database_message' => $dbError['message'],
                'sqlstate' => $dbError['sqlstate'],
                'error_code' => $dbError['error_code'],
                'rollback_attempted' => $rollbackAttempted,
                'rollback_succeeded' => $rollbackSucceeded,
                'rollback_supported' => $rollbackSupported,
                'rollback_guaranteed' => $rollbackGuaranteed,
                'partial_changes_possible' => !$rollbackSupported || !$rollbackSucceeded,
            ];
            $summary['module_health'] = $this->schemaHealth->summary();

            return $summary;
        }
    }

    public function normalizeDatabaseError(\Throwable $exception): array
    {
        $sqlState = '';
        $errorCode = 0;
        $message = trim($exception->getMessage());

        if ($exception instanceof \PDOException && is_array($exception->errorInfo ?? null)) {
            $sqlState = (string) ($exception->errorInfo[0] ?? '');
            $errorCode = (int) ($exception->errorInfo[1] ?? 0);
            $message = trim((string) ($exception->errorInfo[2] ?? $message));
        } elseif (is_numeric($exception->getCode())) {
            $errorCode = (int) $exception->getCode();
        }

        return [
            'message' => $message !== '' ? $message : 'Database trả về lỗi không xác định.',
            'sqlstate' => $sqlState,
            'error_code' => $errorCode,
        ];
    }

    private function validateUploadedFile(array $file): array
    {
        $name = trim((string) ($file['name'] ?? ''));
        $tmpName = trim((string) ($file['tmp_name'] ?? ''));
        $size = (int) ($file['size'] ?? 0);
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Upload file SQL thất bại. Mã lỗi upload: ' . $error . '.');
        }

        if ($name === '' || !preg_match('/\.sql$/i', $name)) {
            throw new \InvalidArgumentException('Chỉ chấp nhận file `.sql` trong chế độ import SQL.');
        }

        if ($size <= 0) {
            throw new \InvalidArgumentException('File SQL đang rỗng.');
        }

        if ($size > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('File SQL vượt giới hạn 5MB của SQL Manager.');
        }

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \InvalidArgumentException('Không thể đọc file SQL đã upload.');
        }

        return [
            'name' => $name,
            'tmp_name' => $tmpName,
            'size' => $size,
        ];
    }

    private function analyzeStatements(array $statements): array
    {
        $ddlCount = 0;
        $dmlCount = 0;
        $controlCount = 0;
        $otherCount = 0;
        $tables = [];
        $warnings = [];

        foreach ($statements as $statement) {
            $kind = $this->statementKind((string) ($statement['sql'] ?? ''));
            if ($kind === 'ddl') {
                $ddlCount++;
            } elseif ($kind === 'dml') {
                $dmlCount++;
            } elseif ($kind === 'control') {
                $controlCount++;
            } else {
                $otherCount++;
            }

            foreach ($this->extractTableNames((string) ($statement['sql'] ?? '')) as $tableName) {
                $tables[$tableName] = true;
            }
        }

        if ($ddlCount > 0) {
            $warnings[] = 'File có chứa DDL. Một số câu lệnh MySQL có thể auto-commit và không rollback đầy đủ.';
        }

        if ($controlCount > 0) {
            $warnings[] = 'File có chứa câu lệnh control/session. SQL Manager sẽ chặn loại file này để tránh trạng thái khó dự đoán.';
        }

        if ($otherCount > 0) {
            $warnings[] = 'File có chứa statement ngoài nhóm DML thuần. SQL Manager sẽ xem đây là import có rủi ro và yêu cầu xác nhận trước khi chạy.';
        }

        $affectedTables = array_values(array_keys($tables));

        return [
            'statement_count' => count($statements),
            'dml_count' => $dmlCount,
            'ddl_count' => $ddlCount,
            'control_count' => $controlCount,
            'other_count' => $otherCount,
            'contains_ddl' => $ddlCount > 0,
            'has_control_statements' => $controlCount > 0,
            'rollback_supported' => $dmlCount === count($statements),
            'tables_referenced' => $affectedTables,
            'affected_modules' => $this->schemaHealth->affectedModulesByTables($affectedTables),
            'warnings' => $warnings,
        ];
    }

    private function parseStatements(string $sqlText): array
    {
        $sqlText = str_replace("\r\n", "\n", str_replace("\r", "\n", $sqlText));
        $sqlText = preg_replace('/^\xEF\xBB\xBF/', '', $sqlText) ?? $sqlText;

        $length = strlen($sqlText);
        $statements = [];
        $buffer = '';
        $line = 1;
        $statementStartLine = 1;
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;
        $escaped = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $sqlText[$index];
            $next = $sqlText[$index + 1] ?? '';
            $nextNext = $sqlText[$index + 2] ?? '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $line++;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $index++;
                    continue;
                }

                if ($char === "\n") {
                    $line++;
                }
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick && trim($buffer) === '' && trim($char) !== '') {
                $statementStartLine = $line;
            }

            if ($inSingle || $inDouble) {
                $buffer .= $char;

                if ($char === "\n") {
                    $line++;
                }

                if ($char === '\\' && !$escaped) {
                    $escaped = true;
                    continue;
                }

                if ($char === '\'' && $inSingle && !$escaped) {
                    $inSingle = false;
                } elseif ($char === '"' && $inDouble && !$escaped) {
                    $inDouble = false;
                }

                $escaped = false;
                continue;
            }

            if ($inBacktick) {
                $buffer .= $char;
                if ($char === '`') {
                    $inBacktick = false;
                }
                if ($char === "\n") {
                    $line++;
                }
                continue;
            }

            if ($char === '-' && $next === '-' && preg_match('/\s/', $nextNext ?: ' ')) {
                $inLineComment = true;
                $index++;
                continue;
            }

            if ($char === '#') {
                $inLineComment = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $index++;
                continue;
            }

            if ($char === '\'') {
                $inSingle = true;
                $buffer .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '"') {
                $inDouble = true;
                $buffer .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '`') {
                $inBacktick = true;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = [
                        'sql' => $statement,
                        'start_line' => $statementStartLine,
                        'end_line' => $line,
                    ];
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
            if ($char === "\n") {
                $line++;
            }
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = [
                'sql' => $statement,
                'start_line' => $statementStartLine,
                'end_line' => $line,
            ];
        }

        return $statements;
    }

    private function statementKind(string $sql): string
    {
        $keyword = $this->firstKeyword($sql);

        if (in_array($keyword, ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'], true)) {
            return 'dml';
        }

        if (in_array($keyword, ['CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'RENAME', 'USE', 'SET'], true)) {
            return 'ddl';
        }

        if (in_array($keyword, ['START', 'BEGIN', 'COMMIT', 'ROLLBACK', 'LOCK', 'UNLOCK'], true)) {
            return 'control';
        }

        return 'other';
    }

    private function firstKeyword(string $sql): string
    {
        $normalized = preg_replace('/^\s*(\/\*.*?\*\/\s*|--[^\n]*\s*|#[^\n]*\s*)+/s', '', $sql) ?? $sql;
        $token = strtoupper((string) strtok(ltrim($normalized), " \t\n\r("));

        return $token;
    }

    private function extractTableNames(string $sql): array
    {
        $patterns = [
            '/\bFROM\s+`?([a-zA-Z0-9_]+)`?/i',
            '/\bJOIN\s+`?([a-zA-Z0-9_]+)`?/i',
            '/\bINTO\s+`?([a-zA-Z0-9_]+)`?/i',
            '/\bUPDATE\s+`?([a-zA-Z0-9_]+)`?/i',
            '/\bTABLE\s+`?([a-zA-Z0-9_]+)`?/i',
        ];

        $tables = [];
        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $sql, $matches)) {
                continue;
            }

            foreach ((array) ($matches[1] ?? []) as $tableName) {
                $tableName = strtolower(trim((string) $tableName));
                if ($tableName !== '') {
                    $tables[$tableName] = true;
                }
            }
        }

        return array_values(array_keys($tables));
    }

    private function sqlPreview(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
        if (mb_strlen($normalized) <= 280) {
            return $normalized;
        }

        return mb_substr($normalized, 0, 277) . '...';
    }
}
