<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ModuleHealthGuardService;

class HomeController extends Controller
{
    public function index(): void
    {
        $settingModel = new Setting($this->config);
        $healthGuard = new ModuleHealthGuardService($this->config);

        $categories = [];
        $cloudCategories = [];
        $secondaryCategories = [];
        $cloudFeaturedProducts = [];

        $categoryModel = new Category($this->config);
        if ($healthGuard->isHealthy('products')) {
            $productModel = new Product($this->config);
            if ($healthGuard->isHealthy('categories')) {
                $categories = $categoryModel->all();
                $categoryGroups = $categoryModel->storefrontGroups($categories);
                $cloudCategories = $categoryGroups['cloud'];
                $secondaryCategories = $categoryGroups['secondary'];
                $cloudCategoryIds = array_map(static fn (array $category): int => (int) ($category['id'] ?? 0), $cloudCategories);
                $cloudFeaturedProducts = $productModel->featuredByCategoryIds($cloudCategoryIds, 6);
            }

            if ($cloudFeaturedProducts === []) {
                $cloudFeaturedProducts = $productModel->featured(6);
            }
        } else {
            security_log('Bỏ qua khối featured products vì products schema unhealthy', [
                'issues' => $healthGuard->moduleStatus('products')['issues'] ?? [],
            ]);
        }

        if ($categories === [] && $healthGuard->isHealthy('categories')) {
            $categories = $categoryModel->all();
            $categoryGroups = $categoryModel->storefrontGroups($categories);
            $cloudCategories = $categoryGroups['cloud'];
            $secondaryCategories = $categoryGroups['secondary'];
        } elseif ($categories === []) {
            security_log('Bỏ qua khối categories vì categories schema unhealthy', [
                'issues' => $healthGuard->moduleStatus('categories')['issues'] ?? [],
            ]);
        }

        $this->view('home/index', [
            'cloudFeaturedProducts' => $cloudFeaturedProducts,
            'categories' => $categories,
            'cloudCategories' => $cloudCategories,
            'secondaryCategories' => $secondaryCategories,
            'siteSettings' => $settingModel->all(),
        ]);
    }
}
