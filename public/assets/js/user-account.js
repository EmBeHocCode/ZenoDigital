(() => {
    const initAvatarCropper = () => {
        const avatarInput = document.querySelector('[data-avatar-crop-input]');
        const avatarTriggerButton = document.querySelector('[data-avatar-editor-trigger]');
        const modalElement = document.querySelector('[data-avatar-crop-modal]');
        const cropImage = document.getElementById('ua-avatar-crop-image');
        const confirmButton = document.querySelector('[data-avatar-crop-confirm]');

        if (!avatarInput || !modalElement || !cropImage || !confirmButton || !window.bootstrap || !window.Cropper) {
            return;
        }

        const modal = new bootstrap.Modal(modalElement);
        let cropper = null;
        let objectUrl = '';
        let croppedApplied = false;

        if (avatarTriggerButton) {
            avatarTriggerButton.addEventListener('click', () => {
                avatarInput.click();
            });
        }

        const destroyCropper = () => {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = '';
            }
        };

        avatarInput.addEventListener('change', () => {
            const file = avatarInput.files && avatarInput.files[0] ? avatarInput.files[0] : null;
            if (!file) {
                return;
            }

            if (!file.type.startsWith('image/')) {
                return;
            }

            destroyCropper();
            croppedApplied = false;

            objectUrl = URL.createObjectURL(file);
            cropImage.src = objectUrl;
            modal.show();
        });

        modalElement.addEventListener('shown.bs.modal', () => {
            if (!cropImage.src) {
                return;
            }

            cropper = new Cropper(cropImage, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                responsive: true,
                background: false,
                guides: true,
                movable: true,
                zoomable: true,
                cropBoxResizable: true,
            });
        });

        modalElement.addEventListener('hidden.bs.modal', () => {
            destroyCropper();
            cropImage.removeAttribute('src');

            if (!croppedApplied) {
                avatarInput.value = '';
            }
        });

        confirmButton.addEventListener('click', () => {
            if (!cropper) {
                return;
            }

            const canvas = cropper.getCroppedCanvas({
                width: 512,
                height: 512,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            if (!canvas) {
                return;
            }

            canvas.toBlob((blob) => {
                if (!blob) {
                    return;
                }

                const croppedFile = new File([blob], 'avatar-cropped.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                const transfer = new DataTransfer();
                transfer.items.add(croppedFile);
                avatarInput.files = transfer.files;
                croppedApplied = true;

                const previewUrl = URL.createObjectURL(croppedFile);
                document.querySelectorAll('[data-avatar-preview]').forEach((image) => {
                    image.src = previewUrl;
                });

                modal.hide();
            }, 'image/jpeg', 0.92);
        });
    };

    const initBannerEditor = () => {
        if (window.ZenoxBannerStudioSkipLegacy === true && typeof window.ZenoxBannerStudioInit === 'function') {
            window.ZenoxBannerStudioInit();
            return;
        }

        const bannerInput = document.querySelector('[data-banner-editor-input]');
        const modalElement = document.querySelector('[data-banner-editor-modal]');
        const imageBox = document.querySelector('[data-banner-editor-image-box]');
        const videoBox = document.querySelector('[data-banner-editor-video-box]');
        const cropImage = document.getElementById('ua-banner-crop-image');
        const videoPreview = document.getElementById('ua-banner-video-preview');
        const startInput = document.getElementById('ua-banner-video-start');
        const endInput = document.getElementById('ua-banner-video-end');
        const confirmButton = document.querySelector('[data-banner-editor-confirm]');

        if (!bannerInput || !modalElement || !imageBox || !videoBox || !cropImage || !videoPreview || !startInput || !endInput || !confirmButton || !window.bootstrap) {
            return;
        }

        const modal = new bootstrap.Modal(modalElement);
        const profileHeader = document.querySelector('.ua-profile-header');
        const bannerTriggerButton = document.querySelector('[data-banner-editor-trigger]');

        let selectedFile = null;
        let selectedMode = '';
        let sourceObjectUrl = '';
        let cropper = null;
        let applySuccess = false;

        const clearSourceObjectUrl = () => {
            if (sourceObjectUrl) {
                URL.revokeObjectURL(sourceObjectUrl);
                sourceObjectUrl = '';
            }
        };

        const destroyImageCropper = () => {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        };

        const clearHeaderBannerMedia = () => {
            if (!profileHeader) {
                return;
            }

            profileHeader.querySelectorAll('[data-banner-header-media], [data-banner-header-image], [data-banner-header-video]').forEach((node) => {
                node.remove();
            });
        };

        const setBannerPreview = (url, type) => {
            if (!profileHeader) {
                return;
            }

            clearHeaderBannerMedia();

            const headerMedia = document.createElement(type === 'video' ? 'video' : 'img');
            headerMedia.className = 'ua-profile-header-media';
            headerMedia.setAttribute('data-banner-header-media', '1');

            if (type === 'video') {
                headerMedia.src = url;
                headerMedia.muted = true;
                headerMedia.autoplay = true;
                headerMedia.loop = true;
                headerMedia.playsInline = true;
            } else {
                headerMedia.src = url;
                headerMedia.alt = 'Banner';
            }

            profileHeader.prepend(headerMedia);
        };

        if (bannerTriggerButton) {
            bannerTriggerButton.addEventListener('click', () => {
                bannerInput.click();
            });
        }

        const chooseRecorderMimeType = () => {
            if (!window.MediaRecorder || typeof window.MediaRecorder.isTypeSupported !== 'function') {
                return '';
            }

            if (window.MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus')) {
                return 'video/webm;codecs=vp9,opus';
            }

            if (window.MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus')) {
                return 'video/webm;codecs=vp8,opus';
            }

            if (window.MediaRecorder.isTypeSupported('video/webm')) {
                return 'video/webm';
            }

            return '';
        };

        const seekVideo = (videoNode, time) => new Promise((resolve, reject) => {
            const onSeeked = () => {
                cleanup();
                resolve();
            };

            const onError = () => {
                cleanup();
                reject(new Error('Không thể tua video.'));
            };

            const cleanup = () => {
                videoNode.removeEventListener('seeked', onSeeked);
                videoNode.removeEventListener('error', onError);
            };

            videoNode.addEventListener('seeked', onSeeked, { once: true });
            videoNode.addEventListener('error', onError, { once: true });
            videoNode.currentTime = time;
        });

        const trimVideo = async(file, startAt, endAt) => {
            if (!window.MediaRecorder) {
                throw new Error('Trình duyệt chưa hỗ trợ cắt video.');
            }

            const streamFactory = videoPreview.captureStream || videoPreview.mozCaptureStream;
            if (typeof streamFactory !== 'function') {
                throw new Error('Trình duyệt chưa hỗ trợ capture stream video.');
            }

            const mimeType = chooseRecorderMimeType();
            if (mimeType === '') {
                throw new Error('Trình duyệt không hỗ trợ định dạng ghi video phù hợp.');
            }

            await seekVideo(videoPreview, startAt);

            const stream = streamFactory.call(videoPreview);
            const recorder = new MediaRecorder(stream, { mimeType });
            const chunks = [];

            const resultBlob = await new Promise((resolve, reject) => {
                let stopped = false;

                const stopSafely = () => {
                    if (stopped) {
                        return;
                    }

                    stopped = true;

                    try {
                        videoPreview.pause();
                        if (recorder.state !== 'inactive') {
                            recorder.stop();
                        }
                    } catch (error) {
                        reject(error);
                    }
                };

                const monitorPlayback = () => {
                    if (videoPreview.currentTime >= endAt) {
                        stopSafely();
                        return;
                    }

                    if (!stopped) {
                        requestAnimationFrame(monitorPlayback);
                    }
                };

                recorder.addEventListener('dataavailable', (event) => {
                    if (event.data && event.data.size > 0) {
                        chunks.push(event.data);
                    }
                });

                recorder.addEventListener('stop', () => {
                    const blob = new Blob(chunks, { type: mimeType });
                    resolve(blob);
                });

                recorder.addEventListener('error', () => {
                    reject(new Error('Không thể ghi lại đoạn video đã cắt.'));
                });

                recorder.start(200);

                videoPreview.play().then(() => {
                    requestAnimationFrame(monitorPlayback);
                }).catch((error) => {
                    reject(error);
                });
            });

            stream.getTracks().forEach((track) => track.stop());

            const baseName = (file.name || 'banner-video').replace(/\.[^.]+$/, '');
            return new File([resultBlob], `${baseName}-cut.webm`, {
                type: 'video/webm',
                lastModified: Date.now(),
            });
        };

        const openImageMode = () => {
            if (!window.Cropper) {
                window.alert('Không thể mở công cụ cắt ảnh bìa trên trình duyệt này.');
                bannerInput.value = '';
                return;
            }

            imageBox.classList.remove('d-none');
            videoBox.classList.add('d-none');
            clearSourceObjectUrl();
            sourceObjectUrl = URL.createObjectURL(selectedFile);
            cropImage.src = sourceObjectUrl;
            modal.show();
        };

        const openVideoMode = () => {
            imageBox.classList.add('d-none');
            videoBox.classList.remove('d-none');
            destroyImageCropper();
            clearSourceObjectUrl();
            sourceObjectUrl = URL.createObjectURL(selectedFile);
            videoPreview.src = sourceObjectUrl;
            videoPreview.load();
            modal.show();
        };

        bannerInput.addEventListener('change', () => {
            const file = bannerInput.files && bannerInput.files[0] ? bannerInput.files[0] : null;
            if (!file) {
                return;
            }

            selectedFile = file;
            selectedMode = file.type.startsWith('video/') ? 'video' : file.type.startsWith('image/') ? 'image' : '';
            applySuccess = false;

            if (selectedMode === 'image') {
                openImageMode();
                return;
            }

            if (selectedMode === 'video') {
                openVideoMode();
                return;
            }

            window.alert('Banner chỉ hỗ trợ ảnh hoặc video.');
            bannerInput.value = '';
        });

        videoPreview.addEventListener('loadedmetadata', () => {
            const duration = Number.isFinite(videoPreview.duration) ? videoPreview.duration : 0;
            startInput.value = '0';
            endInput.value = duration > 0 ? duration.toFixed(1) : '0';
            startInput.max = duration > 0 ? duration.toFixed(1) : '0';
            endInput.max = duration > 0 ? duration.toFixed(1) : '0';
        });

        modalElement.addEventListener('shown.bs.modal', () => {
            if (selectedMode !== 'image' || !cropImage.src) {
                return;
            }

            destroyImageCropper();
            cropper = new Cropper(cropImage, {
                aspectRatio: 16 / 5,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                responsive: true,
                background: false,
                guides: true,
                movable: true,
                zoomable: true,
                cropBoxResizable: true,
            });
        });

        modalElement.addEventListener('hidden.bs.modal', () => {
            destroyImageCropper();
            cropImage.removeAttribute('src');
            videoPreview.pause();
            videoPreview.removeAttribute('src');
            videoPreview.load();
            clearSourceObjectUrl();

            if (!applySuccess) {
                bannerInput.value = '';
            }
        });

        confirmButton.addEventListener('click', async() => {
            if (!selectedFile) {
                return;
            }

            if (selectedMode === 'image') {
                if (!cropper) {
                    return;
                }

                const canvas = cropper.getCroppedCanvas({
                    width: 1600,
                    height: 500,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (!canvas) {
                    return;
                }

                canvas.toBlob((blob) => {
                    if (!blob) {
                        return;
                    }

                    const croppedFile = new File([blob], 'banner-cropped.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    bannerInput.files = transfer.files;
                    applySuccess = true;

                    const previewUrl = URL.createObjectURL(croppedFile);
                    setBannerPreview(previewUrl, 'image');
                    modal.hide();
                }, 'image/jpeg', 0.92);

                return;
            }

            if (selectedMode === 'video') {
                const duration = Number.isFinite(videoPreview.duration) ? videoPreview.duration : 0;
                const startAt = Math.max(0, parseFloat(startInput.value || '0'));
                const endAt = Math.max(0, parseFloat(endInput.value || '0'));

                if (!Number.isFinite(startAt) || !Number.isFinite(endAt) || duration <= 0) {
                    window.alert('Không thể đọc thời lượng video để cắt. Vui lòng chọn lại file.');
                    return;
                }

                if (startAt >= endAt || endAt > duration) {
                    window.alert('Khoảng cắt video không hợp lệ.');
                    return;
                }

                confirmButton.disabled = true;

                try {
                    const shouldTrim = !(startAt <= 0.001 && Math.abs(endAt - duration) <= 0.15);
                    const outputFile = shouldTrim ? await trimVideo(selectedFile, startAt, endAt) : selectedFile;
                    const transfer = new DataTransfer();
                    transfer.items.add(outputFile);
                    bannerInput.files = transfer.files;
                    applySuccess = true;

                    const previewUrl = URL.createObjectURL(outputFile);
                    setBannerPreview(previewUrl, 'video');
                    modal.hide();
                } catch (error) {
                    const message = error instanceof Error ? error.message : 'Không thể cắt video trên trình duyệt hiện tại.';
                    window.alert(message);
                } finally {
                    confirmButton.disabled = false;
                }
            }
        });
    };

    const renderOtpQr = () => {
        const qrNode = document.querySelector('.ua-qr-render[data-otp-auth]');
        if (!qrNode) {
            return;
        }

        const otpAuth = qrNode.dataset.otpAuth || '';
        if (otpAuth === '') {
            return;
        }

        qrNode.innerHTML = '';

        if (!window.QRCode || typeof window.QRCode.toCanvas !== 'function') {
            qrNode.innerHTML = '<div class="text-secondary small text-center px-2">Không thể tải trình tạo QR.</div>';
            return;
        }

        const canvas = document.createElement('canvas');

        window.QRCode.toCanvas(canvas, otpAuth, {
            width: 170,
            margin: 1,
            color: {
                dark: '#111827',
                light: '#ffffff',
            },
            errorCorrectionLevel: 'M',
        }, (error) => {
            if (error) {
                qrNode.innerHTML = '<div class="text-secondary small text-center px-2">Không thể tạo QR.</div>';
                return;
            }

            qrNode.appendChild(canvas);
        });
    };

    const tabButtons = document.querySelectorAll('.ua-tab[data-ua-tab]');
    const tabTriggers = document.querySelectorAll('[data-ua-tab]');
    const sections = document.querySelectorAll('[data-ua-section]');

    const activateSection = (target) => {
        tabButtons.forEach((tab) => {
            tab.classList.toggle('is-active', tab.dataset.uaTab === target);
            if (tab.dataset.uaTab === target) {
                tab.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'center',
                });
            }
        });

        sections.forEach((section) => {
            section.classList.toggle('is-visible', section.dataset.uaSection === target);
        });
    };

    if (tabTriggers.length > 0 && sections.length > 0) {
        let initial = 'dashboard';
        const params = new URLSearchParams(window.location.search);
        if (params.has('tab')) {
            initial = params.get('tab') || initial;
        }

        if (!document.querySelector(`[data-ua-section="${initial}"]`)) {
            initial = 'dashboard';
        }

        activateSection(initial);

        tabTriggers.forEach((tab) => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.uaTab;
                activateSection(target);
                const url = new URL(window.location.href);
                url.searchParams.set('tab', target);
                window.history.replaceState({}, '', url);
            });
        });
    }

    const copyButtons = document.querySelectorAll('[data-copy-target]');
    copyButtons.forEach((button) => {
        button.addEventListener('click', async() => {
            const target = button.dataset.copyTarget;
            const input = document.querySelector(target);
            if (!input) {
                return;
            }

            try {
                await navigator.clipboard.writeText(input.value);
                const label = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check me-1"></i>Đã copy';
                setTimeout(() => {
                    button.innerHTML = label;
                }, 1500);
            } catch (_) {
                input.select();
                document.execCommand('copy');
            }
        });
    });

    const downloadButton = document.querySelector('[data-download-backup-codes]');
    const backupPanel = document.querySelector('.ua-backup-panel[data-backup-codes]');
    if (downloadButton && backupPanel) {
        downloadButton.addEventListener('click', () => {
            try {
                const codes = JSON.parse(backupPanel.dataset.backupCodes || '[]');
                if (!Array.isArray(codes) || codes.length === 0) {
                    return;
                }

                const content = [
                    'ZenoxDigital Backup Codes',
                    'Generated at: ' + new Date().toLocaleString(),
                    '',
                    ...codes,
                    '',
                    'Keep these codes in a safe place. Each code can be used once.'
                ].join('\n');

                const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = 'zenox-backup-codes.txt';
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
                URL.revokeObjectURL(url);
            } catch (_) {
                // no-op
            }
        });
    }

    // Added: quick top-up presets and payment method state for wallet UI.
    const walletAmountInput = document.querySelector('[data-wallet-amount-input]');
    const walletPresetButtons = document.querySelectorAll('[data-wallet-preset]');
    const walletMethodInputs = document.querySelectorAll('.ua-wallet-method-input');

    if (walletAmountInput && walletPresetButtons.length > 0) {
        const syncWalletPresetState = () => {
            const normalizedValue = String(parseInt(walletAmountInput.value || '0', 10) || 0);
            walletPresetButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.walletPreset === normalizedValue);
            });
        };

        walletPresetButtons.forEach((button) => {
            button.addEventListener('click', () => {
                walletAmountInput.value = button.dataset.walletPreset || '';
                walletAmountInput.dispatchEvent(new Event('input', { bubbles: true }));
                syncWalletPresetState();
            });
        });

        walletAmountInput.addEventListener('input', syncWalletPresetState);
        syncWalletPresetState();
    }

    if (walletMethodInputs.length > 0) {
        const syncWalletMethodState = () => {
            walletMethodInputs.forEach((input) => {
                const methodCard = input.closest('.ua-wallet-method');
                if (!methodCard) {
                    return;
                }

                methodCard.classList.toggle('is-active', input.checked);
            });
        };

        walletMethodInputs.forEach((input) => {
            input.addEventListener('change', syncWalletMethodState);
        });

        syncWalletMethodState();
    }

    const initWalletTopupModal = () => {
        const modalElement = document.querySelector('[data-wallet-topup-modal]');
        if (!modalElement || !window.bootstrap) {
            return;
        }

        const openButtons = document.querySelectorAll('[data-wallet-topup-open]');
        const refreshButton = modalElement.querySelector('[data-wallet-status-refresh]');
        const pendingState = modalElement.querySelector('[data-wallet-topup-state="pending"]');
        const successState = modalElement.querySelector('[data-wallet-topup-state="success"]');
        const failedState = modalElement.querySelector('[data-wallet-topup-state="failed"]');
        const successAmount = modalElement.querySelector('[data-wallet-success-amount]');
        const successBalance = modalElement.querySelector('[data-wallet-success-balance]');
        const statusUrl = modalElement.dataset.statusUrl || '';
        const refreshUrl = modalElement.dataset.refreshUrl || window.location.href;
        const autoOpen = modalElement.dataset.autoOpen === '1';
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

        let pollTimer = null;
        let successLocked = false;

        const stopPolling = () => {
            if (pollTimer) {
                window.clearTimeout(pollTimer);
                pollTimer = null;
            }
        };

        const setState = (state) => {
            modalElement.dataset.walletTopupState = state;

            if (pendingState) {
                pendingState.classList.toggle('d-none', state !== 'pending');
            }

            if (successState) {
                successState.classList.toggle('d-none', state !== 'success');
            }

            if (failedState) {
                failedState.classList.toggle('d-none', state !== 'failed');
            }

            if (refreshButton) {
                const isSuccess = state === 'success';
                refreshButton.classList.toggle('btn-outline-primary', !isSuccess);
                refreshButton.classList.toggle('btn-primary', isSuccess);
                refreshButton.innerHTML = isSuccess
                    ? '<i class="fas fa-check me-1"></i>Xem số dư mới'
                    : '<i class="fas fa-rotate-right me-1"></i>Làm mới trang';
            }
        };

        const schedulePoll = (delay = 2500) => {
            stopPolling();
            pollTimer = window.setTimeout(checkStatus, delay);
        };

        const goToWalletHistory = (delay = 1600) => {
            window.setTimeout(() => {
                window.location.href = refreshUrl;
            }, delay);
        };

        const handleStatusPayload = (payload) => {
            const transaction = payload && payload.transaction ? payload.transaction : null;
            const wallet = payload && payload.wallet ? payload.wallet : null;
            const status = transaction && transaction.status ? transaction.status : 'pending';

            if (status === 'completed') {
                successLocked = true;
                if (successAmount && transaction.amount_formatted) {
                    successAmount.textContent = transaction.amount_formatted;
                }
                if (successBalance && wallet && wallet.current_balance_formatted) {
                    successBalance.textContent = wallet.current_balance_formatted;
                }
                setState('success');
                stopPolling();
                goToWalletHistory();
                return;
            }

            if (status === 'failed') {
                setState('failed');
                stopPolling();
                return;
            }

            setState('pending');
            if (modalElement.classList.contains('show') && !successLocked) {
                schedulePoll();
            }
        };

        async function checkStatus() {
            if (!statusUrl || successLocked) {
                return;
            }

            stopPolling();

            try {
                const response = await window.fetch(statusUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error('status_fetch_failed');
                }

                const payload = await response.json();
                if (!payload || payload.success !== true) {
                    throw new Error('status_payload_failed');
                }

                handleStatusPayload(payload);
            } catch (_) {
                if (modalElement.classList.contains('show') && !successLocked) {
                    schedulePoll(4000);
                }
            }
        }

        openButtons.forEach((button) => {
            button.addEventListener('click', () => {
                if (successLocked) {
                    setState('success');
                } else {
                    setState('pending');
                }
                modal.show();
            });
        });

        modalElement.addEventListener('shown.bs.modal', () => {
            if (!successLocked) {
                setState('pending');
                checkStatus();
            }
        });

        modalElement.addEventListener('hidden.bs.modal', () => {
            if (!successLocked) {
                stopPolling();
            }
        });

        if (autoOpen) {
            modal.show();
        }
    };

    renderOtpQr();
    initAvatarCropper();
    initBannerEditor();
    initWalletTopupModal();
})();
