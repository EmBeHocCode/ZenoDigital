(() => {
    window.ZenoxBannerStudioSkipLegacy = true;

    window.ZenoxBannerStudioInit = () => {
        if (window.__zenoxBannerStudioReady) {
            return;
        }

        const bannerInput = document.querySelector('[data-banner-editor-input]');
        const modalElement = document.querySelector('[data-banner-editor-modal]');
        const imageBox = document.querySelector('[data-banner-editor-image-box]');
        const videoBox = document.querySelector('[data-banner-editor-video-box]');
        const emptyBox = document.querySelector('[data-banner-editor-empty-box]');
        const cropImage = document.getElementById('ua-banner-crop-image');
        const videoPreview = document.getElementById('ua-banner-video-preview');
        const startInput = document.getElementById('ua-banner-video-start');
        const endInput = document.getElementById('ua-banner-video-end');
        const confirmButton = document.querySelector('[data-banner-editor-confirm]');
        const previewRoot = document.querySelector('[data-banner-editor-preview-root]');
        const previewEmpty = document.querySelector('[data-banner-editor-preview-empty]');
        const profileHeader = document.querySelector('[data-banner-header-root]');
        const bannerTriggerButton = document.querySelector('[data-banner-editor-trigger]');
        const selectButtons = document.querySelectorAll('[data-banner-editor-select-media]');
        const resetButton = document.querySelector('[data-banner-editor-reset]');
        const fitButtons = Array.from(document.querySelectorAll('[data-banner-fit-option]'));
        const heightButtons = Array.from(document.querySelectorAll('[data-banner-height-option]'));
        const rangeControls = {
            zoom: document.querySelector('[data-banner-control="zoom"]'),
            position_x: document.querySelector('[data-banner-control="position_x"]'),
            position_y: document.querySelector('[data-banner-control="position_y"]'),
        };
        const rangeOutputs = {
            zoom: document.querySelector('[data-banner-output="zoom"]'),
            position_x: document.querySelector('[data-banner-output="position_x"]'),
            position_y: document.querySelector('[data-banner-output="position_y"]'),
        };
        const metaInputs = {
            fit_mode: document.querySelector('[data-banner-meta-input="fit_mode"]'),
            zoom: document.querySelector('[data-banner-meta-input="zoom"]'),
            position_x: document.querySelector('[data-banner-meta-input="position_x"]'),
            position_y: document.querySelector('[data-banner-meta-input="position_y"]'),
            height_mode: document.querySelector('[data-banner-meta-input="height_mode"]'),
            video_start: document.querySelector('[data-banner-meta-input="video_start"]'),
            video_end: document.querySelector('[data-banner-meta-input="video_end"]'),
        };

        if (!bannerInput || !modalElement || !imageBox || !videoBox || !emptyBox || !cropImage || !videoPreview || !startInput || !endInput || !confirmButton || !previewRoot || !previewEmpty || !profileHeader || !window.bootstrap) {
            return;
        }

        window.__zenoxBannerStudioReady = true;

        const modal = new bootstrap.Modal(modalElement);
        const heightVars = {
            narrow: { desktop: '184px', mobile: '152px' },
            standard: { desktop: '220px', mobile: '180px' },
            tall: { desktop: '276px', mobile: '228px' },
        };
        const defaultMeta = {
            fit_mode: 'cover',
            zoom: 1,
            position_x: 50,
            position_y: 50,
            height_mode: 'standard',
            video_start: 0,
            video_end: 0,
        };

        let cropper = null;
        let selectedMode = '';
        let sourceObjectUrl = '';
        let previewCropDataUrl = '';
        let applySuccess = false;
        let cropPreviewTimer = 0;
        let selectedFile = null;
        let sessionFileSnapshot = null;

        const normalizeType = (value) => {
            const normalized = (value || '').toString().trim().toLowerCase();
            return normalized === 'image' || normalized === 'video' ? normalized : '';
        };

        const clamp = (value, min, max, fallback) => {
            const parsed = Number.parseFloat(value);
            if (!Number.isFinite(parsed)) {
                return fallback;
            }

            return Math.min(max, Math.max(min, parsed));
        };

        const formatValue = (value, digits = 3) => {
            const fixed = clamp(value, -99999, 99999, 0).toFixed(digits);
            const trimmed = fixed.replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.0+$/, '').replace(/\.$/, '');
            return trimmed === '' ? '0' : trimmed;
        };

        const readMeta = () => ({
            fit_mode: ['cover', 'contain', 'fill'].includes(metaInputs.fit_mode?.value || '') ? metaInputs.fit_mode.value : defaultMeta.fit_mode,
            zoom: clamp(metaInputs.zoom?.value, 1, 2.8, defaultMeta.zoom),
            position_x: clamp(metaInputs.position_x?.value, 0, 100, defaultMeta.position_x),
            position_y: clamp(metaInputs.position_y?.value, 0, 100, defaultMeta.position_y),
            height_mode: ['narrow', 'standard', 'tall'].includes(metaInputs.height_mode?.value || '') ? metaInputs.height_mode.value : defaultMeta.height_mode,
            video_start: Math.max(0, clamp(metaInputs.video_start?.value, 0, 86400, defaultMeta.video_start)),
            video_end: Math.max(0, clamp(metaInputs.video_end?.value, 0, 86400, defaultMeta.video_end)),
        });

        let editorMeta = readMeta();

        const destroyCropper = () => {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        };

        const clearObjectUrl = () => {
            if (sourceObjectUrl) {
                URL.revokeObjectURL(sourceObjectUrl);
                sourceObjectUrl = '';
            }
        };

        const clearMediaNodes = (root, selector) => {
            root.querySelectorAll(selector).forEach((node) => {
                node.remove();
            });
        };

        const currentBanner = () => ({
            url: (profileHeader.dataset.bannerCurrentUrl || '').trim(),
            type: normalizeType(profileHeader.dataset.bannerCurrentType),
        });

        const currentDuration = () => {
            const duration = Number.isFinite(videoPreview.duration) ? videoPreview.duration : 0;
            return duration > 0 ? duration : 0;
        };

        const updateOutputs = () => {
            if (rangeOutputs.zoom) {
                rangeOutputs.zoom.textContent = `${editorMeta.zoom.toFixed(2)}x`;
            }
            if (rangeOutputs.position_x) {
                rangeOutputs.position_x.textContent = `${Math.round(editorMeta.position_x)}%`;
            }
            if (rangeOutputs.position_y) {
                rangeOutputs.position_y.textContent = `${Math.round(editorMeta.position_y)}%`;
            }
        };

        const syncControlState = () => {
            if (rangeControls.zoom) {
                rangeControls.zoom.value = editorMeta.zoom.toFixed(2);
            }
            if (rangeControls.position_x) {
                rangeControls.position_x.value = Math.round(editorMeta.position_x).toString();
            }
            if (rangeControls.position_y) {
                rangeControls.position_y.value = Math.round(editorMeta.position_y).toString();
            }

            fitButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.bannerFitOption === editorMeta.fit_mode);
            });
            heightButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.bannerHeightOption === editorMeta.height_mode);
            });

            updateOutputs();
        };

        const applyVars = (root) => {
            const heightMode = heightVars[editorMeta.height_mode] ? editorMeta.height_mode : defaultMeta.height_mode;
            root.style.setProperty('--ua-banner-height', heightVars[heightMode].desktop);
            root.style.setProperty('--ua-banner-height-mobile', heightVars[heightMode].mobile);
            root.style.setProperty('--ua-banner-fit', editorMeta.fit_mode);
            root.style.setProperty('--ua-banner-pos-x', `${editorMeta.position_x}%`);
            root.style.setProperty('--ua-banner-pos-y', `${editorMeta.position_y}%`);
            root.style.setProperty('--ua-banner-scale', editorMeta.zoom.toString());
        };

        const bindVideoWindow = (videoNode, startAt, endAt) => {
            let seekDone = false;

            const seekToStart = () => {
                if (seekDone || !Number.isFinite(videoNode.duration) || videoNode.duration <= 0) {
                    return;
                }

                seekDone = true;
                if (startAt > 0) {
                    try {
                        videoNode.currentTime = Math.min(startAt, videoNode.duration);
                    } catch (error) {
                        seekDone = false;
                    }
                }
            };

            videoNode.addEventListener('loadedmetadata', seekToStart);
            videoNode.addEventListener('timeupdate', () => {
                if (endAt > startAt && videoNode.currentTime >= endAt) {
                    videoNode.currentTime = startAt > 0 ? startAt : 0;
                    if (!videoNode.paused) {
                        videoNode.play().catch(() => {});
                    }
                }
            });

            if (videoNode.readyState >= 1) {
                seekToStart();
            }
        };

        const createMediaNode = (url, type, attributeName) => {
            const node = document.createElement(type === 'video' ? 'video' : 'img');
            node.className = 'ua-profile-header-media';
            node.setAttribute(attributeName, '1');

            if (type === 'video') {
                node.src = url;
                node.muted = true;
                node.autoplay = true;
                node.loop = true;
                node.playsInline = true;
                node.preload = 'metadata';
            } else {
                node.src = url;
                node.alt = 'Banner';
            }

            return node;
        };

        const resolvePreviewSource = () => {
            if (selectedMode === 'image' && previewCropDataUrl) {
                return { url: previewCropDataUrl, type: 'image' };
            }

            if (sourceObjectUrl && selectedMode) {
                return { url: sourceObjectUrl, type: selectedMode };
            }

            return currentBanner();
        };

        const renderPreview = () => {
            const source = resolvePreviewSource();
            clearMediaNodes(previewRoot, '[data-banner-preview-media]');
            applyVars(previewRoot);

            if (!source.url || !source.type) {
                previewRoot.classList.add('is-empty');
                previewEmpty.classList.remove('d-none');
                return;
            }

            previewRoot.classList.remove('is-empty');
            previewEmpty.classList.add('d-none');

            const media = createMediaNode(source.url, source.type, 'data-banner-preview-media');
            previewRoot.prepend(media);

            if (source.type === 'video') {
                bindVideoWindow(media, editorMeta.video_start, editorMeta.video_end);
            }
        };

        const renderHeader = (url, type) => {
            clearMediaNodes(profileHeader, '[data-banner-header-media], [data-banner-header-image], [data-banner-header-video]');
            applyVars(profileHeader);

            profileHeader.dataset.bannerCurrentUrl = url || '';
            profileHeader.dataset.bannerCurrentType = type || '';
            profileHeader.dataset.bannerFitMode = editorMeta.fit_mode;
            profileHeader.dataset.bannerHeightMode = editorMeta.height_mode;
            profileHeader.dataset.bannerZoom = formatValue(editorMeta.zoom, 3);
            profileHeader.dataset.bannerPositionX = formatValue(editorMeta.position_x, 3);
            profileHeader.dataset.bannerPositionY = formatValue(editorMeta.position_y, 3);
            profileHeader.dataset.bannerVideoStart = formatValue(editorMeta.video_start, 3);
            profileHeader.dataset.bannerVideoEnd = formatValue(editorMeta.video_end, 3);

            if (!url || !type) {
                return;
            }

            const headerMedia = createMediaNode(url, type, 'data-banner-header-media');
            if (type === 'video') {
                headerMedia.setAttribute('data-banner-header-video', '1');
                headerMedia.setAttribute('data-banner-video-start', formatValue(editorMeta.video_start, 3));
                headerMedia.setAttribute('data-banner-video-end', formatValue(editorMeta.video_end, 3));
                bindVideoWindow(headerMedia, editorMeta.video_start, editorMeta.video_end);
            } else {
                headerMedia.setAttribute('data-banner-header-image', '1');
            }

            profileHeader.prepend(headerMedia);
        };

        const syncVideoInputs = () => {
            const duration = currentDuration();
            const displayStart = Math.max(0, editorMeta.video_start);
            const displayEnd = editorMeta.video_end > 0 ? editorMeta.video_end : duration;
            const maxValue = duration > 0 ? duration.toFixed(1) : '0';

            startInput.max = maxValue;
            endInput.max = maxValue;
            startInput.value = displayStart.toFixed(1);
            endInput.value = (displayEnd > 0 ? displayEnd : 0).toFixed(1);
        };

        const syncMetaInputs = () => {
            const duration = currentDuration();
            const normalizedStart = editorMeta.video_start <= 0.05 ? 0 : editorMeta.video_start;
            let normalizedEnd = editorMeta.video_end;

            if (duration > 0 && Math.abs(editorMeta.video_end - duration) <= 0.15) {
                normalizedEnd = 0;
            }
            if (normalizedEnd <= 0.05) {
                normalizedEnd = 0;
            }

            if (metaInputs.fit_mode) {
                metaInputs.fit_mode.value = editorMeta.fit_mode;
            }
            if (metaInputs.zoom) {
                metaInputs.zoom.value = formatValue(editorMeta.zoom, 3);
            }
            if (metaInputs.position_x) {
                metaInputs.position_x.value = formatValue(editorMeta.position_x, 3);
            }
            if (metaInputs.position_y) {
                metaInputs.position_y.value = formatValue(editorMeta.position_y, 3);
            }
            if (metaInputs.height_mode) {
                metaInputs.height_mode.value = editorMeta.height_mode;
            }
            if (metaInputs.video_start) {
                metaInputs.video_start.value = formatValue(normalizedStart, 3);
            }
            if (metaInputs.video_end) {
                metaInputs.video_end.value = formatValue(normalizedEnd, 3);
            }
        };

        const showMode = (mode) => {
            selectedMode = mode;
            emptyBox.classList.toggle('d-none', !!mode);
            imageBox.classList.toggle('d-none', mode !== 'image');
            videoBox.classList.toggle('d-none', mode !== 'video');
        };

        const scheduleImagePreview = () => {
            window.clearTimeout(cropPreviewTimer);
            cropPreviewTimer = window.setTimeout(() => {
                if (!cropper) {
                    previewCropDataUrl = sourceObjectUrl;
                    renderPreview();
                    return;
                }

                const canvas = cropper.getCroppedCanvas({
                    width: 960,
                    height: 300,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                previewCropDataUrl = canvas ? canvas.toDataURL('image/jpeg', 0.86) : sourceObjectUrl;
                renderPreview();
            }, 120);
        };

        const mountCropper = () => {
            if (selectedMode !== 'image' || !cropImage.src) {
                return;
            }

            destroyCropper();
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
                ready() {
                    scheduleImagePreview();
                },
                crop() {
                    scheduleImagePreview();
                },
            });
        };

        const openEditor = (url, type) => {
            const normalizedType = normalizeType(type);
            applySuccess = false;
            previewCropDataUrl = '';

            if (!normalizedType || !url) {
                showMode('');
                renderPreview();
                modal.show();
                return;
            }

            if (normalizedType === 'image' && !window.Cropper) {
                window.alert('Không thể mở công cụ cắt ảnh bìa trên trình duyệt này.');
                return;
            }

            showMode(normalizedType);
            destroyCropper();

            if (normalizedType === 'image') {
                cropImage.src = url;
                videoPreview.pause();
                videoPreview.removeAttribute('src');
                videoPreview.load();
            } else {
                destroyCropper();
                cropImage.removeAttribute('src');
                videoPreview.src = url;
                videoPreview.load();
            }

            renderPreview();
            modal.show();

            if (modalElement.classList.contains('show') && normalizedType === 'image') {
                mountCropper();
            }
        };

        const beginEditorSession = () => {
            editorMeta = readMeta();
            syncControlState();
            sessionFileSnapshot = bannerInput.files && bannerInput.files[0] ? bannerInput.files[0] : null;

            if (sessionFileSnapshot) {
                selectedFile = sessionFileSnapshot;
                clearObjectUrl();
                sourceObjectUrl = URL.createObjectURL(sessionFileSnapshot);
                openEditor(sourceObjectUrl, sessionFileSnapshot.type.startsWith('video/') ? 'video' : 'image');
                return;
            }

            selectedFile = null;
            const source = currentBanner();
            openEditor(source.url, source.type);
        };

        selectButtons.forEach((button) => {
            button.addEventListener('click', () => {
                bannerInput.click();
            });
        });

        if (bannerTriggerButton) {
            bannerTriggerButton.addEventListener('click', () => {
                beginEditorSession();
            });
        }

        bannerInput.addEventListener('change', () => {
            const file = bannerInput.files && bannerInput.files[0] ? bannerInput.files[0] : null;
            if (!file) {
                return;
            }

            const nextMode = file.type.startsWith('video/') ? 'video' : file.type.startsWith('image/') ? 'image' : '';
            if (!nextMode) {
                window.alert('Banner chỉ hỗ trợ ảnh hoặc video.');
                bannerInput.value = '';
                return;
            }

            selectedFile = file;
            clearObjectUrl();
            sourceObjectUrl = URL.createObjectURL(file);
            openEditor(sourceObjectUrl, nextMode);
        });

        fitButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const nextFit = button.dataset.bannerFitOption || defaultMeta.fit_mode;
                editorMeta.fit_mode = ['cover', 'contain', 'fill'].includes(nextFit) ? nextFit : defaultMeta.fit_mode;
                syncControlState();
                renderPreview();
            });
        });

        heightButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const nextHeight = button.dataset.bannerHeightOption || defaultMeta.height_mode;
                editorMeta.height_mode = ['narrow', 'standard', 'tall'].includes(nextHeight) ? nextHeight : defaultMeta.height_mode;
                syncControlState();
                renderPreview();
            });
        });

        Object.entries(rangeControls).forEach(([key, control]) => {
            if (!control) {
                return;
            }

            control.addEventListener('input', () => {
                if (key === 'zoom') {
                    editorMeta.zoom = clamp(control.value, 1, 2.8, defaultMeta.zoom);
                }
                if (key === 'position_x') {
                    editorMeta.position_x = clamp(control.value, 0, 100, defaultMeta.position_x);
                }
                if (key === 'position_y') {
                    editorMeta.position_y = clamp(control.value, 0, 100, defaultMeta.position_y);
                }

                updateOutputs();
                renderPreview();
            });
        });

        const syncVideoMetaFromFields = () => {
            const duration = currentDuration();
            const startAt = Math.max(0, clamp(startInput.value, 0, duration > 0 ? duration : 86400, 0));
            let endAt = Math.max(0, clamp(endInput.value, 0, duration > 0 ? duration : 86400, duration > 0 ? duration : 0));

            if (duration > 0 && endAt === 0) {
                endAt = duration;
            }

            if (duration > 0) {
                endAt = Math.min(duration, Math.max(startAt, endAt));
            }

            editorMeta.video_start = startAt;
            editorMeta.video_end = endAt;
            startInput.value = startAt.toFixed(1);
            endInput.value = endAt.toFixed(1);
            renderPreview();
        };

        startInput.addEventListener('input', syncVideoMetaFromFields);
        endInput.addEventListener('input', syncVideoMetaFromFields);

        if (resetButton) {
            resetButton.addEventListener('click', () => {
                editorMeta = { ...defaultMeta };
                if (selectedMode === 'video') {
                    editorMeta.video_end = currentDuration();
                    syncVideoInputs();
                } else {
                    editorMeta.video_start = 0;
                    editorMeta.video_end = 0;
                }

                if (selectedMode === 'image' && cropper) {
                    cropper.reset();
                    scheduleImagePreview();
                }

                syncControlState();
                renderPreview();
            });
        }

        videoPreview.addEventListener('loadedmetadata', () => {
            if (editorMeta.video_end <= 0 && currentDuration() > 0) {
                editorMeta.video_end = currentDuration();
            }

            syncVideoInputs();
            renderPreview();
        });

        modalElement.addEventListener('shown.bs.modal', () => {
            if (selectedMode !== 'image' || !cropImage.src) {
                renderPreview();
                return;
            }

            mountCropper();
        });

        modalElement.addEventListener('hidden.bs.modal', () => {
            destroyCropper();
            cropImage.removeAttribute('src');
            videoPreview.pause();
            videoPreview.removeAttribute('src');
            videoPreview.load();
            window.clearTimeout(cropPreviewTimer);
            previewCropDataUrl = '';
            clearObjectUrl();

            if (!applySuccess) {
                if (sessionFileSnapshot) {
                    const transfer = new DataTransfer();
                    transfer.items.add(sessionFileSnapshot);
                    bannerInput.files = transfer.files;
                } else {
                    bannerInput.value = '';
                }
            }

            selectedFile = null;
            selectedMode = '';
            sessionFileSnapshot = null;
            showMode('');
            renderPreview();
        });

        confirmButton.addEventListener('click', () => {
            const source = resolvePreviewSource();
            if (!source.url || !source.type) {
                modal.hide();
                return;
            }

            confirmButton.disabled = true;

            if (source.type === 'image' && cropper) {
                // Persist the cropped banner image, while fit/zoom/position stay in metadata inputs.
                const canvas = cropper.getCroppedCanvas({
                    width: 1600,
                    height: 500,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (!canvas) {
                    confirmButton.disabled = false;
                    return;
                }

                canvas.toBlob((blob) => {
                    confirmButton.disabled = false;
                    if (!blob) {
                        return;
                    }

                    const croppedFile = new File([blob], 'banner-cropped.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    bannerInput.files = transfer.files;
                    syncMetaInputs();
                    applySuccess = true;

                    renderHeader(URL.createObjectURL(croppedFile), 'image');
                    modal.hide();
                }, 'image/jpeg', 0.92);

                return;
            }

            syncVideoMetaFromFields();
            syncMetaInputs();
            applySuccess = true;
            renderHeader(selectedFile && source.type === 'video' ? URL.createObjectURL(selectedFile) : source.url, source.type);
            confirmButton.disabled = false;
            modal.hide();
        });

        syncControlState();
        const initialBanner = currentBanner();
        renderHeader(initialBanner.url, initialBanner.type);
        renderPreview();
    };
})();
