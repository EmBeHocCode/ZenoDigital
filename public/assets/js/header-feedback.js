document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-header-feedback-form]');
    if (!form) {
        return;
    }

    const modalElement = document.getElementById('storefrontHeaderFeedbackModal');
    const submitButton = form.querySelector('[data-header-feedback-submit]');
    const alertBox = document.querySelector('[data-header-feedback-alert]');
    const csrfInput = form.querySelector('[data-header-feedback-csrf]');
    const messageInput = form.querySelector('#headerFeedbackMessage');
    let isSubmitting = false;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSubmitting) {
            return;
        }

        if (!form.reportValidity()) {
            form.classList.add('was-validated');
            return;
        }

        hideAlert();
        setSubmitting(true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: new FormData(form),
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));
            if (csrfInput && payload.csrf_token) {
                csrfInput.value = payload.csrf_token;
                csrfInput.defaultValue = payload.csrf_token;
            }

            if (!response.ok || !payload.success) {
                showAlert(payload.message || 'Không thể gửi feedback lúc này.', 'danger');
                return;
            }

            const code = payload?.data?.feedbackCode ? ` Mã phản hồi: ${payload.data.feedbackCode}.` : '';
            showAlert((payload.message || 'Đã gửi feedback thành công.') + code, 'success');
            form.classList.remove('was-validated');
            form.reset();
            if (csrfInput && payload.csrf_token) {
                csrfInput.value = payload.csrf_token;
                csrfInput.defaultValue = payload.csrf_token;
            }

            setTimeout(() => {
                if (messageInput) {
                    messageInput.focus();
                }
            }, 60);
        } catch (error) {
            showAlert('Có lỗi mạng khi gửi feedback. Vui lòng thử lại.', 'danger');
        } finally {
            setSubmitting(false);
        }
    });

    if (modalElement) {
        modalElement.addEventListener('hidden.bs.modal', () => {
            form.classList.remove('was-validated');
            hideAlert();
            setSubmitting(false);
        });
    }

    function setSubmitting(nextState) {
        isSubmitting = nextState;
        if (submitButton) {
            submitButton.disabled = nextState;
            const defaultLabel = submitButton.querySelector('.default-label');
            const loadingLabel = submitButton.querySelector('.loading-label');
            if (defaultLabel) {
                defaultLabel.classList.toggle('d-none', nextState);
            }
            if (loadingLabel) {
                loadingLabel.classList.toggle('d-none', !nextState);
            }
        }
    }

    function showAlert(message, type) {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert storefront-feedback-alert alert-${type}`;
        alertBox.textContent = String(message || '');
        alertBox.classList.remove('d-none');
    }

    function hideAlert() {
        if (!alertBox) {
            return;
        }

        alertBox.className = 'alert d-none storefront-feedback-alert';
        alertBox.textContent = '';
    }
});
