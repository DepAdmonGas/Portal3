document.addEventListener('DOMContentLoaded', () => {
    const view = document.querySelector('[data-schedule-week-ui]');
    const tabs = view?.querySelector('[data-schedule-tabs]');
    const search = view?.querySelector('[data-schedule-worker-search]');
    if (!view) return;

    const storageKey = `checador.m03.schedule-week.tab.${view.dataset.workplaceId || '0'}`;
    const tabButtons = tabs && window.bootstrap?.Tab
        ? Array.from(tabs.querySelectorAll('[data-bs-toggle="tab"][data-bs-target]'))
        : [];
    const normalize = (value) => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('es-MX')
        .trim();

    const applyWorkerSearch = () => {
        const query = normalize(search?.value || '');
        view.querySelectorAll('[data-schedule-matrix-panel]').forEach((panel) => {
            const rows = Array.from(panel.querySelectorAll('tbody tr[data-worker-name]'));
            let matches = 0;
            rows.forEach((row) => {
                const visible = normalize(row.dataset.workerName).includes(query);
                row.classList.toggle('d-none', !visible);
                if (visible) matches += 1;
            });
            const emptySearch = panel.querySelector('[data-schedule-no-results]');
            emptySearch?.classList.toggle('d-none', query === '' || rows.length === 0 || matches > 0);
        });
    };

    let savedTarget = null;
    try {
        savedTarget = sessionStorage.getItem(storageKey);
    } catch (_) {
        // Browser storage may be unavailable; the default tab remains usable.
    }
    const savedButton = tabButtons.find((button) => button.dataset.bsTarget === savedTarget);
    if (savedButton) window.bootstrap.Tab.getOrCreateInstance(savedButton).show();

    tabs?.addEventListener('shown.bs.tab', (event) => {
        const target = event.target?.dataset?.bsTarget;
        if (typeof target === 'string' && target.startsWith('#')) {
            try {
                sessionStorage.setItem(storageKey, target);
            } catch (_) {
                // Tab switching still works when storage is unavailable.
            }
        }
        applyWorkerSearch();
    });
    search?.addEventListener('input', applyWorkerSearch);
    applyWorkerSearch();
});
