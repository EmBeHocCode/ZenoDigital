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
        $errors = [];
        $status = validate_enum((string) ($_POST['status'] ?? ''), ['active', 'inactive'], 'active');
        $stockStatus = validate_enum((string) ($_POST['stock_status'] ?? ''), ['in_stock', 'out_of_stock'], 'in_stock');
        $productType = validate_enum((string) ($_POST['product_type'] ?? ''), ['service', 'digital_code', 'wallet', 'capacity'], 'service');
        $stockQty = $this->parseNullableInt((string) ($_POST['stock_qty'] ?? ''), 0, 100000000, 'Tồn kho (stock_qty)', $errors);
        $reorderPoint = $this->parseNullableInt((string) ($_POST['reorder_point'] ?? ''), 0, 100000000, 'Ngưỡng nhập lại (reorder_point)', $errors);
        $leadTimeDays = $this->parseNullableInt((string) ($_POST['lead_time_days'] ?? ''), 0, 3650, 'Lead time (ngày)', $errors);
        $capacityLimit = $this->parseNullableInt((string) ($_POST['capacity_limit'] ?? ''), 0, 100000000, 'Giới hạn capacity', $errors);
        $capacityUsed = $this->parseNullableInt((string) ($_POST['capacity_used'] ?? ''), 0, 100000000, 'Capacity đã dùng', $errors);
        $costPrice = $this->parseNullableFloat((string) ($_POST['cost_price'] ?? ''), 0, 1000000000, 'Giá vốn (cost_price)', $errors);
        $minMarginPercent = $this->parseNullableFloat((string) ($_POST['min_margin_percent'] ?? ''), 0, 100, 'Biên lợi nhuận tối thiểu (%)', $errors);
        $platformFeePercent = $this->parseNullableFloat((string) ($_POST['platform_fee_percent'] ?? ''), 0, 100, 'Phí nền tảng (%)', $errors);
        $paymentFeePercent = $this->parseNullableFloat((string) ($_POST['payment_fee_percent'] ?? ''), 0, 100, 'Phí thanh toán (%)', $errors);
        $adsCostPerOrder = $this->parseNullableFloat((string) ($_POST['ads_cost_per_order'] ?? ''), 0, 1000000000, 'Chi phí ads/đơn', $errors);
        $deliveryCost = $this->parseNullableFloat((string) ($_POST['delivery_cost'] ?? ''), 0, 1000000000, 'Chi phí giao hàng', $errors);
        $supplierName = sanitize_text((string) ($_POST['supplier_name'] ?? ''), 160);

        if ($reorderPoint !== null && $stockQty !== null && $reorderPoint > $stockQty) {
            $errors[] = 'Ngưỡng nhập lại không được lớn hơn tồn kho hiện tại.';
        }

        if ($capacityUsed !== null && $capacityLimit !== null && $capacityUsed > $capacityLimit) {
            $errors[] = 'Capacity đã dùng không được lớn hơn giới hạn capacity.';
        }

        if ($productType === 'capacity' && $capacityLimit === null) {
            $errors[] = 'Sản phẩm loại capacity cần nhập `capacity_limit`.';
        }

        if (in_array($productType, ['digital_code', 'wallet'], true) && $stockQty === null) {
            $errors[] = 'Sản phẩm loại digital_code/wallet cần nhập `stock_qty`.';
        }

        if ($name === '' || $categoryId <= 0 || $price <= 0) {
            flash('danger', 'Vui lòng nhập đầy đủ thông tin bắt buộc của sản phẩm.');
            return null;
        }

        if ($errors !== []) {
            flash('danger', implode(' ', array_values(array_unique($errors))));
            return null;
        }

        return [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $this->slugify($name . '-' . ($id ?? time())),
            'price' => $price,
            'product_type' => $productType,
            'stock_qty' => $stockQty,
            'reorder_point' => $reorderPoint,
            'supplier_name' => $supplierName !== '' ? $supplierName : null,
            'lead_time_days' => $leadTimeDays,
            'cost_price' => $costPrice,
            'min_margin_percent' => $minMarginPercent,
            'platform_fee_percent' => $platformFeePercent,
            'payment_fee_percent' => $paymentFeePercent,
            'ads_cost_per_order' => $adsCostPerOrder,
            'delivery_cost' => $deliveryCost,
            'capacity_limit' => $capacityLimit,
            'capacity_used' => $capacityUsed,
            'short_description' => sanitize_text((string) ($_POST['short_description'] ?? ''), 300),
            'description' => sanitize_text((string) ($_POST['description'] ?? ''), 5000),
            'specs' => sanitize_text((string) ($_POST['specs'] ?? ''), 5000),
            'image' => $_POST['current_image'] ?? null,
            'stock_status' => $stockStatus,
            'status' => $status,
        ];
    }

    private function parseNullableInt(string $rawValue, int $min, int $max, string $label, array &$errors): ?int
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $rawValue) !== 1) {
            $errors[] = $label . ' phải là số nguyên không âm.';
            return null;
        }

        $value = (int) $rawValue;
        if ($value < $min || $value > $max) {
            $errors[] = $label . ' phải nằm trong khoảng ' . $min . ' - ' . $max . '.';
            return null;
        }

        return $value;
    }

    private function parseNullableFloat(string $rawValue, float $min, float $max, string $label, array &$errors): ?float
    {
        $rawValue = trim(str_replace(',', '.', $rawValue));
        if ($rawValue === '') {
            return null;
        }

        if (!is_numeric($rawValue)) {
            $errors[] = $label . ' phải là số hợp lệ.';
            return null;
        }

        $value = (float) $rawValue;
        if ($value < $min || $value > $max) {
            $errors[] = $label . ' phải nằm trong khoảng ' . $min . ' - ' . $max . '.';
            return null;
        }

        return $value;
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
