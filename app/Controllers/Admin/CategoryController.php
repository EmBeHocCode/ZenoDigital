<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Category;
use App\Services\ModuleHealthGuardService;

class CategoryController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->ensureCategoriesModuleHealthy('read');
        $model = new Category($this->config);

        $search = sanitize_text((string) ($_GET['q'] ?? ''), 120);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $model->paginated($search, $page, 10);

        $this->view('admin/categories/index', [
            'categories' => $result['data'],
            'meta' => $result['meta'],
            'search' => $search,
        ], 'admin');
    }

    public function create(): void
    {
        $this->requireAdmin();
        $this->ensureCategoriesModuleHealthy('write');
        $this->view('admin/categories/create', [], 'admin');
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/categories/create');
        $this->ensureCategoriesModuleHealthy('write');

        $name = sanitize_text((string) ($_POST['name'] ?? ''), 120);
        $description = sanitize_text((string) ($_POST['description'] ?? ''), 500);

        if ($name === '') {
            flash('danger', 'Tên danh mục là bắt buộc.');
            redirect('admin/categories/create');
        }

        $model = new Category($this->config);
        $ok = $model->create([
            'name' => $name,
            'slug' => $this->slugify($name),
            'description' => $description,
        ]);

        if ($ok) {
            admin_audit('create', 'category', null, ['name' => $name]);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Tạo danh mục thành công.' : 'Tạo danh mục thất bại.');
        redirect('admin/categories');
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $this->ensureCategoriesModuleHealthy('write');
        $model = new Category($this->config);
        $category = $model->find($id);

        if (!$category) {
            flash('danger', 'Danh mục không tồn tại.');
            redirect('admin/categories');
        }

        $this->view('admin/categories/edit', ['category' => $category], 'admin');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/categories/edit/' . $id);
        $this->ensureCategoriesModuleHealthy('write');
        $model = new Category($this->config);

        $name = sanitize_text((string) ($_POST['name'] ?? ''), 120);
        if ($name === '') {
            flash('danger', 'Tên danh mục là bắt buộc.');
            redirect('admin/categories/edit/' . $id);
        }

        $ok = $model->update($id, [
            'name' => $name,
            'slug' => $this->slugify($name),
            'description' => sanitize_text((string) ($_POST['description'] ?? ''), 500),
        ]);

        if ($ok) {
            admin_audit('update', 'category', $id, ['name' => $name]);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Cập nhật danh mục thành công.' : 'Cập nhật danh mục thất bại.');
        redirect('admin/categories');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/categories');
        $this->ensureCategoriesModuleHealthy('write');
        $model = new Category($this->config);

        if (!$model->canDelete($id)) {
            flash('danger', 'Không thể xóa danh mục đang liên kết sản phẩm.');
            redirect('admin/categories');
        }

        $ok = $model->delete($id);
        if ($ok) {
            admin_audit('delete', 'category', $id);
        }
        flash($ok ? 'success' : 'danger', $ok ? 'Xóa danh mục thành công.' : 'Xóa danh mục thất bại.');
        redirect('admin/categories');
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        return strtolower(trim($text, '-')) ?: 'category';
    }

    private function ensureCategoriesModuleHealthy(string $intent): void
    {
        $healthGuard = new ModuleHealthGuardService($this->config);
        if ($healthGuard->isHealthy('categories')) {
            return;
        }

        $healthGuard->logBlockedAction('categories', $intent, [
            'controller' => __CLASS__,
            'intent' => $intent,
        ]);
        flash('danger', $healthGuard->messageFor('categories', $intent));
        redirect('admin');
    }
}
