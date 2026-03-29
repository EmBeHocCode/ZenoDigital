<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\CustomerFeedback;

class FeedbackController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $search = sanitize_text((string) ($_GET['q'] ?? ''), 120);
        $status = validate_enum((string) ($_GET['status'] ?? ''), ['', 'new', 'reviewing', 'resolved', 'closed'], '');
        $sentiment = validate_enum((string) ($_GET['sentiment'] ?? ''), ['', 'positive', 'neutral', 'negative'], '');
        $source = sanitize_text((string) ($_GET['source'] ?? ''), 40);
        $pageType = sanitize_text((string) ($_GET['page_type'] ?? ''), 40);
        $page = validate_int_range($_GET['page'] ?? 1, 1, 999999, 1);

        $model = new CustomerFeedback($this->config);
        $sources = $model->distinctSources();
        $pageTypes = $model->distinctPageTypes();
        $source = validate_enum($source, array_merge([''], $sources), '');
        $pageType = validate_enum($pageType, array_merge([''], $pageTypes), '');
        $feedbackResult = $model->paginated($search, $status, $sentiment, $source, $pageType, $page, 20);

        $this->view('admin/feedback/index', [
            'summary' => $model->summary(),
            'feedbackItems' => $feedbackResult['data'],
            'meta' => $feedbackResult['meta'],
            'filterOptions' => [
                'sources' => $sources,
                'page_types' => $pageTypes,
            ],
            'filters' => [
                'q' => $search,
                'status' => $status,
                'sentiment' => $sentiment,
                'source' => $source,
                'page_type' => $pageType,
            ],
        ], 'admin');
    }
}
