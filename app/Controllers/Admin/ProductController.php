<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ModuleHealthGuardService;

class ProductController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->ensureProductsModuleHealthy('read');

        $productModel = new Product($this->config);
        $categoryModel = new Category($this->config);

        $filters = [
            'q' => sanitize_text((string) ($_GET['q'] ?? ''), 120),
            'category_id' => sanitize_text((string) ($_GET['category_id'] ?? ''), 10),
            'min_price' => sanitize_text((string) ($_GET['min_price'] ?? ''), 12),
            'max_price' => sanitize_text((string) ($_GET['max_price'] ?? ''), 12),
            'sort' => validate_enum((string) ($_GET['sort'] ?? 'latest'), ['latest', 'price_asc', 'price_desc'], 'latest'),
        ];

        if ($filters['min_price'] !== '') {
            $filters['min_price'] = (string) validate_float_range($filters['min_price'], 0, 1000000000, 0);
        }

        if ($filters['max_price'] !== '') {
            $filters['max_price'] = (string) validate_float_range($filters['max_price'], 0, 1000000000, 0);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $productModel->paginated($filters, $page, 10);

        $this->view('admin/products/index', [
            'products' => $result['data'],
            'meta' => $result['meta'],
            'filters' => $filters,
            'categories' => $categoryModel->all(),
        ], 'admin');
    }

    public function create(): void
    {
        $this->requireAdmin();
        $this->ensureProductsModuleHealthy('write');
        $categoryModel = new Category($this->config);
        $this->view('admin/products/create', ['categories' => $categoryModel->all()], 'admin');
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/products/create');
        $this->ensureProductsModuleHealthy('write');

        $payload = $this->validateProductRequest();
        if (!$payload) {
            redirect('admin/products/create');
        }

        $productModel = new Product($this->config);
        $productId = $productModel->create($payload);

        if ($productId !== false) {
            $images = $this->uploadMultiple('gallery');
            $productModel->saveImages($productId, $images);
            admin_audit('create', 'product', (int) $productId, [
                'name' => $payload['name'] ?? '',
                'price' => $payload['price'] ?? 0,
                'status' => $payload['status'] ?? 'active',
            ]);
            flash('success', 'Thêm sản phẩm thành công.');
        } else {
            flash('danger', 'Thêm sản phẩm thất bại.');
        }

        redirect('admin/products');
    }

    public function show(int $id): void
    {
        $this->requireAdmin();
        $this->ensureProductsModuleHealthy('read');
        $model = new Product($this->config);

        $product = $model->find($id);
        if (!$product) {
            flash('danger', 'Sản phẩm không tồn tại.');
            redirect('admin/products');
        }

        $this->view('admin/products/show', [
            'product' => $product,
            'images' => $model->images($id),
        ], 'admin');
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $this->ensureProductsModuleHealthy('write');

        $productModel = new Product($this->config);
        $categoryModel = new Category($this->config);

        $product = $productModel->find($id);
        if (!$product) {
            flash('danger', 'Sản phẩm không tồn tại.');
            redirect('admin/products');
        }

        $this->view('admin/products/edit', [
            'product' => $product,
            'categories' => $categoryModel->all(),
            'images' => $productModel->images($id),
        ], 'admin');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/products/edit/' . $id);
        $this->ensureProductsModuleHealthy('write');

        $payload = $this->validateProductRequest($id);
        if (!$payload) {
            redirect('admin/products/edit/' . $id);
        }

        $model = new Product($this->config);
        $current = $model->find($id);

        if (!empty($_FILES['image']['name'])) {
            $uploaded = secure_upload_image($_FILES['image'], 'product');
            if ($uploaded === null) {
                flash('danger', 'Ảnh đại diện không hợp lệ hoặc vượt dung lượng cho phép.');
                redirect('admin/products/edit/' . $id);
            }
            $payload['image'] = $uploaded;
        } else {
            $payload['image'] = $current['image'] ?? null;
        }

        $ok = $model->update($id, $payload);

        $images = $this->uploadMultiple('gallery');
        $model->saveImages($id, $images);

        if ($ok) {
            admin_audit('update', 'product', $id, [
                'name' => $payload['name'] ?? '',
                'price' => $payload['price'] ?? 0,
                'status' => $payload['status'] ?? 'active',
            ]);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Cập nhật sản phẩm thành công.' : 'Cập nhật sản phẩm thất bại.');
        redirect('admin/products');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/products');
        $this->ensureProductsModuleHealthy('write');

        $model = new Product($this->config);
        $ok = $model->delete($id);

        if ($ok) {
            admin_audit('delete', 'product', $id);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Xóa sản phẩm thành công.' : 'Xóa sản phẩm thất bại.');
        redirect('admin/products');
    }

    private function validateProductRequest(?int $id = null): ?array
    {
        $name = sanitize_text((string) ($_POST['name'] ?? ''), 180);
        $categoryId = validate_int_range($_POST['category_id'] ?? null, 1, 9999999, 0);
        $price = validate_float_range($_POST['price'] ?? null, 1000, 1000000000, 0);

        $status = validate_enum((string) ($_POST['status'] ?? ''), ['active', 'inactive'], 'active');
        $stockStatus = validate_enum((string) ($_POST['stock_status'] ?? ''), ['in_stock', 'out_of_stock'], 'in_stock');

        if ($name === '' || $categoryId <= 0 || $price <= 0) {
            flash('danger', 'Vui lòng nhập đầy đủ thông tin bắt buộc của sản phẩm.');
            return null;
        }

        return [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $this->slugify($name . '-' . ($id ?? time())),
            'price' => $price,
            'short_description' => sanitize_text((string) ($_POST['short_description'] ?? ''), 300),
            'description' => sanitize_text((string) ($_POST['description'] ?? ''), 5000),
            'specs' => sanitize_text((string) ($_POST['specs'] ?? ''), 5000),
            'image' => $_POST['current_image'] ?? null,
            'stock_status' => $stockStatus,
            'status' => $status,
        ];
    }

    private function uploadMultiple(string $field): array
    {
        $saved = [];
        if (empty($_FILES[$field]['name'][0])) {
            return $saved;
        }

        foreach ($_FILES[$field]['name'] as $index => $originName) {
            if (($_FILES[$field]['error'][$index] ?? 1) !== UPLOAD_ERR_OK) {
                continue;
            }

            $file = [
                'name' => $originName,
                'type' => $_FILES[$field]['type'][$index] ?? '',
                'tmp_name' => $_FILES[$field]['tmp_name'][$index] ?? '',
                'error' => $_FILES[$field]['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES[$field]['size'][$index] ?? 0,
            ];

            $uploaded = secure_upload_image($file, 'gallery');
            if ($uploaded !== null) {
                $saved[] = $uploaded;
            }
        }

        return $saved;
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        return strtolower(trim($text, '-')) ?: 'product';
    }

    private function ensureProductsModuleHealthy(string $intent): void
    {
        $healthGuard = new ModuleHealthGuardService($this->config);
        if ($healthGuard->isHealthy('products')) {
            return;
        }

        $healthGuard->logBlockedAction('products', $intent, [
            'controller' => __CLASS__,
            'intent' => $intent,
        ]);
        flash('danger', $healthGuard->messageFor('products', $intent));
        redirect('admin');
    }
}
