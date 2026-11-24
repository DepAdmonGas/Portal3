document.addEventListener('click', async (event) => {
    const link = event.target.closest('a[data-download-loader]');
    if (!link) return;

    event.preventDefault();
    if (link.dataset.downloadBusy === '1') return;

    link.dataset.downloadBusy = '1';
    link.setAttribute('aria-busy', 'true');
    const contenidoOriginal = link.hasAttribute('data-download-button-loader')
        ? link.innerHTML
        : null;
    if (contenidoOriginal !== null) {
        link.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Descargando...</span>';
        link.classList.add('disabled');
    }
    const ventanaVista = link.dataset.downloadMode === 'inline'
        ? window.open('about:blank', '_blank')
        : null;
    window.loader?.show();

    try {
        const response = await fetch(link.href, { credentials: 'same-origin' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const blob = await response.blob();
        const disposition = response.headers.get('Content-Disposition') || '';
        const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
        const plainName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
        let filename = link.getAttribute('download') || plainName || 'documento';

        if (encodedName) {
            try {
                filename = decodeURIComponent(encodedName);
            } catch (_) {
                filename = encodedName;
            }
        }

        const objectUrl = URL.createObjectURL(blob);
        if (ventanaVista) {
            ventanaVista.location.href = objectUrl;
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60000);
        } else {
            const download = document.createElement('a');
            download.href = objectUrl;
            download.download = filename;
            document.body.appendChild(download);
            download.click();
            download.remove();
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        }
    } catch (error) {
        ventanaVista?.close();
        console.error('Error al descargar documento de SASISOPA:', error);
        window.alerts?.error('No se pudo descargar el documento. Intenta nuevamente.');
    } finally {
        link.dataset.downloadBusy = '0';
        link.removeAttribute('aria-busy');
        if (contenidoOriginal !== null) {
            link.innerHTML = contenidoOriginal;
            link.classList.remove('disabled');
        }
        window.loader?.hide();
    }
});
