<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ModuleHealthGuardService;

class SeoController extends Controller
{
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /profile',
            'Disallow: /profile/',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /auth/google',
            'Disallow: /login/2fa',
            'Sitemap: ' . base_url('sitemap.xml'),
        ];

        echo implode("\n", $lines) . "\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');

        $urls = [
            [
                'loc' => base_url('/'),
                'lastmod' => null,
            ],
            [
                'loc' => base_url('products'),
                'lastmod' => null,
            ],
        ];

        $healthGuard = new ModuleHealthGuardService($this->config);

        if ($healthGuard->isHealthy('categories')) {
            $categoryModel = new Category($this->config);
            foreach ($categoryModel->sitemapEntries() as $category) {
                $urls[] = [
                    'loc' => base_url('products?' . http_build_query([
                        'category_id' => (int) ($category['id'] ?? 0),
                    ])),
                    'lastmod' => (string) ($category['updated_at'] ?? $category['created_at'] ?? ''),
                ];
            }
        }

        if ($healthGuard->isHealthy('products')) {
            $productModel = new Product($this->config);
            foreach ($productModel->sitemapEntries() as $product) {
                $urls[] = [
                    'loc' => base_url('products/show/' . (int) ($product['id'] ?? 0)),
                    'lastmod' => (string) ($product['updated_at'] ?? $product['created_at'] ?? ''),
                ];
            }
        }

        echo $this->renderSitemapXml($urls);
    }

    private function renderSitemapXml(array $urls): string
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $entry) {
            $loc = trim((string) ($entry['loc'] ?? ''));
            if ($loc === '') {
                continue;
            }

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . $this->xmlEscape($loc) . '</loc>';

            $lastmod = $this->normalizeLastModified((string) ($entry['lastmod'] ?? ''));
            if ($lastmod !== null) {
                $xml[] = '    <lastmod>' . $this->xmlEscape($lastmod) . '</lastmod>';
            }

            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml) . "\n";
    }

    private function normalizeLastModified(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('c', $timestamp);
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
