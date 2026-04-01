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
        $siteSettings = $settingModel->all();

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

        $siteName = app_site_name();
        $homeUrl = base_url('/');
        $siteLogo = trim((string) ($siteSettings['site_logo'] ?? ''));
        $siteLogoUrl = $siteLogo !== '' ? base_url('uploads/' . ltrim($siteLogo, '/')) : base_url('images/logo/zenox.png');
        $socialLinks = array_values(array_filter([
            trim((string) ($siteSettings['facebook_url'] ?? '')),
            trim((string) ($siteSettings['zalo_url'] ?? '')),
        ]));

        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $siteName,
                'url' => $homeUrl,
                'logo' => $siteLogoUrl,
                'sameAs' => $socialLinks,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $homeUrl,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => base_url('products?q={search_term_string}'),
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];

        $this->view('home/index', [
            'title' => $siteName . ' - Cloud VPS, Cloud Server và dịch vụ số',
            'metaDescription' => $siteName . ' cung cấp Cloud VPS, Cloud Server và dịch vụ số theo hướng cloud-first cho website, app, automation và workload production.',
            'canonicalUrl' => $homeUrl,
            'ogType' => 'website',
            'metaImageUrl' => $siteLogoUrl,
            'structuredData' => $structuredData,
            'cloudFeaturedProducts' => $cloudFeaturedProducts,
            'categories' => $categories,
            'cloudCategories' => $cloudCategories,
            'secondaryCategories' => $secondaryCategories,
            'siteSettings' => $siteSettings,
        ]);
    }
}
