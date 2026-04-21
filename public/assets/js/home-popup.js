(function () {
    const popup = document.querySelector('[data-home-popup]');

    if (!popup) {
        return;
    }

    const dialog = popup.querySelector('.home-notice-popup__dialog');
    const closeTriggers = popup.querySelectorAll('[data-home-popup-close]');
    const snoozeButtons = popup.querySelectorAll('[data-home-popup-snooze]');
    const storageKey = popup.dataset.storageKey || 'zenox-home-notice-hidden-until';
    const snoozeHours = Math.max(1, Number.parseInt(popup.dataset.snoozeHours || '2', 10) || 2);
    const bodyClass = 'home-popup-open';
    let lastFocusedElement = null;
    let hideTimer = null;

    function readHiddenUntil() {
        try {
            return Number.parseInt(window.localStorage.getItem(storageKey) || '0', 10) || 0;
        } catch (error) {
            return 0;
        }
    }

    function writeHiddenUntil(timestamp) {
        try {
            window.localStorage.setItem(storageKey, String(timestamp));
        } catch (error) {
            /* Gracefully ignore storage access issues. */
        }
    }

    function isOpen() {
        return !popup.hidden;
    }

    function showPopup() {
        if (isOpen()) {
            return;
        }

        if (hideTimer) {
            window.clearTimeout(hideTimer);
            hideTimer = null;
        }

        lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        popup.hidden = false;
        popup.setAttribute('aria-hidden', 'false');
        document.body.classList.add(bodyClass);

        window.requestAnimationFrame(() => {
            popup.classList.add('is-visible');
            if (dialog instanceof HTMLElement) {
                dialog.focus({ preventScroll: true });
            }
        });
    }

    function hidePopup(shouldSnooze) {
        if (!isOpen()) {
            return;
        }

        if (shouldSnooze) {
            writeHiddenUntil(Date.now() + snoozeHours * 60 * 60 * 1000);
        }

        popup.classList.remove('is-visible');
        popup.setAttribute('aria-hidden', 'true');
        document.body.classList.remove(bodyClass);

        if (hideTimer) {
            window.clearTimeout(hideTimer);
        }

        hideTimer = window.setTimeout(() => {
            popup.hidden = true;
            hideTimer = null;
        }, 220);

        if (lastFocusedElement instanceof HTMLElement) {
            lastFocusedElement.focus({ preventScroll: true });
        }
    }

    function handleKeydown(event) {
        if (event.key === 'Escape' && isOpen()) {
            hidePopup(false);
        }
    }

    closeTriggers.forEach((button) => {
        button.addEventListener('click', () => {
            hidePopup(false);
        });
    });

    snoozeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            hidePopup(true);
        });
    });

    popup.addEventListener('click', (event) => {
        if (event.target === popup) {
            hidePopup(false);
        }
    });

    document.addEventListener('keydown', handleKeydown);

    if (readHiddenUntil() <= Date.now()) {
        window.setTimeout(showPopup, 140);
    }
})();
