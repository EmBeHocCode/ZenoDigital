document.addEventListener('DOMContentLoaded', () => {
    const panelRoot = document.querySelector('[data-admin-ai-panel]');
    if (!panelRoot) {
        return;
    }

    const drawer = panelRoot.querySelector('[data-admin-ai-drawer]');
    const overlay = panelRoot.querySelector('[data-admin-ai-overlay]');
    const refreshButton = panelRoot.querySelector('[data-admin-ai-refresh]');
    const closeButton = panelRoot.querySelector('[data-admin-ai-close]');
    const resetButton = panelRoot.querySelector('[data-admin-ai-reset]');
    const form = panelRoot.querySelector('[data-admin-ai-form]');
    const input = panelRoot.querySelector('[data-admin-ai-input]');
    const runtimeNode = panelRoot.querySelector('[data-admin-ai-runtime]');
    const sessionStatusNode = panelRoot.querySelector('[data-admin-ai-session-status]');
    const cardsNode = panelRoot.querySelector('[data-admin-ai-cards]');
    const listsNode = panelRoot.querySelector('[data-admin-ai-lists]');
    const snapshotToggleButton = panelRoot.querySelector('[data-admin-ai-snapshot-toggle]');
    const snapshotToggleLabel = panelRoot.querySelector('[data-admin-ai-snapshot-toggle-label]');
    const snapshotToggleIcon = panelRoot.querySelector('[data-admin-ai-snapshot-toggle-icon]');
    const messagesNode = panelRoot.querySelector('[data-admin-ai-messages]');
    const promptsRail = panelRoot.querySelector('[data-admin-ai-prompts]');
    const promptButtons = panelRoot.querySelectorAll('[data-admin-ai-prompt]');
    const scrollLeftButton = panelRoot.querySelector('[data-admin-ai-prompts-left]');
    const scrollRightButton = panelRoot.querySelector('[data-admin-ai-prompts-right]');
    const openButtons = document.querySelectorAll('[data-admin-ai-open]');

    if (!drawer || !overlay || !form || !input || !runtimeNode || !cardsNode || !listsNode || !messagesNode) {
        return;
    }

    const profile = parseJson(panelRoot.dataset.profile, {});
    const chatEndpoint = panelRoot.dataset.chatEndpoint || '';
    const progressEndpoint = panelRoot.dataset.progressEndpoint || '';
    const sessionEndpoint = panelRoot.dataset.sessionEndpoint || '';
    const summaryEndpoint = panelRoot.dataset.summaryEndpoint || '';
    let csrfToken = panelRoot.dataset.csrfToken || '';
    const snapshotStorageKey = 'adminAiSnapshotCollapsed';

    const state = {
        open: false,
        loadingSummary: false,
        summaryLoaded: false,
        loadingSession: false,
        sessionLoaded: false,
        sessionId: '',
        messages: [],
        snapshotCollapsed: false,
        busy: false,
        activeRequestId: '',
        longWaitTimer: null,
        progressPollTimer: null,
    };

    function parseJson(value, fallback) {
        if (!value) {
            return fallback;
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
            maximumFractionDigits: 0,
        }).format(Number(value || 0));
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('vi-VN').format(Number(value || 0));
    }

    function orderStatusLabel(status) {
        switch (String(status || '').trim().toLowerCase()) {
            case 'pending':
                return 'Chờ xử lý';
            case 'paid':
                return 'Đã thanh toán';
            case 'processing':
                return 'Đang xử lý';
            case 'completed':
                return 'Hoàn thành';
            case 'cancelled':
                return 'Đã hủy';
            default:
                return String(status || 'Không xác định');
        }
    }

    function orderStatusBadgeClass(status) {
        switch (String(status || '').trim().toLowerCase()) {
            case 'paid':
            case 'completed':
                return 'is-success';
            case 'processing':
                return 'is-info';
            case 'pending':
                return 'is-warning';
            default:
                return 'is-muted';
        }
    }

    function normalizeOrderCode(value) {
        return String(value || '').trim().toUpperCase();
    }

    function statusText(meta) {
        if (!meta) {
            return '';
        }

        if (meta.mode === 'guardrail') {
            return 'Guardrail · ' + (meta.source || 'ai-guardrail');
        }

        if (meta.mode === 'direct_admin_read') {
            return 'DỮ LIỆU SHOP · ' + (meta.source || 'shop-data');
        }

        if (meta.mode === 'clarification') {
            return 'LÀM RÕ NHANH · ' + (meta.source || 'shop-data');
        }

        if (meta.mode === 'direct_admin_action') {
            return 'THAO TÁC HỆ THỐNG · ' + (meta.source || 'shop-data');
        }

        if (meta.mode === 'mutation_preview') {
            return 'PREVIEW THAO TÁC · ' + (meta.source || 'shop-data');
        }

        if (meta.mode === 'mutation_preview_only') {
            return 'PREVIEW ONLY · ' + (meta.source || 'shop-data');
        }

        if (meta.mode === 'mutation_cancelled') {
            return 'ĐÃ HỦY PREVIEW · ' + (meta.source || 'shop-data');
        }

        if (meta.mode === 'mutation_blocked') {
            return 'THAO TÁC BỊ CHẶN · ' + (meta.source || 'shop-data');
        }

        if (meta.is_fallback) {
            return 'FALLBACK · ' + (meta.provider || 'local-fallback');
        }

        return 'REAL BRIDGE · ' + (meta.provider || 'bridge');
    }

    function setRuntime(text, tone = 'neutral') {
        runtimeNode.textContent = text;
        runtimeNode.dataset.tone = tone;
    }

    function setSessionStatus(text, tone = 'restored') {
        if (!sessionStatusNode) {
            return;
        }

        sessionStatusNode.textContent = text;
        sessionStatusNode.dataset.tone = tone;
    }

    function createRequestId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'req-' + Date.now() + '-' + Math.random().toString(16).slice(2, 10);
    }

    function applySnapshotState() {
        drawer.classList.toggle('is-snapshot-collapsed', state.snapshotCollapsed);

        if (!snapshotToggleButton) {
            return;
        }

        snapshotToggleButton.setAttribute('aria-expanded', state.snapshotCollapsed ? 'false' : 'true');
        snapshotToggleButton.setAttribute('title', state.snapshotCollapsed ? 'Hiện snapshot' : 'Thu gọn snapshot');

        if (snapshotToggleLabel) {
            snapshotToggleLabel.textContent = state.snapshotCollapsed ? 'Hiện snapshot' : 'Thu gọn';
        }

        if (snapshotToggleIcon) {
            snapshotToggleIcon.classList.toggle('fa-chevron-left', !state.snapshotCollapsed);
            snapshotToggleIcon.classList.toggle('fa-chevron-right', state.snapshotCollapsed);
        }
    }

    function loadSnapshotState() {
        try {
            state.snapshotCollapsed = window.localStorage.getItem(snapshotStorageKey) === '1';
        } catch (error) {
            state.snapshotCollapsed = false;
        }

        applySnapshotState();
    }

    function toggleSnapshotState() {
        state.snapshotCollapsed = !state.snapshotCollapsed;
        applySnapshotState();

        try {
            window.localStorage.setItem(snapshotStorageKey, state.snapshotCollapsed ? '1' : '0');
        } catch (error) {
            // Ignore storage failures and keep the session state in memory.
        }
    }

    function createMessage(role, text, extraClass = '') {
        const article = document.createElement('article');
        article.className = `admin-ai-message is-${role}` + (extraClass ? ` ${extraClass}` : '');

        const bubble = document.createElement('div');
        bubble.className = 'admin-ai-message-bubble';

        const strong = document.createElement('strong');
        strong.textContent = role === 'assistant'
            ? (profile.bot_display_name || 'Meow')
            : (role === 'system' ? 'Hệ thống' : 'Bạn');
        bubble.appendChild(strong);

        const body = document.createElement('div');
        body.className = 'admin-ai-message-text';
        body.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
        bubble.appendChild(body);

        const note = document.createElement('div');
        note.className = 'admin-ai-message-note';
        note.hidden = true;
        bubble.appendChild(note);

        article.appendChild(bubble);
        return article;
    }

    function appendMessage(role, text, shouldStore = true, extraClass = '') {
        const node = createMessage(role, text, extraClass);
        messagesNode.appendChild(node);
        if (shouldStore) {
            state.messages.push({
                role,
                text,
            });
            state.messages = state.messages.slice(-8);
        }
        scrollMessagesToBottom();
        return node;
    }

    function setMessageText(node, text, note = '', stepKey = '') {
        if (!node) {
            return;
        }

        const textNode = node.querySelector('.admin-ai-message-text');
        if (textNode) {
            textNode.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
        }

        const noteNode = node.querySelector('.admin-ai-message-note');
        if (noteNode) {
            noteNode.textContent = note;
            noteNode.hidden = !note;
        }

        if (stepKey) {
            node.dataset.step = stepKey;
        }

        scrollMessagesToBottom();
    }

    function renderWelcomeState() {
        messagesNode.innerHTML = '';
        const welcomeText = profile.welcome_hint || 'Meow có thể tóm tắt dashboard hoặc trả lời câu hỏi quản trị ngắn gọn.';
        messagesNode.appendChild(createMessage('assistant', welcomeText, 'is-welcome'));
        state.messages = [];
    }

    function normalizeSessionMessages(messages) {
        if (!Array.isArray(messages)) {
            return [];
        }

        return messages
            .filter((item) => item && typeof item === 'object')
            .map((item) => ({
                role: ['assistant', 'user', 'system'].includes(String(item.role || '').trim())
                    ? String(item.role).trim()
                    : 'assistant',
                text: String(item.text || ''),
                created_at: String(item.created_at || ''),
                meta: item.meta && typeof item.meta === 'object' ? item.meta : {},
            }))
            .filter((item) => item.text.trim() !== '');
    }

    function renderMessages(messages) {
        messagesNode.innerHTML = '';
        const normalized = normalizeSessionMessages(messages);

        if (!normalized.length) {
            renderWelcomeState();
            return;
        }

        normalized.forEach((item) => {
            messagesNode.appendChild(createMessage(item.role, item.text));
        });
        state.messages = normalized.map((item) => ({
            role: item.role,
            text: item.text,
        }));
        scrollMessagesToBottom();
    }

    function recentMessagesForRequest() {
        return state.messages
            .filter((item) => item && ['user', 'assistant'].includes(String(item.role || '')))
            .slice(-6);
    }

    function applySessionPayload(sessionPayload, updateStatus = true) {
        if (!sessionPayload || typeof sessionPayload !== 'object') {
            renderWelcomeState();
            return;
        }

        state.sessionId = String(sessionPayload.sessionId || '');
        state.sessionLoaded = true;
        renderMessages(sessionPayload.messages || []);

        if (!updateStatus) {
            return;
        }

        const resumeReason = String(sessionPayload.resumeReason || '');
        const resumeNotice = String(sessionPayload.resumeNotice || '');
        let tone = 'restored';

        if (resumeReason === 'new' || resumeReason === 'reset') {
            tone = 'new';
        } else if (resumeReason === 'expired') {
            tone = 'expired';
        }

        setSessionStatus(
            resumeNotice || (resumeReason === 'new'
                ? 'Đã tạo phiên copilot mới.'
                : 'Đang tiếp tục phiên copilot gần nhất.'),
            tone
        );
    }

    function scrollMessagesToBottom() {
        messagesNode.scrollTop = messagesNode.scrollHeight;
    }

    function setComposerBusy(isBusy) {
        state.busy = isBusy;
        input.disabled = isBusy;
        form.querySelectorAll('button').forEach((button) => {
            button.disabled = isBusy;
        });
        promptButtons.forEach((button) => {
            button.disabled = isBusy;
        });
    }

    function clearProgressTimers() {
        if (state.longWaitTimer) {
            window.clearTimeout(state.longWaitTimer);
            state.longWaitTimer = null;
        }

        if (state.progressPollTimer) {
            window.clearTimeout(state.progressPollTimer);
            state.progressPollTimer = null;
        }
    }

    function wait(ms) {
        return new Promise((resolve) => {
            window.setTimeout(resolve, ms);
        });
    }

    function updatePendingFromProgress(node, progress) {
        if (!node || !progress) {
            return;
        }

        const note = progress.long_wait
            ? 'Bot đang xử lý lâu hơn dự kiến, có thể đang đọc snapshot lớn. Vui lòng chờ thêm...'
            : '';

        setMessageText(
            node,
            progress.step_label || 'Bot đang kiểm dữ liệu...',
            note,
            progress.step_key || ''
        );
    }

    async function parseJsonResponse(response, defaultErrorMessage) {
        const raw = await response.text();

        try {
            return JSON.parse(raw);
        } catch (error) {
            throw new Error(raw || defaultErrorMessage);
        }
    }

    async function pollProgress(requestId, pendingNode) {
        if (!progressEndpoint || !requestId || state.activeRequestId !== requestId) {
            return;
        }

        try {
            const url = new URL(progressEndpoint, window.location.origin);
            url.searchParams.set('request_id', requestId);

            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const payload = await parseJsonResponse(response, 'Không đọc được tiến trình AI.');

            if (response.ok && payload.success && payload.data) {
                updatePendingFromProgress(pendingNode, payload.data);
            }
        } catch (error) {
            // Bỏ qua lỗi polling tạm thời để request chính vẫn có thể hoàn tất.
        } finally {
            if (state.busy && state.activeRequestId === requestId) {
                state.progressPollTimer = window.setTimeout(() => {
                    pollProgress(requestId, pendingNode);
                }, 1200);
            }
        }
    }

    function buildSummaryCards(data) {
        const stats = data.stats || {};
        const scope = data.backoffice_scope || {};
        const coreBusiness = data.sales_recommendations?.core_business || {};
        const cards = [
            { label: 'Sản phẩm', value: formatNumber(stats.products), note: 'Danh mục đang quản lý', icon: 'fa-box' },
            { label: 'Đơn hàng', value: formatNumber(stats.orders), note: 'Tổng đơn hiện có', icon: 'fa-receipt' },
            { label: 'Đơn chờ xử lý', value: formatNumber(stats.pending_orders), note: 'Cần follow-up sớm', icon: 'fa-hourglass-half' },
            { label: 'Coupon hoạt động', value: formatNumber(stats.active_coupons), note: 'Mã đang còn hiệu lực', icon: 'fa-ticket' },
        ];

        if (scope.can_view_feedback) {
            cards.push({ label: 'Feedback mới', value: formatNumber(stats.new_feedback), note: 'Phản hồi chưa xử lý', icon: 'fa-comments' });
        }

        if (coreBusiness.cloud_product_count) {
            cards.push({
                label: 'Cloud Core',
                value: formatNumber(coreBusiness.cloud_product_count),
                note: String(coreBusiness.catalog_share_percent || 0) + '% catalog active',
                icon: 'fa-cloud',
            });
        }

        if (scope.can_view_coupons) {
            cards.push({ label: 'Sắp hết hạn', value: formatNumber(stats.expiring_coupons), note: 'Coupon cần kiểm tra', icon: 'fa-hourglass-end' });
        }

        if (scope.can_view_users) {
            cards.push({ label: 'Người dùng', value: formatNumber(stats.users), note: 'Tài khoản trong hệ thống', icon: 'fa-users' });
        }

        if (scope.can_view_finance) {
            cards.push({ label: 'Doanh thu', value: formatMoney(stats.revenue), note: 'Doanh thu tích lũy', icon: 'fa-wallet' });
            cards.push({ label: 'Hôm nay', value: formatMoney(stats.today_revenue), note: formatNumber(stats.today_orders) + ' đơn trong ngày', icon: 'fa-chart-line' });
        }

        return cards;
    }

    function renderSummaryCards(data) {
        const cards = buildSummaryCards(data);
        cardsNode.innerHTML = cards.length
            ? cards.map((card) => `
                <article class="admin-ai-metric-card">
                    <div class="admin-ai-metric-head">
                        <span>${escapeHtml(card.label)}</span>
                        <i class="fas ${escapeHtml(card.icon)}"></i>
                    </div>
                    <div class="admin-ai-metric-value">${escapeHtml(card.value)}</div>
                    <div class="admin-ai-metric-note">${escapeHtml(card.note)}</div>
                </article>
            `).join('')
            : '<div class="admin-ai-empty">Chưa có snapshot phù hợp với quyền hiện tại.</div>';
    }

    function renderListSection(title, items) {
        if (!items.length) {
            return '';
        }

        return `
            <section class="admin-ai-list-card">
                <header>
                    <h4>${escapeHtml(title)}</h4>
                </header>
                <div class="admin-ai-list-items">
                    ${items.join('')}
                </div>
            </section>
        `;
    }

    function confidenceLabel(value) {
        const normalized = String(value || '').trim().toLowerCase();
        if (normalized === 'high') {
            return 'Cao';
        }

        if (normalized === 'medium') {
            return 'Trung bình';
        }

        if (normalized === 'low') {
            return 'Thấp';
        }

        return '';
    }

    function recommendationMetrics(item) {
        const metrics = item?.metrics || {};
        const parts = [];

        if (typeof metrics.current_price === 'number' && Number.isFinite(metrics.current_price) && metrics.current_price > 0) {
            parts.push(formatMoney(metrics.current_price));
        }

        if (typeof metrics.order_count_30d === 'number') {
            parts.push(formatNumber(metrics.order_count_30d) + ' đơn / 30d');
        }

        if (metrics.specs_summary) {
            parts.push(String(metrics.specs_summary));
        }

        if (metrics.location) {
            parts.push('Loc: ' + String(metrics.location));
        }

        return parts;
    }

    function recommendationTitle(item) {
        if (item?.slot && item?.product_name) {
            return String(item.slot) + ' · ' + String(item.product_name);
        }

        if (item?.from_product_name && item?.to_product_name) {
            return String(item.from_product_name) + ' → ' + String(item.to_product_name);
        }

        if (item?.product_name) {
            return String(item.product_name);
        }

        if (item?.coupon_code) {
            return String(item.coupon_code);
        }

        return item?.recommendation_type ? String(item.recommendation_type) : 'Gợi ý';
    }

    function renderRecommendationSection(title, items) {
        if (!Array.isArray(items) || !items.length) {
            return '';
        }

        return `
            <section class="admin-ai-list-card">
                <header>
                    <h4>${escapeHtml(title)}</h4>
                </header>
                <div class="admin-ai-list-items admin-ai-reco-list">
                    ${items.map((item) => {
                        const metrics = recommendationMetrics(item);
                        const confidence = confidenceLabel(item?.confidence);
                        return `
                            <article class="admin-ai-reco-item">
                                <div class="admin-ai-reco-head">
                                    <strong>${escapeHtml(recommendationTitle(item))}</strong>
                                    ${confidence ? `<span class="admin-ai-confidence is-${escapeHtml(String(item.confidence || '').toLowerCase())}">${escapeHtml(confidence)}</span>` : ''}
                                </div>
                                ${metrics.length ? `<div class="admin-ai-reco-metrics">${metrics.map((part) => `<span>${escapeHtml(part)}</span>`).join('')}</div>` : ''}
                                ${item?.reason ? `<div class="admin-ai-reco-body">${escapeHtml(item.reason)}</div>` : ''}
                                ${item?.next_action ? `<div class="admin-ai-reco-next">Next: ${escapeHtml(item.next_action)}</div>` : ''}
                            </article>
                        `;
                    }).join('')}
                </div>
            </section>
        `;
    }

    function renderSummaryLists(data) {
        const sections = [];

        const latestOrders = Array.isArray(data.latest_orders) ? data.latest_orders : [];
        sections.push(renderListSection('Đơn gần đây', latestOrders.slice(0, 4).map((item) => `
            <div class="admin-ai-list-item">
                <div>
                    <strong>${escapeHtml(item.order_code || 'N/A')}</strong>
                    <span>${escapeHtml(orderStatusLabel(item.status || 'unknown'))}</span>
                </div>
                <small>${escapeHtml(item.created_at || '')}${item.total_amount != null ? ' · ' + escapeHtml(formatMoney(item.total_amount)) : ''}</small>
            </div>
        `)));

        const topProducts = Array.isArray(data.top_products) ? data.top_products : [];
        sections.push(renderListSection('Top sản phẩm', topProducts.slice(0, 4).map((item) => `
            <div class="admin-ai-list-item">
                <div>
                    <strong>${escapeHtml(item.name || 'N/A')}</strong>
                    <span>${escapeHtml(item.category_name || '')}</span>
                </div>
                <small>${escapeHtml(formatNumber(item.sold_qty || 0))} bán${item.sold_revenue ? ' · ' + escapeHtml(formatMoney(item.sold_revenue)) : ''}</small>
            </div>
        `)));

        const latestFeedback = Array.isArray(data.latest_feedback) ? data.latest_feedback : [];
        sections.push(renderListSection('Feedback mới', latestFeedback.slice(0, 4).map((item) => `
            <div class="admin-ai-list-item">
                <div>
                    <strong>${escapeHtml(item.feedback_code || 'N/A')}</strong>
                    <span>${escapeHtml(item.sentiment || 'neutral')} · ${escapeHtml(item.status || 'new')}</span>
                </div>
                <small>${escapeHtml(item.product_name || item.order_code || 'Không có liên kết')}</small>
            </div>
        `)));

        const latestCoupons = Array.isArray(data.latest_coupons) ? data.latest_coupons : [];
        sections.push(renderListSection('Coupon gần đây', latestCoupons.slice(0, 4).map((item) => `
            <div class="admin-ai-list-item">
                <div>
                    <strong>${escapeHtml(item.code || 'N/A')}</strong>
                    <span>${escapeHtml(item.status || 'inactive')}</span>
                </div>
                <small>-${escapeHtml(formatNumber(item.discount_percent || 0))}%${item.expires_at ? ' · ' + escapeHtml(item.expires_at) : ''}</small>
            </div>
        `)));

        const salesRecommendations = data.sales_recommendations || {};
        const recommendationBuckets = salesRecommendations.recommendations || {};
        sections.push(renderRecommendationSection('Cloud nên đẩy', Array.isArray(recommendationBuckets.push) ? recommendationBuckets.push.slice(0, 3) : []));
        sections.push(renderRecommendationSection('Upsell cloud', Array.isArray(recommendationBuckets.upsell) ? recommendationBuckets.upsell.slice(0, 3) : []));
        sections.push(renderRecommendationSection('Coupon / khuyến mãi', [
            ...(Array.isArray(recommendationBuckets.promotions) ? recommendationBuckets.promotions.slice(0, 2) : []),
            ...(Array.isArray(recommendationBuckets.coupon_actions) ? recommendationBuckets.coupon_actions.slice(0, 1) : []),
        ]));

        const dataGapNotes = Array.isArray(salesRecommendations?.data_gaps?.notes) ? salesRecommendations.data_gaps.notes.slice(0, 4) : [];
        sections.push(renderListSection('Giới hạn dữ liệu', dataGapNotes.map((note) => `
            <div class="admin-ai-list-item">
                <div>
                    <strong>Lưu ý</strong>
                </div>
                <small>${escapeHtml(note)}</small>
            </div>
        `)));

        const html = sections.filter(Boolean).join('');
        listsNode.innerHTML = html || '<div class="admin-ai-empty">Chưa có danh sách ngắn phù hợp với quyền hiện tại.</div>';
    }

    async function fetchSession(force = false) {
        if (!sessionEndpoint || state.loadingSession || (state.sessionLoaded && !force)) {
            return state.sessionId;
        }

        state.loadingSession = true;
        if (!state.sessionLoaded) {
            setSessionStatus('Đang tải phiên copilot gần nhất...', 'new');
        }

        try {
            const response = await fetch(sessionEndpoint, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const payload = await parseJsonResponse(response, 'Không tải được phiên copilot.');

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Không tải được phiên copilot.');
            }

            csrfToken = payload.csrf_token || csrfToken;
            applySessionPayload(payload.data || {}, true);
            return state.sessionId;
        } catch (error) {
            state.sessionLoaded = false;
            renderWelcomeState();
            setSessionStatus('Không tải được phiên copilot. Sẽ tạo lại khi bạn gửi câu hỏi mới.', 'expired');
            throw error;
        } finally {
            state.loadingSession = false;
        }
    }

    async function fetchSummary(force = false) {
        if (state.loadingSummary || (state.summaryLoaded && !force) || !summaryEndpoint) {
            return;
        }

        state.loadingSummary = true;
        cardsNode.innerHTML = '<div class="admin-ai-empty">Đang tải snapshot...</div>';
        listsNode.innerHTML = '';
        setRuntime('Đang tải snapshot từ dashboard...', 'loading');

        try {
            const response = await fetch(summaryEndpoint, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const payload = await parseJsonResponse(response, 'Không tải được snapshot admin AI.');

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Không tải được snapshot admin AI.');
            }

            csrfToken = payload.csrf_token || csrfToken;
            renderSummaryCards(payload.data || {});
            renderSummaryLists(payload.data || {});

            const runtime = payload.data?.runtime || {};
            const freshness = payload.data?.data_freshness || {};
            const freshnessNote = freshness?.cache?.hit
                ? ' · cache nóng'
                : ' · snapshot mới';
            const runtimeLabel = runtime.bridge_configured
                ? 'Bridge sẵn sàng · ' + (runtime.provider || 'bridge') + freshnessNote
                : 'Bridge chưa cấu hình đầy đủ';
            setRuntime(runtimeLabel, runtime.bridge_configured ? 'ready' : 'warning');
            state.summaryLoaded = true;
        } catch (error) {
            cardsNode.innerHTML = '<div class="admin-ai-empty">Không tải được snapshot admin AI.</div>';
            listsNode.innerHTML = '';
            setRuntime(error instanceof Error ? error.message : 'Không tải được snapshot.', 'danger');
        } finally {
            state.loadingSummary = false;
        }
    }

    async function sendChatRequest(payload) {
        const body = new URLSearchParams();
        Object.entries(payload || {}).forEach(([key, value]) => {
            if (Array.isArray(value) || (value && typeof value === 'object')) {
                body.append(key, JSON.stringify(value));
                return;
            }

            if (value !== undefined && value !== null) {
                body.append(key, String(value));
            }
        });

        const controller = new AbortController();
        const timeoutHandle = window.setTimeout(() => controller.abort(), 60000);
        try {
            const response = await fetch(chatEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
                body: body.toString(),
            });
            const json = await parseJsonResponse(response, 'Không gửi được câu hỏi tới Meow Copilot.');
            if (!response.ok || !json.success) {
                throw new Error(json.message || 'Không gửi được câu hỏi tới Meow Copilot.');
            }

            csrfToken = json.csrf_token || csrfToken;
            return json;
        } catch (error) {
            if (error && error.name === 'AbortError') {
                throw new Error('Bot đang xử lý quá thời gian chờ. Vui lòng thử lại sau ít phút.');
            }

            throw error;
        } finally {
            window.clearTimeout(timeoutHandle);
        }
    }

    async function submitMessage(message) {
        const trimmed = String(message || '').trim();
        if (!trimmed || !chatEndpoint || state.busy) {
            return;
        }

        if (!state.sessionLoaded) {
            try {
                await fetchSession();
            } catch (error) {
                appendMessage('assistant', error instanceof Error ? error.message : 'Không tải được phiên copilot.');
                setRuntime('Không tải được phiên copilot', 'danger');
                return;
            }
        }

        const requestId = createRequestId();
        appendMessage('user', trimmed);
        input.value = '';
        resizeInput();
        setComposerBusy(true);
        state.activeRequestId = requestId;
        setRuntime('Bot đang kiểm dữ liệu...', 'loading');

        const pendingNode = appendMessage('assistant', 'Bot đang kiểm dữ liệu...', false, 'is-pending');
        setMessageText(
            pendingNode,
            'Bot đang kiểm dữ liệu...',
            'Meow đang đọc dữ liệu shop và scope quản trị hiện tại.'
        );
        pollProgress(requestId, pendingNode);
        state.longWaitTimer = window.setTimeout(() => {
            if (state.busy && state.activeRequestId === requestId) {
                setMessageText(
                    pendingNode,
                    pendingNode.querySelector('.admin-ai-message-text')?.textContent || 'Bot đang kiểm dữ liệu...',
                    'Bot đang xử lý lâu hơn dự kiến, có thể đang lấy dữ liệu lớn. Vui lòng chờ thêm...'
                );
            }
        }, 8500);

        try {
            const payload = {
                _csrf: csrfToken,
                message: trimmed,
                request_id: requestId,
                session_id: state.sessionId,
                recent_messages: recentMessagesForRequest(),
            };
            const result = await sendChatRequest(payload);
            const progress = result.progress || null;
            if ((progress?.history || []).some((item) => item.step_key === 'summarizing')
                && pendingNode.dataset.step !== 'summarizing') {
                setMessageText(
                    pendingNode,
                    'Bot đang thống kê và gửi đến...',
                    'Meow đã lấy xong dữ liệu và đang chốt câu trả lời cuối.',
                    'summarizing'
                );
                await wait(220);
            }

            pendingNode.remove();
            if (result.data?.session) {
                applySessionPayload(result.data.session, false);
            } else {
                appendMessage('assistant', result.data?.reply || 'Meow đã phản hồi.');
            }
            if (result.data?.mutation) {
                applyMutation(result.data.mutation);
            }
            if (result.data?.refresh_summary) {
                fetchSummary(true);
            }
            const meta = result.meta || result.data || {};
            const runtimeTone = meta.mode === 'guardrail'
                ? 'warning'
                : (meta.is_fallback ? 'warning' : 'ready');
            setRuntime(statusText(meta), runtimeTone);
        } catch (error) {
            setMessageText(
                pendingNode,
                'Bot không thể hoàn tất yêu cầu.',
                error instanceof Error ? error.message : 'Không gửi được câu hỏi tới Meow Copilot.',
                'failed'
            );
            await wait(120);
            pendingNode.remove();
            appendMessage('assistant', error instanceof Error ? error.message : 'Không gửi được câu hỏi tới Meow Copilot.');
            setRuntime('Lỗi kết nối copilot', 'danger');
        } finally {
            clearProgressTimers();
            setComposerBusy(false);
            state.activeRequestId = '';
            resizeInput();
            input.focus();
        }
    }

    async function resetConversation() {
        if (!chatEndpoint || state.busy) {
            return;
        }

        try {
            setComposerBusy(true);
            const result = await sendChatRequest({
                _csrf: csrfToken,
                session_id: state.sessionId,
                reset: true,
            });
            if (result.data?.session) {
                applySessionPayload(result.data.session, true);
            } else {
                state.sessionId = result.data?.sessionId || '';
                renderWelcomeState();
                setSessionStatus('Đã tạo phiên copilot mới.', 'new');
            }
            setRuntime(statusText(result.meta || result.data), result.meta?.is_fallback ? 'warning' : 'ready');
        } catch (error) {
            appendMessage('assistant', error instanceof Error ? error.message : 'Không thể reset cuộc trò chuyện.');
            setRuntime('Không thể reset copilot', 'danger');
        } finally {
            setComposerBusy(false);
        }
    }

    function resizeInput() {
        input.style.height = 'auto';
        input.style.height = Math.min(104, Math.max(48, input.scrollHeight)) + 'px';
    }

    function syncPromptScrollButtons() {
        if (!promptsRail || !scrollLeftButton || !scrollRightButton) {
            return;
        }

        const maxScroll = promptsRail.scrollWidth - promptsRail.clientWidth;
        scrollLeftButton.disabled = promptsRail.scrollLeft <= 4;
        scrollRightButton.disabled = promptsRail.scrollLeft >= maxScroll - 4;
    }

    function openPanel() {
        if (state.open) {
            return;
        }

        state.open = true;
        document.documentElement.classList.add('admin-ai-open');
        overlay.hidden = false;
        drawer.setAttribute('aria-hidden', 'false');
        fetchSummary();
        fetchSession().catch(() => {
            // Session restore errors are surfaced in the panel status pill.
        });
        setTimeout(() => {
            input.focus();
            scrollMessagesToBottom();
            syncPromptScrollButtons();
        }, 120);
    }

    function closePanel() {
        if (!state.open) {
            return;
        }

        state.open = false;
        document.documentElement.classList.remove('admin-ai-open');
        overlay.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');
    }

    function applyMutation(mutation) {
        if (!mutation || mutation.type !== 'order_status_updated') {
            return;
        }

        const orderCode = normalizeOrderCode(mutation.order_code);
        const nextStatus = String(mutation.status || '').trim().toLowerCase();
        if (!orderCode || !nextStatus) {
            return;
        }

        document.querySelectorAll('[data-order-row]').forEach((row) => {
            if (normalizeOrderCode(row.getAttribute('data-order-code')) !== orderCode) {
                return;
            }

            const badge = row.querySelector('[data-order-status-badge]');
            if (badge) {
                badge.textContent = mutation.status_label || orderStatusLabel(nextStatus);
                badge.className = 'admin-badge-soft ' + orderStatusBadgeClass(nextStatus);
            }
        });

        const statusSelect = document.querySelector('[data-order-status-select]');
        if (statusSelect && normalizeOrderCode(statusSelect.getAttribute('data-order-code')) === orderCode) {
            statusSelect.value = nextStatus;
        }
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', openPanel);
    });

    closeButton?.addEventListener('click', closePanel);
    overlay.addEventListener('click', closePanel);
    refreshButton?.addEventListener('click', () => fetchSummary(true));
    resetButton?.addEventListener('click', resetConversation);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitMessage(input.value);
    });

    input.addEventListener('input', resizeInput);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submitMessage(input.value);
        }
    });

    promptButtons.forEach((button) => {
        button.addEventListener('click', () => {
            submitMessage(button.getAttribute('data-admin-ai-prompt') || button.textContent || '');
        });
    });

    scrollLeftButton?.addEventListener('click', () => {
        promptsRail?.scrollBy({ left: -220, behavior: 'smooth' });
    });

    scrollRightButton?.addEventListener('click', () => {
        promptsRail?.scrollBy({ left: 220, behavior: 'smooth' });
    });

    snapshotToggleButton?.addEventListener('click', toggleSnapshotState);

    promptsRail?.addEventListener('scroll', syncPromptScrollButtons);
    window.addEventListener('resize', syncPromptScrollButtons);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePanel();
        }
    });

    renderWelcomeState();
    setSessionStatus('Đang chuẩn bị phiên copilot...', 'new');
    loadSnapshotState();
    resizeInput();
    syncPromptScrollButtons();
    fetchSession().catch(() => {
        // Ignore warmup failure. The panel will retry on open or send.
    });
});
