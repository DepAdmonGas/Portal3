(() => {
    const originalConfigs = new WeakMap();
    const overflowValues = new Set(['auto', 'scroll', 'hidden', 'clip']);
    const resolveToggle = (event) => {
        const target = event.target instanceof HTMLElement ? event.target : null;
        const related = event.relatedTarget instanceof HTMLElement ? event.relatedTarget : null;

        if (target?.matches('[data-bs-toggle="dropdown"]')) return target;
        if (related?.matches('[data-bs-toggle="dropdown"]')) return related;
        return target?.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]') || null;
    };

    const clipsMenu = (menu) => {
        let ancestor = menu.parentElement;

        while (ancestor && ancestor !== document.body && ancestor !== document.documentElement) {
            const style = window.getComputedStyle(ancestor);
            if ([style.overflowX, style.overflowY].some((value) => overflowValues.has(value))) {
                return true;
            }
            ancestor = ancestor.parentElement;
        }

        return false;
    };

    document.addEventListener('show.bs.dropdown', (event) => {
        const toggle = resolveToggle(event);
        if (!toggle?.closest('table')) return;

        const menu = toggle.closest('.dropdown')?.querySelector('.dropdown-menu');
        if (!menu || !clipsMenu(menu)) return;

        const instance = window.bootstrap?.Dropdown.getInstance(toggle);
        if (!instance || originalConfigs.has(toggle)) return;

        const originalConfig = {
            display: instance._config.display,
            popperConfig: instance._config.popperConfig
        };
        originalConfigs.set(toggle, originalConfig);

        // Dropdowns in table rows often use data-bs-display="static". That
        // disables Popper, so a large menu gets clipped by .table-responsive.
        // Re-enable Popper only for menus inside a clipping container.
        instance._config.display = 'dynamic';
        instance._config.popperConfig = (defaultConfig) => {
            const customConfig = typeof originalConfig.popperConfig === 'function'
                ? originalConfig.popperConfig(defaultConfig)
                : (originalConfig.popperConfig || {});
            const modifiers = (customConfig.modifiers || defaultConfig.modifiers || [])
                .filter((modifier) => !['flip', 'preventOverflow'].includes(modifier.name));

            return {
                ...defaultConfig,
                ...customConfig,
                strategy: 'fixed',
                modifiers: [
                    ...modifiers,
                    { name: 'flip', options: { boundary: 'viewport', rootBoundary: 'viewport', padding: 8 } },
                    { name: 'preventOverflow', options: { boundary: 'viewport', rootBoundary: 'viewport', altBoundary: true, padding: 8 } }
                ]
            };
        };
    });

    document.addEventListener('hidden.bs.dropdown', (event) => {
        const toggle = resolveToggle(event);
        if (!toggle) return;
        const instance = window.bootstrap?.Dropdown.getInstance(toggle);
        if (!instance || !originalConfigs.has(toggle)) return;

        const originalConfig = originalConfigs.get(toggle);
        instance._config.display = originalConfig.display;
        instance._config.popperConfig = originalConfig.popperConfig;
        originalConfigs.delete(toggle);
    });
})();
