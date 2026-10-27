(() => {
    const isolateTableScroll = (wrapper) => {
        if (!(wrapper instanceof HTMLElement) || wrapper.dataset.scrollIsolated === 'true') {
            return;
        }

        const scrollContainer = wrapper.parentElement;
        if (!scrollContainer?.classList.contains('table-responsive')) {
            return;
        }

        const table = wrapper.querySelector('table.dataTable');
        if (!table) {
            return;
        }

        const bootstrapTableRow = table.closest('.dt-row');
        const scrollContent = bootstrapTableRow && wrapper.contains(bootstrapTableRow)
            ? bootstrapTableRow
            : table;

        scrollContainer.parentElement.insertBefore(wrapper, scrollContainer);
        wrapper.insertBefore(scrollContainer, scrollContent);
        scrollContainer.appendChild(scrollContent);
        wrapper.dataset.scrollIsolated = 'true';
    };

    const scan = (root) => {
        if (root instanceof HTMLElement && root.matches('.dataTables_wrapper')) {
            isolateTableScroll(root);
        }

        root.querySelectorAll?.('.dataTables_wrapper').forEach(isolateTableScroll);
    };

    scan(document);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) {
                    scan(node);
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
})();
