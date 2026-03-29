<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data, string $layout, array $config): void
    {
        $viewFile = BASE_PATH . '/app/Views/' . $view . '.php';
        $layoutFile = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';

        if (!file_exists($viewFile) || !file_exists($layoutFile)) {
            http_response_code(500);
            exit('View or layout not found.');
        }

        extract($data, EXTR_SKIP);
        $content = $viewFile;
        require $layoutFile;
    }
}
