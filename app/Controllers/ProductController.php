<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\ModuleHealthGuardService;

class ProductController extends Controller
{
    public function index(): void
    {
        $healthGuard = new ModuleHealthGuardService($this->config);
        if (!$healthGuard->isHealthy('products')) {
            $healthGuard->logBlockedAction('products', 'read', ['controller' => __CLASS__, 'action' => __FUNCTION__]);
            flash('warning', $healthGuard->messageFor('products', 'read'));

            $this->view('products/index', [
                'title' => 'Cloud VPS & Cloud Server',
                'vpsUi' => true,
                'products' => [],
                'meta' => paginate_meta(0, 1, 9),
                'filters' => [
                    'q' => '',
                    'category_id' => '',
                    'min_price' => '',
                    'max_price' => '',
                    'cpu' => '',
                    'ram' => '',
                    'disk' => '',
                    'location' => '',
                    'plan_type' => '',
                    'sort' => 'latest',
                ],
                'categories' => [],
                'cloudCategories' => [],
                'secondaryCategories' => [],
                'selectedCategory' => null,
                'isCloudCatalog' => true,
            ]);
            return;
        }

        $productModel = new Product($this->config);
        $categoryModel = new Category($this->config);
        $categories = $categoryModel->all();
        $categoryGroups = $categoryModel->storefrontGroups($categories);
        $cloudCategories = $categoryGroups['cloud'];
        $secondaryCategories = $categoryGroups['secondary'];
        $primaryCloudCategory = $cloudCategories[0] ?? null;

        $filters = [
            'q' => sanitize_text((string) ($_GET['q'] ?? ''), 120),
            'category_id' => sanitize_text((string) ($_GET['category_id'] ?? ''), 10),
            'min_price' => sanitize_text((string) ($_GET['min_price'] ?? ''), 12),
            'max_price' => sanitize_text((string) ($_GET['max_price'] ?? ''), 12),
            'cpu' => validate_enum((string) ($_GET['cpu'] ?? ''), ['', '2', '4', '6'], ''),
            'ram' => validate_enum((string) ($_GET['ram'] ?? ''), ['', '4GB', '8GB', '16GB'], ''),
            'disk' => validate_enum((string) ($_GET['disk'] ?? ''), ['', 'ssd', 'nvme'], ''),
            'location' => validate_enum((string) ($_GET['location'] ?? ''), ['', 'VN', 'SG', 'US'], ''),
            'plan_type' => validate_enum((string) ($_GET['plan_type'] ?? ''), ['', 'Starter', 'Business', 'Enterprise'], ''),
            'sort' => validate_enum((string) ($_GET['sort'] ?? 'latest'), ['latest', 'price_asc', 'price_desc', 'popular'], 'latest'),
        ];

        if ($filters['min_price'] !== '') {
            $filters['min_price'] = (string) validate_float_range($filters['min_price'], 0, 1000000000, 0);
        }

        if ($filters['max_price'] !== '') {
            $filters['max_price'] = (string) validate_float_range($filters['max_price'], 0, 1000000000, 0);
        }

        if ($filters['category_id'] === '' && is_array($primaryCloudCategory)) {
            $filters['category_id'] = (string) ((int) ($primaryCloudCategory['id'] ?? 0));
        }

        $selectedCategoryId = (int) ($filters['category_id'] !== '' ? $filters['category_id'] : 0);
        $selectedCategory = null;
        foreach ($categories as $category) {
            if ((int) ($category['id'] ?? 0) === $selectedCategoryId) {
                $selectedCategory = $category;
                break;
            }
        }

        $isCloudCatalog = $selectedCategory ? $categoryModel->isCloudStorefrontCategory($selectedCategory) : true;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $productModel->paginated($filters, $page, 9);
        $pageTitle = 'Cloud VPS & Cloud Server';

        if ($selectedCategory) {
            $pageTitle = $isCloudCatalog
                ? (string) ($selectedCategory['name'] ?? 'Cloud VPS & Cloud Server')
                : 'Dịch vụ ' . (string) ($selectedCategory['name'] ?? 'Sản phẩm số');
        }

        $this->view('products/index', [
            'title' => $pageTitle,
            'vpsUi' => true,
            'products' => $result['data'],
            'meta' => $result['meta'],
            'filters' => $filters,
            'categories' => $categories,
            'cloudCategories' => $cloudCategories,
            'secondaryCategories' => $secondaryCategories,
            'selectedCategory' => $selectedCategory,
            'isCloudCatalog' => $isCloudCatalog,
        ]);
    }

    public function show(int $id): void
    {
        $healthGuard = new ModuleHealthGuardService($this->config);
        if (!$healthGuard->isHealthy('products')) {
            $healthGuard->logBlockedAction('products', 'read', [
                'controller' => __CLASS__,
                'action' => __FUNCTION__,
                'product_id' => $id,
            ]);
            flash('warning', $healthGuard->messageFor('products', 'read'));
            redirect('products');
        }

        $productModel = new Product($this->config);
        $product = $productModel->find($id);
        $currentWalletBalance = 0.0;

        if (Auth::check() && $healthGuard->isHealthy('users')) {
            $viewer = (new User($this->config))->find((int) Auth::id());
            $currentWalletBalance = (float) ($viewer['wallet_balance'] ?? 0);
        } elseif (Auth::check() && !$healthGuard->isHealthy('users')) {
            $healthGuard->logBlockedAction('users', 'read', [
                'controller' => __CLASS__,
                'action' => __FUNCTION__,
                'product_id' => $id,
            ]);
        }

        if (!$product) {
            flash('danger', 'Sản phẩm không tồn tại.');
            redirect('products');
        }

        $isCloudProduct = (new Category($this->config))->isCloudStorefrontCategory([
            'name' => $product['category_name'] ?? '',
            'slug' => $product['category_slug'] ?? '',
            'description' => $product['category_description'] ?? '',
        ]);
        $pageTitle = $product['name'] . ($isCloudProduct ? ' - Cấu hình VPS' : ' - Chi tiết sản phẩm');

        $this->view('products/show', [
            'title' => $pageTitle,
            'vpsUi' => true,
            'product' => $product,
            'images' => $productModel->images($id),
            'related' => $productModel->related((int) $product['category_id'], $id, 4),
            // Added: expose current wallet balance so checkout UI can reflect whether payment is allowed.
            'currentWalletBalance' => $currentWalletBalance,
            'isAuthenticated' => Auth::check(),
            'isCloudProduct' => $isCloudProduct,
        ]);
    }

    public function checkout(int $id): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('products/show/' . $id . '#checkout');

        $healthGuard = new ModuleHealthGuardService($this->config);
        if (!$healthGuard->isHealthy('products')) {
            $healthGuard->logBlockedAction('products', 'write', [
                'controller' => __CLASS__,
                'action' => __FUNCTION__,
                'product_id' => $id,
            ]);
            flash('danger', $healthGuard->messageFor('products', 'write'));
            redirect('products');
        }
        if (!$healthGuard->isHealthy('orders')) {
            $healthGuard->logBlockedAction('orders', 'write', [
                'controller' => __CLASS__,
                'action' => __FUNCTION__,
                'product_id' => $id,
            ]);
            flash('danger', $healthGuard->messageFor('orders', 'write'));
            redirect('products');
        }

        $productModel = new Product($this->config);
        $product = $productModel->find($id);

        if (!$product) {
            flash('danger', 'Sản phẩm không tồn tại hoặc đã bị gỡ.');
            redirect('products');
        }

        if ((string) ($product['status'] ?? 'inactive') !== 'active') {
            flash('danger', 'Sản phẩm hiện không khả dụng để thanh toán.');
            redirect('products/show/' . $id . '#checkout');
        }

        $baseConfig = $this->extractBaseConfig($product);
        $billingKey = validate_enum((string) ($_POST['billing_cycle'] ?? '3m'), ['1m', '3m', '12m'], '3m');
        $billingMultipliers = [
            '1m' => 1,
            '3m' => 3,
            '12m' => 12,
        ];
        $billingMultiplier = $billingMultipliers[$billingKey] ?? 3;

        $cpu = $this->sanitizeStepperValue((int) ($_POST['cpu'] ?? $baseConfig['cpu']), $baseConfig['cpu'], 16, 1);
        $ram = $this->sanitizeStepperValue((int) ($_POST['ram'] ?? $baseConfig['ram']), $baseConfig['ram'], 64, 2);
        $disk = $this->sanitizeStepperValue((int) ($_POST['disk'] ?? $baseConfig['disk']), $baseConfig['disk'], 500, 20);

        $selectedAddons = array_filter((array) ($_POST['addons'] ?? []), 'is_string');
        $addonPrices = [
            'backup' => 49000,
            'premium_support' => 79000,
            'private_ip' => 59000,
            'snapshot' => 39000,
            'vat' => 0,
        ];

        $upgradeMonthly = max(0, $cpu - $baseConfig['cpu']) * 35000;
        $upgradeMonthly += max(0, ($ram - $baseConfig['ram']) / 2) * 28000;
        $upgradeMonthly += max(0, ($disk - $baseConfig['disk']) / 20) * 9000;

        $addonMonthly = 0;
        foreach ($selectedAddons as $addonKey) {
            if (array_key_exists($addonKey, $addonPrices)) {
                $addonMonthly += (float) $addonPrices[$addonKey];
            }
        }

        $basePrice = (float) ($product['price'] ?? 0);
        $grandTotal = ($basePrice + $upgradeMonthly + $addonMonthly) * $billingMultiplier;
        if ($grandTotal <= 0) {
            flash('danger', 'Không thể tính được tổng thanh toán cho đơn hàng này.');
            redirect('products/show/' . $id . '#checkout');
        }

        // Added: ensure wallet schema exists before wallet-backed checkout executes.
        new WalletTransaction($this->config);
        $orderModel = new Order($this->config);
        $orderId = $orderModel->createProductOrder((int) Auth::id(), $id, $grandTotal, 'paid');

        if ($orderId === false) {
            if ($orderModel->lastError() === 'insufficient_balance') {
                flash('danger', 'Số dư ví không đủ để thanh toán đơn hàng này. Vui lòng nạp thêm số dư trước khi mua.');
                redirect('products/show/' . $id . '#checkout');
            }

            flash('danger', 'Không thể tạo đơn hàng lúc này. Vui lòng thử lại.');
            redirect('products/show/' . $id . '#checkout');
        }

        // Added: refresh session wallet balance so header/profile update immediately after spend.
        $this->refreshAuthUser(new User($this->config));

        flash('success', 'Thanh toán ví thành công. Đơn #' . $orderId . ' đã được ghi nhận và hệ thống đã trừ ' . format_money($grandTotal) . ' từ số dư hiện có.');

        if (Auth::can('backoffice.dashboard')) {
            redirect('admin/orders');
        }

        redirect('profile?tab=history');
    }

    private function extractBaseConfig(array $product): array
    {
        $specs = (string) ($product['specs'] ?? '');
        $baseCpu = 2;
        $baseRam = 4;
        $baseDisk = 80;

        if (preg_match('/CPU:\s*(\d+)/i', $specs, $cpuMatch)) {
            $baseCpu = (int) $cpuMatch[1];
        }

        if (preg_match('/RAM:\s*(\d+)/i', $specs, $ramMatch)) {
            $baseRam = (int) $ramMatch[1];
        }

        if (preg_match('/(?:SSD|NVMe|Storage):\s*(\d+)/i', $specs, $diskMatch)) {
            $baseDisk = (int) $diskMatch[1];
        }

        return [
            'cpu' => max(1, $baseCpu),
            'ram' => max(1, $baseRam),
            'disk' => max(20, $baseDisk),
        ];
    }

    private function sanitizeStepperValue(int $value, int $min, int $max, int $step): int
    {
        $normalized = max($min, min($max, $value));
        $delta = $normalized - $min;
        $aligned = $min + (int) floor($delta / max(1, $step)) * $step;
        return max($min, min($max, $aligned));
    }

    private function refreshAuthUser(User $userModel): void
    {
        $updated = $userModel->find((int) Auth::id());
        if (!$updated) {
            return;
        }

        $updated['session_token'] = (string) (Auth::user()['session_token'] ?? '');
        Auth::login($updated);
    }
}
