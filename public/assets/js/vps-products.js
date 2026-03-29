document.addEventListener('DOMContentLoaded', () => {
    const formatter = new Intl.NumberFormat('vi-VN');
    const formatMoney = (value) => `${formatter.format(Math.max(0, Math.round(value)))} ₫`;

    const planTypeInput = document.querySelector('[data-plan-type-input]');
    const planChips = document.querySelectorAll('[data-plan-chip]');

    if (planTypeInput && planChips.length) {
        planChips.forEach((chip) => {
            chip.addEventListener('click', () => {
                const value = chip.getAttribute('data-plan-chip') || '';
                const wasActive = chip.classList.contains('active');

                planChips.forEach((item) => item.classList.remove('active'));
                if (wasActive) {
                    planTypeInput.value = '';
                    return;
                }

                chip.classList.add('active');
                planTypeInput.value = value;
            });
        });
    }

    const stepperRoot = document.querySelector('[data-vps-stepper]');
    const summary = {
        basePriceEl: document.querySelector('[data-base-price]'),
        upgradeEl: document.querySelector('[data-upgrade-price]'),
        addonEl: document.querySelector('[data-addon-total]'),
        totalEl: document.querySelector('[data-grand-total]'),
        cycleEl: document.querySelector('[data-selected-cycle]'),
        currentConfigEl: document.querySelector('[data-current-config]'),
    };

    const getBillingInput = () => document.querySelector('input[name="billing_cycle"]:checked');

    const getBillingMultiplier = () => {
        const checked = getBillingInput();
        return checked ? Number(checked.getAttribute('data-multiplier') || 1) : 1;
    };

    const getBillingLabel = () => {
        const checked = getBillingInput();
        if (!checked) return '1 tháng';
        const card = checked.closest('.vps-radio-card');
        if (!card) return '1 tháng';
        const label = card.querySelector('span');
        return label ? label.textContent || '1 tháng' : '1 tháng';
    };

    const updateSummary = () => {
        if (!summary.basePriceEl || !summary.totalEl) return;

        const basePrice = Number(summary.basePriceEl.getAttribute('data-base-price') || 0);
        const multiplier = getBillingMultiplier();

        let upgradeMonthly = 0;
        let cpu = Number(stepperRoot ? stepperRoot.getAttribute('data-base-cpu') : 0);
        let ram = Number(stepperRoot ? stepperRoot.getAttribute('data-base-ram') : 0);
        let disk = Number(stepperRoot ? stepperRoot.getAttribute('data-base-disk') : 0);

        if (stepperRoot) {
            const items = stepperRoot.querySelectorAll('.vps-stepper-item');
            items.forEach((item) => {
                const valueEl = item.querySelector('[data-value]');
                const currentVal = Number(valueEl ? valueEl.textContent : 0);
                const minVal = Number(item.getAttribute('data-min') || 0);
                const step = Number(item.getAttribute('data-step') || 1);
                const priceStep = Number(item.getAttribute('data-price-step') || 0);
                const increment = Math.max(0, (currentVal - minVal) / step);
                upgradeMonthly += increment * priceStep;

                const key = item.getAttribute('data-key');
                if (key === 'cpu') cpu = currentVal;
                if (key === 'ram') ram = currentVal;
                if (key === 'disk') disk = currentVal;
            });
        }

        let addonMonthly = 0;
        document.querySelectorAll('input[name="addons[]"]').forEach((input) => {
            if (input instanceof HTMLInputElement && input.checked) {
                addonMonthly += Number(input.getAttribute('data-addon-price') || 0);
                const selectedAddonCard = input.closest('.vps-addon-item');
                if (selectedAddonCard) selectedAddonCard.classList.add('is-selected');
            } else {
                const unselectedAddonCard = input.closest('.vps-addon-item');
                if (unselectedAddonCard) unselectedAddonCard.classList.remove('is-selected');
            }
        });

        if (summary.currentConfigEl) {
            summary.currentConfigEl.textContent = `${cpu} vCPU · ${ram}GB RAM · ${disk}GB NVMe`;
        }
        if (summary.cycleEl) {
            summary.cycleEl.textContent = getBillingLabel();
        }
        if (summary.upgradeEl) {
            summary.upgradeEl.textContent = formatMoney(upgradeMonthly * multiplier);
        }
        if (summary.addonEl) {
            summary.addonEl.textContent = formatMoney(addonMonthly * multiplier);
        }

        const total = (basePrice + upgradeMonthly + addonMonthly) * multiplier;
        summary.totalEl.textContent = formatMoney(total);
        updateWalletState(total);
    };

    if (stepperRoot) {
        stepperRoot.querySelectorAll('.vps-step-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const container = button.closest('.vps-stepper-item');
                const valueEl = container ? container.querySelector('[data-value]') : null;
                if (!container || !valueEl) return;

                const step = Number(container.getAttribute('data-step') || 1);
                const min = Number(container.getAttribute('data-min') || 0);
                const max = Number(container.getAttribute('data-max') || 999);
                let value = Number(valueEl.textContent || 0);

                const action = button.getAttribute('data-action');
                value = action === 'plus' ? value + step : value - step;
                value = Math.max(min, Math.min(max, value));
                valueEl.textContent = String(value);
                updateSummary();
            });
        });
    }

    const billingInputs = document.querySelectorAll('input[name="billing_cycle"]');
    billingInputs.forEach((input) => {
        input.addEventListener('change', () => {
            document.querySelectorAll('.vps-radio-card').forEach((card) => card.classList.remove('is-selected'));
            const selectedBillingCard = input.closest('.vps-radio-card');
            if (selectedBillingCard) selectedBillingCard.classList.add('is-selected');
            updateSummary();
        });
    });

    const osInputs = document.querySelectorAll('input[name="os"]');
    osInputs.forEach((input) => {
        input.addEventListener('change', () => {
            document.querySelectorAll('.vps-os-item').forEach((item) => item.classList.remove('is-selected'));
            const selectedOsCard = input.closest('.vps-os-item');
            if (selectedOsCard) selectedOsCard.classList.add('is-selected');
        });
    });

    const addonInputs = document.querySelectorAll('input[name="addons[]"]');
    addonInputs.forEach((input) => {
        input.addEventListener('change', updateSummary);
    });

    const confirmModalEl = document.getElementById('vpsConfirmModal');
    const processingModalEl = document.getElementById('vpsProcessingModal');
    const successModalEl = document.getElementById('vpsSuccessModal');
    const errorModalEl = document.getElementById('vpsErrorModal');
    const checkoutForm = document.querySelector('[data-product-checkout-form]');
    const cpuInput = document.querySelector('[data-checkout-cpu]');
    const ramInput = document.querySelector('[data-checkout-ram]');
    const diskInput = document.querySelector('[data-checkout-disk]');

    const openConfirmBtn = document.querySelector('[data-open-confirm]');
    const startPaymentBtn = document.querySelector('[data-start-payment]');
    const payButtonText = document.querySelector('[data-pay-button-text]');
    const startPaymentText = document.querySelector('[data-start-payment-text]');
    const walletBox = document.querySelector('[data-wallet-box]');
    const walletNote = document.querySelector('[data-wallet-note]');
    const isWalletAuth = walletBox ? walletBox.getAttribute('data-wallet-auth') === '1' : false;
    const walletBalance = walletBox ? Number(walletBox.getAttribute('data-wallet-balance') || 0) : 0;
    const defaultPayLabel = payButtonText ? payButtonText.textContent || 'Thanh toán ngay' : 'Thanh toán ngay';
    const defaultStartLabel = startPaymentText ? startPaymentText.textContent || 'Bắt đầu thanh toán' : 'Bắt đầu thanh toán';

    const confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;
    const processingModal = processingModalEl ? new bootstrap.Modal(processingModalEl) : null;
    const successModal = successModalEl ? new bootstrap.Modal(successModalEl) : null;
    const errorModal = errorModalEl ? new bootstrap.Modal(errorModalEl) : null;

    const updateWalletState = (total = 0) => {
        if (!walletBox) return;

        let hasEnoughBalance = true;
        let noteText = '';

        if (!isWalletAuth) {
            noteText = 'Đăng nhập để thanh toán sản phẩm bằng số dư ví nội bộ.';
        } else if (walletBalance <= 0) {
            hasEnoughBalance = false;
            noteText = 'Số dư ví đang là 0 ₫. Hãy nạp thêm số dư trước khi mua hàng.';
        } else if (walletBalance < total) {
            hasEnoughBalance = false;
            noteText = `Số dư hiện có chưa đủ. Bạn cần nạp thêm ${formatMoney(total - walletBalance)} để thanh toán cấu hình này.`;
        } else {
            noteText = `Thanh toán sẽ trừ trực tiếp ${formatMoney(total)} từ số dư hiện có của bạn.`;
        }

        walletBox.classList.toggle('is-guest', !isWalletAuth);
        walletBox.classList.toggle('is-ready', isWalletAuth && hasEnoughBalance);
        walletBox.classList.toggle('is-insufficient', isWalletAuth && !hasEnoughBalance);

        if (walletNote) {
            walletNote.textContent = noteText;
        }

        if (openConfirmBtn && isWalletAuth) {
            openConfirmBtn.disabled = !hasEnoughBalance;
        }

        if (startPaymentBtn && isWalletAuth) {
            startPaymentBtn.disabled = !hasEnoughBalance;
        }

        if (payButtonText && isWalletAuth) {
            payButtonText.textContent = hasEnoughBalance ? defaultPayLabel : 'Số dư không đủ';
        }

        if (startPaymentText && isWalletAuth) {
            startPaymentText.textContent = hasEnoughBalance ? defaultStartLabel : 'Không thể thanh toán';
        }
    };

    if (openConfirmBtn && confirmModal) {
        openConfirmBtn.addEventListener('click', () => confirmModal.show());
    }

    if (startPaymentBtn && confirmModal && processingModal && checkoutForm) {
        startPaymentBtn.addEventListener('click', (event) => {
            event.preventDefault();
            confirmModal.hide();
            processingModal.show();

            setTimeout(() => {
                checkoutForm.requestSubmit();
            }, 120);
        });
    }

    if (stepperRoot && cpuInput && ramInput && diskInput) {
        const syncCheckoutConfig = () => {
            stepperRoot.querySelectorAll('.vps-stepper-item').forEach((item) => {
                const valueEl = item.querySelector('[data-value]');
                const currentVal = Number(valueEl ? valueEl.textContent : 0);
                const key = item.getAttribute('data-key');

                if (key === 'cpu') cpuInput.value = String(currentVal);
                if (key === 'ram') ramInput.value = String(currentVal);
                if (key === 'disk') diskInput.value = String(currentVal);
            });
        };

        stepperRoot.querySelectorAll('.vps-step-btn').forEach((button) => {
            button.addEventListener('click', () => {
                window.requestAnimationFrame(syncCheckoutConfig);
            });
        });

        syncCheckoutConfig();
    }

    updateSummary();
    updateWalletState(Number((summary.totalEl ? summary.totalEl.textContent : '0').replace(/[^\d]/g, '')));
});
