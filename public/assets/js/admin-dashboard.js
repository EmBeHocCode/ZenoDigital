document.addEventListener('DOMContentLoaded', () => {
    // Added: desktop collapse + mobile off-canvas sidebar controller with localStorage.
    const sidebarToggleButtons = document.querySelectorAll('[data-admin-sidebar-toggle]');
    const sidebar = document.querySelector('[data-admin-sidebar]');
    const sidebarOverlay = document.querySelector('[data-admin-sidebar-overlay]');
    const desktopMedia = window.matchMedia('(min-width: 768px)');
    const sidebarStorageKey = 'adminSidebarCollapsed';
    const menuGroups = sidebar ? Array.from(sidebar.querySelectorAll('[data-admin-menu-group]')) : [];
    const groupStorageKey = 'adminSidebarGroupState';
    const sidebarState = {
        collapsed: getStoredSidebarState(),
        mobileOpen: false,
    };
    let groupState = getStoredGroupState();

    function getStoredSidebarState() {
        try {
            return window.localStorage.getItem(sidebarStorageKey) === '1';
        } catch (error) {
            return document.documentElement.classList.contains('admin-sidebar-collapsed');
        }
    }

    function storeSidebarState(isCollapsed) {
        try {
            window.localStorage.setItem(sidebarStorageKey, isCollapsed ? '1' : '0');
        } catch (error) {
            /* Ignore localStorage access issues for a graceful fallback. */
        }
    }

    function getStoredGroupState() {
        try {
            const raw = window.localStorage.getItem(groupStorageKey);
            if (!raw) {
                return {};
            }

            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function storeGroupState() {
        try {
            window.localStorage.setItem(groupStorageKey, JSON.stringify(groupState));
        } catch (error) {
            /* Ignore localStorage access issues for a graceful fallback. */
        }
    }

    function isDesktopSidebar() {
        return desktopMedia.matches;
    }

    function updateSidebarToggleUi() {
        const isDesktop = isDesktopSidebar();
        const isExpanded = isDesktop ? !sidebarState.collapsed : sidebarState.mobileOpen;
        const label = isDesktop
            ? (sidebarState.collapsed ? 'Mở rộng sidebar' : 'Thu gọn sidebar')
            : (sidebarState.mobileOpen ? 'Đóng menu admin' : 'Mở menu admin');

        sidebarToggleButtons.forEach((button) => {
            button.setAttribute('aria-expanded', String(isExpanded));
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
        });
    }

    function syncSidebarState() {
        const isDesktop = isDesktopSidebar();
        document.documentElement.classList.toggle('admin-sidebar-collapsed', isDesktop && sidebarState.collapsed);
        document.documentElement.classList.toggle('admin-sidebar-mobile-open', !isDesktop && sidebarState.mobileOpen);
        updateSidebarToggleUi();
    }

    function closeMobileSidebar() {
        if (!sidebarState.mobileOpen) {
            return;
        }

        sidebarState.mobileOpen = false;
        syncSidebarState();
    }

    function toggleSidebarState() {
        if (isDesktopSidebar()) {
            sidebarState.collapsed = !sidebarState.collapsed;
            storeSidebarState(sidebarState.collapsed);
        } else {
            sidebarState.mobileOpen = !sidebarState.mobileOpen;
        }

        syncSidebarState();
    }

    function setMenuGroupState(group, isOpen, persist = true) {
        const toggle = group.querySelector('[data-admin-menu-toggle]');
        const panel = group.querySelector('[data-admin-menu-panel]');
        const groupKey = group.getAttribute('data-group-key') || '';

        if (!toggle || !panel) {
            return;
        }

        group.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        panel.setAttribute('aria-hidden', String(!isOpen));

        if (persist && groupKey !== '') {
            groupState[groupKey] = isOpen;
            storeGroupState();
        }
    }

    function hydrateMenuGroups() {
        menuGroups.forEach((group) => {
            const groupKey = group.getAttribute('data-group-key') || '';
            const hasActiveItem = group.getAttribute('data-has-active') === '1';
            const defaultOpen = group.getAttribute('data-default-open') === '1';
            const hasStoredState = groupKey !== '' && Object.prototype.hasOwnProperty.call(groupState, groupKey);
            const shouldOpen = hasActiveItem || (hasStoredState ? groupState[groupKey] === true : defaultOpen);

            setMenuGroupState(group, shouldOpen, false);
        });
    }

    if (menuGroups.length > 0) {
        hydrateMenuGroups();

        menuGroups.forEach((group) => {
            const toggle = group.querySelector('[data-admin-menu-toggle]');
            if (!toggle) {
                return;
            }

            toggle.addEventListener('click', () => {
                setMenuGroupState(group, !group.classList.contains('is-open'));
            });
        });
    }

    if (sidebar && sidebarToggleButtons.length > 0) {
        sidebarToggleButtons.forEach((button) => {
            button.addEventListener('click', toggleSidebarState);
        });

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeMobileSidebar);
        }

        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (!isDesktopSidebar()) {
                    closeMobileSidebar();
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !isDesktopSidebar()) {
                closeMobileSidebar();
            }
        });

        const handleSidebarViewportChange = () => {
            if (isDesktopSidebar()) {
                sidebarState.mobileOpen = false;
            }

            syncSidebarState();
        };

        if (typeof desktopMedia.addEventListener === 'function') {
            desktopMedia.addEventListener('change', handleSidebarViewportChange);
        } else if (typeof desktopMedia.addListener === 'function') {
            desktopMedia.addListener(handleSidebarViewportChange);
        }

        syncSidebarState();
    }

    document.querySelectorAll('[data-select-all]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const target = checkbox.getAttribute('data-select-all');
            document.querySelectorAll(`[data-bulk-item=\"${target}\"]`).forEach((item) => {
                item.checked = checkbox.checked;
            });
            updateBulkCounts();
        });
    });

    document.querySelectorAll('[data-bulk-item]').forEach((checkbox) => {
        checkbox.addEventListener('change', updateBulkCounts);
    });

    function updateBulkCounts() {
        document.querySelectorAll('[data-bulk-count]').forEach((node) => {
            const target = node.getAttribute('data-bulk-count');
            const checked = document.querySelectorAll(`[data-bulk-item=\"${target}\"]:checked`).length;
            node.textContent = String(checked);
        });
    }

    updateBulkCounts();

    if (window.__revenueData && document.getElementById('revenueChart') && window.Chart) {
        const revenueLabels = window.__revenueData.map((item) => item.month);
        const revenueValues = window.__revenueData.map((item) => Number(item.total));

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: revenueLabels,
                datasets: [{
                    label: 'Doanh thu',
                    data: revenueValues,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,.14)',
                    fill: true,
                    tension: .34,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true },
                    tooltip: { mode: 'index', intersect: false },
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.18)' } },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    if (window.__orderStatusData && document.getElementById('orderStatusChart') && window.Chart) {
        const labels = window.__orderStatusData.map((item) => item.status_label || item.status);
        const values = window.__orderStatusData.map((item) => Number(item.total));

        new Chart(document.getElementById('orderStatusChart'), {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['#2563eb', '#22c55e', '#f59e0b', '#ef4444', '#64748b', '#14b8a6', '#8b5cf6'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });
    }

    if (window.__userGrowthData && document.getElementById('userGrowthChart') && window.Chart) {
        const labels = window.__userGrowthData.map((item) => item.month);
        const values = window.__userGrowthData.map((item) => Number(item.total));

        new Chart(document.getElementById('userGrowthChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Người dùng mới',
                    data: values,
                    borderRadius: 8,
                    backgroundColor: 'rgba(37,99,235,.85)',
                    maxBarThickness: 28,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.18)' } },
                    x: { grid: { display: false } },
                },
            },
        });
    }
});
