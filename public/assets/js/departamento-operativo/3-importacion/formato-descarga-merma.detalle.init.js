(() => {
    let descargaEnCurso = false;
    const duracionMinimaLoader = 350;
    let loaderVisibleDesde = 0;

    const mostrarLoader = () => {
        const loader = document.querySelector('.loader-admongas');
        if (!loader) return null;
        loaderVisibleDesde = performance.now();
        loader.style.display = 'flex';
        return loader;
    };

    const ocultarLoader = loader => {
        if (!loader) return;
        const restante = Math.max(0, duracionMinimaLoader - (performance.now() - loaderVisibleDesde));
        window.setTimeout(() => {
            loader.style.display = 'none';
            loaderVisibleDesde = 0;
        }, restante);
    };

    window.addEventListener('click', async (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const link = target?.closest('a[data-merma-export]');
        if (!link) return;

        event.preventDefault();
        event.stopPropagation();
        if (descargaEnCurso) return;

        const format = link.dataset.mermaExport;
        const defaultName = format === 'pdf' ? 'formato-descarga-merma.pdf' : 'formato-descarga-merma.xlsx';
        descargaEnCurso = true;
        link.classList.add('disabled');
        const loader = mostrarLoader();

        try {
            const response = await fetch(link.href, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const blob = await response.blob();
            const contentType = (response.headers.get('Content-Type') || blob.type || '').toLowerCase();
            const validType = format === 'pdf'
                ? contentType.includes('application/pdf') || contentType.includes('octet-stream')
                : contentType.includes('spreadsheet') || contentType.includes('excel') || contentType.includes('octet-stream');

            if (!response.ok || !validType || !blob.size) {
                let message = 'No se pudo descargar el archivo.';
                try {
                    const responseText = (await blob.text()).trim();
                    if (responseText) message = responseText;
                } catch (_) {}
                throw new Error(message);
            }

            const disposition = response.headers.get('Content-Disposition') || '';
            const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
            const plainName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
            let filename = defaultName;
            if (encodedName) {
                try { filename = decodeURIComponent(encodedName); } catch (_) { filename = encodedName; }
            } else if (plainName) {
                filename = plainName;
            }

            const objectUrl = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = objectUrl;
            downloadLink.download = filename;
            downloadLink.hidden = true;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            downloadLink.remove();
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        } catch (error) {
            if (window.Notify) {
                window.Notify.error(error.message || 'No se pudo descargar el archivo.');
            } else {
                console.error(error);
            }
        } finally {
            ocultarLoader(loader);
            descargaEnCurso = false;
            link.classList.remove('disabled');
        }
    }, true);
})();
