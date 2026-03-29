document.addEventListener('DOMContentLoaded', () => {
    const widget = document.querySelector('[data-ai-widget]');
    if (!widget) {
        return;
    }

    const launcher = widget.querySelector('[data-ai-launcher]');
    const panel = widget.querySelector('[data-ai-panel]');
    const closeButton = widget.querySelector('[data-ai-close]');
    const resetButton = widget.querySelector('[data-ai-reset]');
    const form = widget.querySelector('[data-ai-form]');
    const input = widget.querySelector('[data-ai-input]');
    const sendButton = widget.querySelector('[data-ai-send]');
    const status = widget.querySelector('[data-ai-status]');
    const messages = widget.querySelector('[data-ai-messages]');
    const promptsPanel = widget.querySelector('[data-ai-prompts-panel]');
    const promptsWrap = widget.querySelector('[data-ai-prompts]');
    const promptsScrollLeft = widget.querySelector('[data-ai-prompts-scroll-left]');
    const promptsScrollRight = widget.querySelector('[data-ai-prompts-scroll-right]');
    const promptsDismissButton = widget.querySelector('[data-ai-prompts-dismiss]');
    const feedbackToggle = widget.querySelector('[data-ai-feedback-toggle]');
    const feedbackPanel = widget.querySelector('[data-ai-feedback-panel]');
    const feedbackClose = widget.querySelector('[data-ai-feedback-close]');
    const feedbackInput = widget.querySelector('[data-ai-feedback-input]');
    const feedbackSubmit = widget.querySelector('[data-ai-feedback-submit]');
    const utilityWrap = widget.querySelector('.zen-ai-utility-wrap');
    const utilityToggle = widget.querySelector('[data-ai-utility-toggle]');
    const utilityMenu = widget.querySelector('[data-ai-utility-menu]');
    const feedbackTypeButtons = Array.from(widget.querySelectorAll('[data-ai-feedback-type]'));
    const feedbackSentimentButtons = Array.from(widget.querySelectorAll('[data-ai-feedback-sentiment]'));
    const welcomeMarkup = messages ? messages.innerHTML : '';

    if (!launcher || !panel || !form || !input || !sendButton || !status || !messages || !promptsPanel || !promptsWrap || !promptsDismissButton) {
        return;
    }

    const endpoint = widget.dataset.endpoint || '';
    const feedbackEndpoint = widget.dataset.feedbackEndpoint || '';
    const productId = Number(widget.dataset.productId || 0);
    const pageType = widget.dataset.pageType || 'storefront';
    const storageKey = widget.dataset.sessionStorageKey || 'zenox-ai-widget-v1';
    const defaultStatus = widget.dataset.defaultStatus || 'Chạm một gợi ý để bắt đầu nhanh hơn.';
    const promptsDismissedStatus = widget.dataset.promptsDismissedStatus || 'Đã ẩn gợi ý nhanh cho lần mở widget này.';
    const bridgeStatus = widget.dataset.bridgeStatus || 'Đã nhận phản hồi từ AI.';
    const fallbackStatus = widget.dataset.fallbackStatus || 'Đang phản hồi bằng chế độ dự phòng kỹ thuật.';
    let csrfToken = widget.dataset.csrf || '';
    let isSending = false;
    let feedbackType = 'general';
    let feedbackSentiment = 'neutral';
    let promptsDismissedThisPage = false;
    let hasOpenedOnceThisPage = false;

    const state = loadState(storageKey);
    state.sessionId = state.sessionId || '';
    state.messages = Array.isArray(state.messages) ? state.messages : [];
    state.isOpen = Boolean(state.isOpen);

    setFeedbackType(feedbackType);
    setFeedbackSentiment(feedbackSentiment);
    renderMessages();
    syncOpenState(state.isOpen);
    updateStatus(defaultStatus);
    resizeInput();
    toggleSendDisabled();
    syncFeedbackPanel(false);

    launcher.addEventListener('click', () => {
        syncOpenState(!widget.classList.contains('is-open'));
        if (widget.classList.contains('is-open')) {
            input.focus();
        }
    });

    closeButton.addEventListener('click', () => syncOpenState(false));

    promptsDismissButton.addEventListener('click', () => {
        dismissPrompts();
        updateStatus(promptsDismissedStatus);
    });

    resetButton.addEventListener('click', async () => {
        await resetConversation();
    });

    promptsWrap.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-ai-prompt]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        const prompt = (trigger.dataset.aiPrompt || '').trim();
        if (prompt !== '') {
            dismissPrompts();
            await submitMessage(prompt);
        }
    });

    if (promptsScrollLeft && promptsScrollRight) {
        promptsScrollLeft.addEventListener('click', () => scrollPromptsBy(-1));
        promptsScrollRight.addEventListener('click', () => scrollPromptsBy(1));
        promptsWrap.addEventListener('scroll', updatePromptsScrollState, { passive: true });
        window.addEventListener('resize', () => {
            window.requestAnimationFrame(() => {
                updatePromptsScrollState();
            });
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await submitMessage(input.value);
    });

    input.addEventListener('input', () => {
        resizeInput();
        toggleSendDisabled();
    });

    input.addEventListener('keydown', async (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            await submitMessage(input.value);
        }
    });

    if (feedbackToggle && feedbackPanel && feedbackClose && feedbackInput && feedbackSubmit) {
        feedbackToggle.addEventListener('click', () => {
            syncOpenState(true);
            syncUtilityMenu(false);
            dismissPrompts();
            syncFeedbackPanel(feedbackPanel.hidden);
        });

        feedbackClose.addEventListener('click', () => syncFeedbackPanel(false));

        feedbackTypeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setFeedbackType(button.dataset.aiFeedbackType || 'general');
            });
        });

        feedbackSentimentButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setFeedbackSentiment(button.dataset.aiFeedbackSentiment || 'neutral');
            });
        });

        feedbackSubmit.addEventListener('click', async () => {
            await submitFeedback(feedbackInput.value);
        });
    }

    if (utilityToggle && utilityMenu) {
        utilityToggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            syncOpenState(true);
            syncUtilityMenu(utilityMenu.hidden);
        });

        utilityMenu.addEventListener('click', async (event) => {
            const promptTrigger = event.target.closest('[data-ai-utility-prompt]');
            if (promptTrigger) {
                const prompt = (promptTrigger.dataset.aiUtilityPrompt || '').trim();
                syncUtilityMenu(false);
                if (prompt !== '') {
                    dismissPrompts();
                    await submitMessage(prompt);
                }
                return;
            }

            const actionTrigger = event.target.closest('[data-ai-utility-action]');
            if (!actionTrigger) {
                return;
            }

            const action = (actionTrigger.dataset.aiUtilityAction || '').trim();
            syncUtilityMenu(false);

            if (action === 'feedback') {
                dismissPrompts();
                syncOpenState(true);
                syncFeedbackPanel(true);
                return;
            }

            if (action === 'reset') {
                await resetConversation();
            }
        });

        document.addEventListener('click', (event) => {
            if (!utilityWrap || utilityMenu.hidden) {
                return;
            }

            if (!utilityWrap.contains(event.target)) {
                syncUtilityMenu(false);
            }
        });
    }

    document.querySelectorAll('[data-ai-chat-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', async (event) => {
            event.preventDefault();
            const prompt = (trigger.dataset.aiMessage || trigger.dataset.aiPrompt || '').trim();
            const autoSend = (trigger.dataset.aiAutosend || '') === '1';

            syncOpenState(true);

            if (autoSend && prompt !== '') {
                await submitMessage(prompt);
                return;
            }

            input.value = prompt;
            resizeInput();
            toggleSendDisabled();
            input.focus();
        });
    });

    function syncOpenState(nextOpen) {
        const wasOpen = widget.classList.contains('is-open');
        const isFirstOpenInSession = nextOpen && !wasOpen && !hasOpenedOnceThisPage;

        widget.classList.toggle('is-open', nextOpen);
        panel.hidden = !nextOpen;
        launcher.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
        document.body.classList.toggle('has-ai-chat-open', nextOpen && window.innerWidth < 768);

        if (isFirstOpenInSession) {
            hasOpenedOnceThisPage = true;
        }

        if (!nextOpen) {
            syncUtilityMenu(false);
            syncFeedbackPanel(false);
        }

        if (nextOpen) {
            window.requestAnimationFrame(() => {
                scrollMessagesToBottom();
                updatePromptsScrollState();
            });
        }

        state.isOpen = nextOpen;
        syncPromptsVisibility(isFirstOpenInSession);
        saveState();
    }

    async function submitMessage(rawMessage) {
        const message = String(rawMessage || '').trim();
        if (message === '' || isSending || endpoint === '') {
            return;
        }

        syncOpenState(true);
        syncUtilityMenu(false);
        syncFeedbackPanel(false);
        dismissPrompts();
        appendMessage('user', message);
        state.messages.push({ role: 'user', text: message });
        saveState();

        input.value = '';
        resizeInput();
        toggleSendDisabled();
        setLoading(true);
        appendTyping();

        try {
            const payload = await sendRequest(message);
            removeTyping();

            const reply = String(payload?.data?.reply || '').trim();
            if (reply === '') {
                throw new Error('AI chưa trả lời được câu hỏi này. Bạn thử diễn đạt ngắn hơn giúp mình nhé.');
            }

            appendMessage('assistant', reply);
            state.messages.push({ role: 'assistant', text: reply });
            if (payload?.data?.sessionId) {
                state.sessionId = String(payload.data.sessionId);
            }
            saveState();
            updateStatus(payload?.data?.isFallback ? fallbackStatus : bridgeStatus);
        } catch (error) {
            removeTyping();
            const errorMessage = error instanceof Error ? error.message : 'Đã xảy ra lỗi khi gửi câu hỏi.';
            appendMessage('assistant', 'Mình chưa xử lý được ngay. Bạn thử hỏi ngắn gọn hơn hoặc bấm lại sau ít giây nhé.');
            updateStatus(errorMessage);
        } finally {
            setLoading(false);
        }
    }

    async function submitFeedback(rawMessage) {
        const message = String(rawMessage || '').trim();
        if (message === '' || isSending || feedbackEndpoint === '') {
            updateStatus('Bạn hãy nhập nội dung feedback trước khi gửi.');
            if (feedbackInput) {
                feedbackInput.focus();
            }
            return;
        }

        syncOpenState(true);
        syncUtilityMenu(false);
        dismissPrompts();
        appendMessage('user', message);
        state.messages.push({ role: 'user', text: message });
        saveState();

        setLoading(true);
        appendTyping();

        try {
            const payload = await sendFeedbackRequest(message);
            removeTyping();

            const reply = String(payload?.data?.reply || '').trim();
            if (reply === '') {
                throw new Error('Feedback đã được gửi nhưng AI chưa trả lời tiếp được. Bạn thử lại sau nhé.');
            }

            appendMessage('assistant', reply);
            state.messages.push({ role: 'assistant', text: reply });
            if (payload?.data?.sessionId) {
                state.sessionId = String(payload.data.sessionId);
            }

            saveState();
            clearFeedbackComposer();
            syncFeedbackPanel(false);
            updateStatus(payload?.data?.isFallback
                ? 'Đã lưu feedback. Phần phản hồi thêm hiện đang dùng fallback.'
                : (payload?.data?.needsFollowUp
                    ? 'Đã lưu feedback và đánh dấu cần hỗ trợ thêm.'
                    : 'Đã lưu feedback và AI đã phản hồi.'));
        } catch (error) {
            removeTyping();
            const errorMessage = error instanceof Error ? error.message : 'Đã xảy ra lỗi khi gửi feedback.';
            appendMessage('assistant', 'Mình chưa lưu được feedback lúc này. Bạn thử lại sau ít phút nhé.');
            updateStatus(errorMessage);
        } finally {
            setLoading(false);
        }
    }

    async function sendRequest(message, extra = {}) {
        const body = new URLSearchParams();
        body.set('_csrf', csrfToken);
        body.set('session_id', state.sessionId);
        body.set('recent_messages', JSON.stringify(collectRecentMessages()));

        if (productId > 0) {
            body.set('product_id', String(productId));
        }

        if (message !== '') {
            body.set('message', message);
        }

        if (extra.reset) {
            body.set('reset', '1');
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        });

        const payload = await response.json().catch(() => null);
        if (payload && payload.csrf_token) {
            csrfToken = String(payload.csrf_token);
        }

        if (!response.ok || !payload || payload.success !== true) {
            throw new Error(payload?.message || 'AI đang bận, bạn thử lại sau nhé.');
        }

        return payload;
    }

    async function sendFeedbackRequest(message) {
        const body = new URLSearchParams();
        body.set('_csrf', csrfToken);
        body.set('session_id', state.sessionId);
        body.set('message', message);
        body.set('recent_messages', JSON.stringify(collectRecentMessages()));
        body.set('feedback_type', feedbackType);
        body.set('sentiment', feedbackSentiment);
        body.set('page_type', pageType);

        if (productId > 0) {
            body.set('product_id', String(productId));
        }

        const response = await fetch(feedbackEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        });

        const payload = await response.json().catch(() => null);
        if (payload && payload.csrf_token) {
            csrfToken = String(payload.csrf_token);
        }

        if (!response.ok || !payload || payload.success !== true) {
            throw new Error(payload?.message || 'Chưa gửi được feedback, bạn thử lại sau nhé.');
        }

        return payload;
    }

    function setLoading(nextLoading) {
        isSending = nextLoading;
        widget.classList.toggle('is-loading', nextLoading);
        sendButton.disabled = nextLoading || input.value.trim() === '';
        input.disabled = nextLoading;

        if (feedbackToggle) {
            feedbackToggle.disabled = nextLoading;
        }

        if (feedbackInput) {
            feedbackInput.disabled = nextLoading;
        }

        if (feedbackSubmit) {
            feedbackSubmit.disabled = nextLoading;
        }
    }

    function updateStatus(message) {
        const textNode = status.querySelector('span:last-child');
        if (textNode) {
            textNode.textContent = message;
        }
    }

    function appendMessage(role, text) {
        const article = document.createElement('article');
        article.className = `zen-ai-message is-${role}`;

        if (role === 'assistant') {
            article.innerHTML = `
                <div class="zen-ai-avatar"><i class="fas fa-sparkles"></i></div>
                <div class="zen-ai-bubble"></div>
            `;
        } else {
            article.innerHTML = '<div class="zen-ai-bubble"></div>';
        }

        const bubble = article.querySelector('.zen-ai-bubble');
        if (bubble) {
            bubble.textContent = text;
        }

        messages.appendChild(article);
        scrollMessagesToBottom();
    }

    function appendTyping() {
        removeTyping();
        const article = document.createElement('article');
        article.className = 'zen-ai-message is-assistant is-typing';
        article.dataset.aiTyping = '1';
        article.innerHTML = `
            <div class="zen-ai-avatar"><i class="fas fa-sparkles"></i></div>
            <div class="zen-ai-bubble">
                <span class="zen-ai-typing-dot"></span>
                <span class="zen-ai-typing-dot"></span>
                <span class="zen-ai-typing-dot"></span>
            </div>
        `;
        messages.appendChild(article);
        scrollMessagesToBottom();
    }

    function removeTyping() {
        messages.querySelectorAll('[data-ai-typing]').forEach((node) => node.remove());
    }

    function renderMessages() {
        messages.innerHTML = welcomeMarkup;
        state.messages.forEach((item) => {
            if (!item || !item.role || !item.text) {
                return;
            }
            appendMessage(item.role, item.text);
        });
        scrollMessagesToBottom();
        syncPromptsVisibility();
        updatePromptsScrollState();
    }

    async function resetConversation() {
        if (isSending) {
            return;
        }

        try {
            const payload = await sendRequest('', { reset: true });
            state.sessionId = String(payload?.data?.sessionId || '');
            state.messages = [];
            saveState();
            clearFeedbackComposer();
            syncUtilityMenu(false);
            syncFeedbackPanel(false);
            renderMessages();
            syncPromptsVisibility();
            updateStatus(defaultStatus);
        } catch (error) {
            updateStatus(error.message || 'Không thể làm mới cuộc trò chuyện.');
        }
    }

    function resizeInput() {
        const maxHeight = 100;
        input.style.height = 'auto';
        input.style.overflowY = 'hidden';

        const nextHeight = Math.min(input.scrollHeight, maxHeight);
        input.style.height = `${nextHeight}px`;
        input.style.overflowY = input.scrollHeight > maxHeight ? 'auto' : 'hidden';
    }

    function toggleSendDisabled() {
        sendButton.disabled = isSending || input.value.trim() === '';
    }

    function scrollMessagesToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    function saveState() {
        const payload = JSON.stringify({
            sessionId: state.sessionId,
            messages: state.messages.slice(-10),
            isOpen: state.isOpen,
        });

        try {
            window.sessionStorage.setItem(storageKey, payload);
        } catch (error) {
            // Ignore storage quota errors in constrained browsers.
        }
    }

    function loadState(key) {
        try {
            const raw = window.sessionStorage.getItem(key);
            if (!raw) {
                return {
                    sessionId: '',
                    messages: [],
                    isOpen: false,
                };
            }

            const parsed = JSON.parse(raw);
            return {
                sessionId: String(parsed?.sessionId || ''),
                messages: Array.isArray(parsed?.messages) ? parsed.messages : [],
                isOpen: Boolean(parsed?.isOpen),
            };
        } catch (error) {
            return {
                sessionId: '',
                messages: [],
                isOpen: false,
            };
        }
    }

    function dismissPrompts() {
        promptsDismissedThisPage = true;
        syncPromptsVisibility();
        saveState();
    }

    function syncPromptsVisibility(forceShow = false) {
        const hasConversation = state.messages.length > 0;
        const shouldShow = widget.classList.contains('is-open')
            && !hasConversation
            && !isFeedbackPanelOpen()
            && (forceShow || !promptsDismissedThisPage);

        promptsPanel.classList.toggle('is-hidden', !shouldShow);

        if (shouldShow) {
            window.requestAnimationFrame(() => {
                updatePromptsScrollState();
            });
        }
    }

    function syncFeedbackPanel(nextOpen) {
        if (!feedbackPanel) {
            return;
        }

        const shouldOpen = Boolean(nextOpen);
        feedbackPanel.hidden = !shouldOpen;
        feedbackPanel.classList.toggle('is-hidden', !shouldOpen);

        if (shouldOpen && feedbackInput) {
            feedbackInput.focus();
        }

        syncPromptsVisibility();
    }

    function syncUtilityMenu(nextOpen) {
        if (!utilityToggle || !utilityMenu) {
            return;
        }

        const shouldOpen = Boolean(nextOpen) && widget.classList.contains('is-open');
        utilityMenu.hidden = !shouldOpen;
        utilityMenu.classList.toggle('is-hidden', !shouldOpen);
        utilityToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }

    function scrollPromptsBy(direction) {
        if (!promptsWrap) {
            return;
        }

        const offset = Math.max(120, Math.round(promptsWrap.clientWidth * 0.72));
        promptsWrap.scrollBy({
            left: direction * offset,
            behavior: 'smooth',
        });
    }

    function updatePromptsScrollState() {
        if (!promptsWrap || !promptsScrollLeft || !promptsScrollRight) {
            return;
        }

        const maxScrollLeft = Math.max(0, promptsWrap.scrollWidth - promptsWrap.clientWidth);
        const current = Math.max(0, promptsWrap.scrollLeft);
        const canScroll = maxScrollLeft > 8;

        promptsScrollLeft.disabled = !canScroll || current <= 4;
        promptsScrollRight.disabled = !canScroll || current >= maxScrollLeft - 4;
    }

    function isFeedbackPanelOpen() {
        return Boolean(feedbackPanel && !feedbackPanel.hidden);
    }

    function clearFeedbackComposer() {
        if (feedbackInput) {
            feedbackInput.value = '';
        }

        setFeedbackType('general');
        setFeedbackSentiment('neutral');
    }

    function setFeedbackType(nextType) {
        feedbackType = nextType || 'general';
        feedbackTypeButtons.forEach((button) => {
            button.classList.toggle('is-active', (button.dataset.aiFeedbackType || '') === feedbackType);
        });
    }

    function setFeedbackSentiment(nextSentiment) {
        feedbackSentiment = nextSentiment || 'neutral';
        feedbackSentimentButtons.forEach((button) => {
            button.classList.toggle('is-active', (button.dataset.aiFeedbackSentiment || '') === feedbackSentiment);
        });
    }

    function collectRecentMessages() {
        return state.messages.slice(-6).map((item) => ({
            role: item?.role === 'assistant' ? 'assistant' : 'user',
            text: String(item?.text || '').slice(0, 500),
        })).filter((item) => item.text.trim() !== '');
    }
});
