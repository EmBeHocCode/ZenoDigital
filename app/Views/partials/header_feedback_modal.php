<?php

use App\Core\Auth;

$headerFeedbackProductId = validate_int_range($headerFeedbackProductId ?? 0, 0, 999999999, 0);
$headerFeedbackProductName = sanitize_text((string) ($headerFeedbackProductName ?? ''), 180);
$headerFeedbackUser = Auth::user();
$isHeaderFeedbackLoggedIn = Auth::check();
$headerFeedbackAction = base_url('feedback/header/store');
?>

<div
    class="modal fade storefront-feedback-modal"
    id="storefrontHeaderFeedbackModal"
    tabindex="-1"
    aria-labelledby="storefrontHeaderFeedbackModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header storefront-feedback-modal__header">
                <div>
                    <span class="storefront-feedback-modal__eyebrow">Storefront Feedback</span>
                    <h2 class="modal-title h5 mb-1" id="storefrontHeaderFeedbackModalLabel">Gửi góp ý nhanh</h2>
                    <p class="storefront-feedback-modal__desc mb-0">Phản hồi từ header sẽ đi chung vào hệ thống feedback hiện tại để admin theo dõi và lọc theo nguồn.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="alert d-none storefront-feedback-alert" data-header-feedback-alert role="alert"></div>

                <form
                    action="<?= e($headerFeedbackAction) ?>"
                    method="post"
                    class="storefront-feedback-form"
                    data-header-feedback-form
                    novalidate
                >
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>" data-header-feedback-csrf>
                    <input type="hidden" name="source" value="storefront_header">
                    <input type="hidden" name="page_type" value="storefront_header">
                    <?php if ($headerFeedbackProductId > 0): ?>
                        <input type="hidden" name="product_id" value="<?= (int) $headerFeedbackProductId ?>">
                    <?php endif; ?>

                    <?php if ($isHeaderFeedbackLoggedIn): ?>
                        <div class="storefront-feedback-account mb-3">
                            <div class="storefront-feedback-account__label">Người gửi</div>
                            <div class="storefront-feedback-account__value"><?= e((string) ($headerFeedbackUser['full_name'] ?? 'Tài khoản hiện tại')) ?></div>
                            <div class="storefront-feedback-account__meta"><?= e((string) ($headerFeedbackUser['email'] ?? '')) ?></div>
                        </div>
                    <?php else: ?>
                        <div class="row g-3 mb-1">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="headerFeedbackFullName">Họ tên</label>
                                <input type="text" class="form-control" id="headerFeedbackFullName" name="full_name" maxlength="120" required placeholder="Nhập họ tên của bạn">
                                <div class="invalid-feedback">Vui lòng nhập họ tên.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="headerFeedbackEmail">Email</label>
                                <input type="email" class="form-control" id="headerFeedbackEmail" name="email" maxlength="120" required placeholder="you@example.com">
                                <div class="invalid-feedback">Vui lòng nhập email hợp lệ.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="headerFeedbackType">Loại feedback</label>
                            <select class="form-select" id="headerFeedbackType" name="feedback_type" required>
                                <option value="general">Góp ý chung</option>
                                <option value="product">Sản phẩm</option>
                                <option value="support">Hỗ trợ</option>
                                <option value="system_bug">Lỗi hệ thống</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="headerFeedbackSentiment">Cảm xúc</label>
                            <select class="form-select" id="headerFeedbackSentiment" name="sentiment">
                                <option value="neutral">Trung lập</option>
                                <option value="positive">Tích cực</option>
                                <option value="negative">Tiêu cực</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="headerFeedbackSeverity">Mức độ</label>
                            <select class="form-select" id="headerFeedbackSeverity" name="severity">
                                <option value="">Tự động</option>
                                <option value="low">Thấp</option>
                                <option value="medium">Trung bình</option>
                                <option value="high">Cao</option>
                            </select>
                        </div>
                    </div>

                    <?php if ($headerFeedbackProductId > 0): ?>
                        <div class="storefront-feedback-product mt-3">
                            <span class="storefront-feedback-product__label">Liên kết sản phẩm</span>
                            <strong><?= e($headerFeedbackProductName !== '' ? $headerFeedbackProductName : ('#' . $headerFeedbackProductId)) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <label class="form-label fw-semibold" for="headerFeedbackMessage">Nội dung feedback</label>
                        <textarea
                            class="form-control storefront-feedback-form__textarea"
                            id="headerFeedbackMessage"
                            name="message"
                            rows="5"
                            maxlength="2000"
                            required
                            placeholder="Ví dụ: trang sản phẩm này cần rõ hơn về thời gian bàn giao hoặc mình gặp lỗi khi thao tác."
                        ></textarea>
                        <div class="invalid-feedback">Vui lòng nhập nội dung feedback.</div>
                    </div>

                    <div class="storefront-feedback-form__footer mt-3">
                        <div class="storefront-feedback-form__hint">
                            Feedback này sẽ lưu vào cùng bảng `customer_feedback` và hiện ngay trong dashboard admin.
                        </div>
                        <button type="submit" class="btn btn-primary" data-header-feedback-submit>
                            <span class="default-label"><i class="fas fa-paper-plane me-1"></i>Gửi feedback</span>
                            <span class="loading-label d-none"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Đang gửi...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
