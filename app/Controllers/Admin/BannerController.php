<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\HeroBanner;

class BannerController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $model = new HeroBanner($this->config);
        $search = sanitize_text((string) ($_GET['q'] ?? ''), 120);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $model->paginated($search, $page, 10);

        $this->view('admin/banners/index', [
            'banners' => $result['data'],
            'meta' => $result['meta'],
            'search' => $search,
        ], 'admin');
    }

    public function create(): void
    {
        $this->requireAdmin();

        $this->view('admin/banners/create', [
            'banner' => [
                'status' => 'active',
                'display_order' => 0,
                'link_label' => 'Xem ngay',
            ],
        ], 'admin');
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/banners/create');

        $model = new HeroBanner($this->config);
        $payload = $this->bannerPayload($model);
        if ($payload === null) {
            redirect('admin/banners/create');
        }

        $id = $model->create($payload);
        if ($id !== false) {
            admin_audit('create', 'hero_banner', (int) $id, [
                'title' => $payload['title'],
                'image_path' => $payload['image_path'],
            ]);
        }

        flash($id !== false ? 'success' : 'danger', $id !== false ? 'Thêm banner thành công.' : 'Thêm banner thất bại.');
        redirect('admin/banners');
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();

        $model = new HeroBanner($this->config);
        $banner = $model->find($id);
        if (!$banner) {
            flash('danger', 'Banner không tồn tại.');
            redirect('admin/banners');
        }

        $this->view('admin/banners/edit', [
            'banner' => $banner,
        ], 'admin');
    }

    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/banners/edit/' . $id);

        $model = new HeroBanner($this->config);
        $current = $model->find($id);
        if (!$current) {
            flash('danger', 'Banner không tồn tại.');
            redirect('admin/banners');
        }

        $payload = $this->bannerPayload($model, (string) ($current['image_path'] ?? ''));
        if ($payload === null) {
            redirect('admin/banners/edit/' . $id);
        }

        $ok = $model->update($id, $payload);
        if ($ok) {
            admin_audit('update', 'hero_banner', $id, [
                'title' => $payload['title'],
                'image_path' => $payload['image_path'],
                'status' => $payload['status'],
            ]);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Cập nhật banner thành công.' : 'Cập nhật banner thất bại.');
        redirect('admin/banners');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/banners');

        $model = new HeroBanner($this->config);
        $ok = $model->delete($id);
        if ($ok) {
            admin_audit('delete', 'hero_banner', $id);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Xóa banner thành công.' : 'Xóa banner thất bại.');
        redirect('admin/banners');
    }

    public function toggleStatus(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/banners');

        $model = new HeroBanner($this->config);
        $ok = $model->toggleStatus($id);
        if ($ok) {
            admin_audit('toggle_status', 'hero_banner', $id);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Đã đổi trạng thái banner.' : 'Không thể đổi trạng thái banner.');
        redirect('admin/banners');
    }

    private function bannerPayload(HeroBanner $model, string $currentImage = ''): ?array
    {
        $title = sanitize_text((string) ($_POST['title'] ?? ''), 180);
        $subtitle = sanitize_text((string) ($_POST['subtitle'] ?? ''), 500);
        $linkLabel = sanitize_text((string) ($_POST['link_label'] ?? ''), 80);
        $linkUrl = $this->sanitizeBannerLink((string) ($_POST['link_url'] ?? ''));
        $displayOrder = validate_int_range($_POST['display_order'] ?? 0, 0, 999999, 0);
        $status = validate_enum((string) ($_POST['status'] ?? 'active'), ['active', 'inactive'], 'active');

        if ($title === '') {
            flash('danger', 'Tiêu đề banner là bắt buộc.');
            return null;
        }

        $imagePath = $this->uploadedSlideImage();
        if ($imagePath === null) {
            $imagePath = basename(trim((string) ($_POST['image_path'] ?? $currentImage)));
        }

        if ($imagePath === '' || !$model->imageExists($imagePath)) {
            flash('danger', 'Vui lòng upload ảnh banner hoặc nhập đúng tên file trong assets/images/slides.');
            return null;
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'image_path' => $imagePath,
            'link_label' => $linkLabel,
            'link_url' => $linkUrl,
            'display_order' => $displayOrder,
            'status' => $status,
        ];
    }

    private function uploadedSlideImage(): ?string
    {
        if (empty($_FILES['image_file']['name']) || ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES['image_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $maxSize = (int) config('upload.max_size', 2 * 1024 * 1024);
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxSize) {
            return null;
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, (array) config('upload.allowed_image_ext', []), true)) {
            return null;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mime === false || !in_array($mime, (array) config('upload.allowed_image_mime', []), true)) {
            return null;
        }

        $targetDir = BASE_PATH . '/public/assets/images/slides';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return null;
        }

        $fileName = bin2hex(random_bytes(12)) . '_slide.' . $ext;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            return null;
        }

        @chmod($targetPath, 0644);

        return $fileName;
    }

    private function sanitizeBannerLink(string $value): string
    {
        $value = trim(preg_replace('/[\r\n\t]+/', '', $value) ?? '');
        if ($value === '') {
            return '';
        }

        $value = mb_substr($value, 0, 500, 'UTF-8');
        $lower = strtolower($value);

        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
            return '';
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        if (str_starts_with($value, '#')) {
            return preg_match('/^#[a-zA-Z0-9_-]+$/', $value) === 1 ? $value : '';
        }

        if (str_starts_with($value, '/')) {
            return ltrim($value, '/');
        }

        if (preg_match('#^[a-zA-Z0-9_\-/]+(\?[a-zA-Z0-9=&%_.\-]+)?(#[a-zA-Z0-9_-]+)?$#', $value) === 1) {
            return $value;
        }

        return '';
    }
}
