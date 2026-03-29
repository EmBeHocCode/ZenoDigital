<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\SqlManager;
use App\Services\SchemaHealthService;
use App\Services\SqlImportService;

class SqlManagerController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $sqlManager = new SqlManager($this->config);
        $schemaHealth = new SchemaHealthService($this->config);
        $tables = $sqlManager->listTables();
        $selectedTable = trim((string) ($_GET['table'] ?? ($tables[0]['name'] ?? '')));
        $selectedTable = $sqlManager->tableExists($selectedTable) ? $selectedTable : ((string) ($tables[0]['name'] ?? ''));

        $initialContext = null;
        if ($selectedTable !== '') {
            $initialContext = $sqlManager->browseTable($selectedTable, 1, 25);
        }

        $cssPath = BASE_PATH . '/public/assets/css/admin-sql-manager.css';
        $jsPath = BASE_PATH . '/public/assets/js/admin-sql-manager.js';
        $cssUrl = base_url('assets/css/admin-sql-manager.css') . (is_file($cssPath) ? '?v=' . filemtime($cssPath) : '');
        $jsUrl = base_url('assets/js/admin-sql-manager.js') . (is_file($jsPath) ? '?v=' . filemtime($jsPath) : '');

        $this->view('admin/sql_manager/index', [
            'title' => 'SQL Manager',
            'databaseName' => $sqlManager->databaseName(),
            'tables' => $tables,
            'selectedTable' => $selectedTable,
            'initialContext' => $initialContext,
            'initialCsrfToken' => csrf_token(),
            'moduleHealthSummary' => $schemaHealth->summary(),
            'pageStyles' => [$cssUrl],
            'pageScripts' => [$jsUrl],
        ], 'admin');
    }

    public function tableData(): void
    {
        $this->ensureAdminApi();

        $sqlManager = new SqlManager($this->config);
        $table = trim((string) ($_GET['table'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(10, min((int) ($_GET['per_page'] ?? 25), 100));
        $search = trim((string) ($_GET['search'] ?? ''));
        $sortColumn = trim((string) ($_GET['sort_column'] ?? ''));
        $sortDirection = trim((string) ($_GET['sort_direction'] ?? 'asc'));

        try {
            $context = $sqlManager->browseTable($table, $page, $perPage, $search, $sortColumn, $sortDirection);
            $this->respondJson([
                'success' => true,
                'context' => $context,
            ]);
        } catch (\Throwable $exception) {
            $this->respondJson([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function runQuery(): void
    {
        $this->ensureAdminApi();
        $this->ensurePostApi();

        $sqlManager = new SqlManager($this->config);
        $sql = (string) ($_POST['sql'] ?? '');

        try {
            $result = $sqlManager->runReadOnlyQuery($sql);
            admin_audit('sql_query', 'sql_console', null, [
                'query_kind' => $result['query_kind'],
                'query_preview' => mb_substr($result['query'], 0, 180),
                'row_count' => $result['row_count'],
            ]);

            $this->respondJson([
                'success' => true,
                'message' => 'Đã chạy truy vấn SQL thành công.',
                'result' => $result,
                'csrf_token' => csrf_token(),
            ]);
        } catch (\Throwable $exception) {
            $dbError = $this->normalizeDatabaseError($exception);
            $message = $dbError['message'];
            if ($dbError['sqlstate'] !== '' || $dbError['error_code'] !== 0) {
                $message .= ' | SQLSTATE: ' . ($dbError['sqlstate'] !== '' ? $dbError['sqlstate'] : '-')
                    . ' | Code: ' . ($dbError['error_code'] !== 0 ? (string) $dbError['error_code'] : '-');
            }
            $this->respondJson([
                'success' => false,
                'message' => $message,
                'error' => $dbError,
                'query_preview' => mb_substr(trim($sql), 0, 280),
                'csrf_token' => csrf_token(),
            ], 422);
        }
    }

    public function importSql(): void
    {
        $this->ensureAdminApi();
        $this->ensurePostApi();

        $importService = new SqlImportService($this->config);
        $confirmRisky = !empty($_POST['confirm_risky']);
        $file = $_FILES['sql_file'] ?? null;

        if (!is_array($file)) {
            $this->respondJson([
                'success' => false,
                'message' => 'Bạn chưa chọn file SQL để import.',
                'csrf_token' => csrf_token(),
            ], 422);
        }

        try {
            $result = $importService->importUploadedFile($file, $confirmRisky);
            $affectedModules = (array) ($result['analysis']['affected_modules'] ?? []);
            $unhealthyModules = (array) ($result['module_health']['unhealthy_modules'] ?? []);
            $auditMeta = [
                'filename' => (string) ($result['filename'] ?? ($file['name'] ?? 'unknown.sql')),
                'statement_count' => (int) (($result['analysis']['statement_count'] ?? 0)),
                'successful_statements' => (int) (($result['execution']['successful_statements'] ?? 0)),
                'failed_statement_number' => $result['execution']['failed_statement_number'] ?? null,
                'rollback_supported' => (bool) (($result['analysis']['rollback_supported'] ?? false)),
                'rollback_succeeded' => $result['execution']['rollback_succeeded'] ?? null,
                'requires_confirmation' => (bool) ($result['requires_confirmation'] ?? false),
                'affected_modules' => $affectedModules,
                'unhealthy_modules' => $unhealthyModules,
                'database_message' => $result['execution']['database_message'] ?? null,
                'sqlstate' => $result['execution']['sqlstate'] ?? null,
                'error_code' => $result['execution']['error_code'] ?? null,
            ];

            if (!empty($result['requires_confirmation'])) {
                admin_audit('sql_import_preflight', 'sql_manager', null, $auditMeta);
                security_log('SQL import preflight cảnh báo DDL/mixed statements', $auditMeta);

                $this->respondJson([
                    'success' => false,
                    'message' => $result['message'] ?? 'File SQL cần xác nhận trước khi chạy.',
                    'result' => $result,
                    'csrf_token' => csrf_token(),
                ], 409);
            }

            if (!empty($result['success'])) {
                admin_audit('sql_import_success', 'sql_manager', null, $auditMeta);
                security_log('SQL import thành công', $auditMeta);

                $this->respondJson([
                    'success' => true,
                    'message' => $result['message'] ?? 'Đã import SQL thành công.',
                    'result' => $result,
                    'csrf_token' => csrf_token(),
                ]);
            }

            admin_audit('sql_import_failed', 'sql_manager', null, $auditMeta);
            security_log('SQL import thất bại', $auditMeta);

            $this->respondJson([
                'success' => false,
                'message' => $result['message'] ?? 'Import SQL thất bại.',
                'result' => $result,
                'csrf_token' => csrf_token(),
            ], 422);
        } catch (\Throwable $exception) {
            $dbError = $this->normalizeDatabaseError($exception);
            $auditMeta = [
                'filename' => (string) (($file['name'] ?? 'unknown.sql')),
                'database_message' => $dbError['message'],
                'sqlstate' => $dbError['sqlstate'],
                'error_code' => $dbError['error_code'],
            ];
            admin_audit('sql_import_failed', 'sql_manager', null, $auditMeta);
            security_log('SQL import ném exception chưa xử lý', $auditMeta);

            $this->respondJson([
                'success' => false,
                'message' => $dbError['message'],
                'error' => $dbError,
                'csrf_token' => csrf_token(),
            ], 422);
        }
    }

    public function commitBatch(): void
    {
        $this->ensureAdminApi();
        $this->ensurePostApi();

        $sqlManager = new SqlManager($this->config);

        try {
            $operations = $this->decodeJsonPostField('operations_json');
            $summary = $sqlManager->commitBatchOperations($operations);

            $tables = [];
            foreach ($operations as $operation) {
                if (!is_array($operation)) {
                    continue;
                }

                $table = trim((string) ($operation['table'] ?? ''));
                if ($table !== '') {
                    $tables[$table] = true;
                }
            }

            admin_audit('sql_batch_commit', count($tables) === 1 ? (string) array_key_first($tables) : 'multiple_tables', null, [
                'processed' => $summary['processed'] ?? 0,
                'counts' => $summary['counts'] ?? [],
                'tables' => array_keys($tables),
            ]);

            $processed = (int) ($summary['processed'] ?? 0);

            $this->respondJson([
                'success' => true,
                'message' => $processed > 0
                    ? 'Đã commit ' . $processed . ' thao tác trong một transaction.'
                    : 'Không có thao tác nào để commit.',
                'summary' => $summary,
                'csrf_token' => csrf_token(),
            ]);
        } catch (\Throwable $exception) {
            $dbError = $this->normalizeDatabaseError($exception);
            $this->respondJson([
                'success' => false,
                'message' => $dbError['message'],
                'error' => $dbError,
                'csrf_token' => csrf_token(),
            ], 422);
        }
    }

    public function updateRow(): void
    {
        $this->ensureAdminApi();
        $this->ensurePostApi();

        $sqlManager = new SqlManager($this->config);

        try {
            $table = trim((string) ($_POST['table'] ?? ''));
            $rowKey = $this->decodeJsonPostField('row_key_json');
            $values = $this->decodeJsonPostField('values_json');

            $affectedRows = $sqlManager->updateRow($table, $rowKey, $values);
            admin_audit('sql_update', $table, null, [
                'row_key' => $rowKey,
                'changed_columns' => array_values(array_keys($values)),
                'affected_rows' => $affectedRows,
            ]);

            $this->respondJson([
                'success' => true,
                'message' => $affectedRows > 0 ? 'Đã cập nhật bản ghi.' : 'Không có thay đổi nào được ghi.',
                'csrf_token' => csrf_token(),
            ]);
        } catch (\Throwable $exception) {
            $dbError = $this->normalizeDatabaseError($exception);
            $this->respondJson([
                'success' => false,
                'message' => $dbError['message'],
                'error' => $dbError,
                'csrf_token' => csrf_token(),
            ], 422);
        }
    }

    public function insertRow(): void
    {
        $this->ensureAdminApi();
        $this->ensurePostApi();

        $sqlManager = new SqlManager($this->config);

        try {
            $table = trim((string) ($_POST['table'] ?? ''));
            $values = $this->decodeJsonPostField('values_json');

            $insertId = $sqlManager->insertRow($table, $values);
            admin_audit('sql_insert', $table, null, [
                'insert_id' => $insertId,
                'columns' => array_values(array_keys($values)),
            ]);

            $this->respondJson([
                'success' => true,
                'message' => 'Đã thêm bản ghi mới vào bảng ' . $table . '.',
                'insert_id' => $insertId,
                'csrf_token' => csrf_token(),
            ]);
        } catch (\Throwable $exception) {
            $dbError = $this->normalizeDatabaseError($exception);
            $this->respondJson([
                'success' => false,
                'message' => $dbError['message'],
                'error' => $dbError,
                'csrf_token' => csrf_token(),
            ], 422);
        }
    }

    public function deleteRow(): void
    {
        $this->ensureAdminApi();
        $this->ensurePostApi();

        $sqlManager = new SqlManager($this->config);

        try {
            $table = trim((string) ($_POST['table'] ?? ''));
            $rowKey = $this->decodeJsonPostField('row_key_json');

            $affectedRows = $sqlManager->deleteRow($table, $rowKey);
            admin_audit('sql_delete', $table, null, [
                'row_key' => $rowKey,
                'affected_rows' => $affectedRows,
            ]);

            $this->respondJson([
                'success' => true,
                'message' => $affectedRows > 0 ? 'Đã xóa bản ghi.' : 'Không tìm thấy bản ghi để xóa.',
                'csrf_token' => csrf_token(),
            ]);
        } catch (\Throwable $exception) {
            $this->respondJson([
                'success' => false,
                'message' => $exception->getMessage(),
                'csrf_token' => csrf_token(),
            ], 422);
        }
    }

    private function ensureAdminApi(): void
    {
        if (!Auth::check() || !Auth::isAdmin()) {
            $this->respondJson([
                'success' => false,
                'message' => 'Phiên admin không hợp lệ hoặc đã hết hạn.',
            ], 403);
        }
    }

    private function ensurePostApi(): void
    {
        if (!is_post()) {
            $this->respondJson([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }
    }

    private function decodeJsonPostField(string $field): array
    {
        $raw = (string) ($_POST[$field] ?? '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Dữ liệu gửi lên không hợp lệ ở trường ' . $field . '.');
        }

        return $decoded;
    }

    private function normalizeDatabaseError(\Throwable $exception): array
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

    private function respondJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
