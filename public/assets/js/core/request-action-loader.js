/**
 * Loader compartido para acciones que procesan una petición o navegan a otra
 * vista. Para peticiones, reconoce DELETE y rutas que identifican acciones de
 * borrado, guardado, actualización, firma, token, finalización o estatus.
 */
(function () {
    if (window.__portal3RequestActionLoaderInitialized) return;
    window.__portal3RequestActionLoaderInitialized = true;

    let activeRequests = 0;
    let loaderWasVisible = false;
    let pendingIntent = null;
    let pendingIntentTimer = 0;

    const getLoaderElement = () => document.querySelector('.loader-admongas');
    const isLoaderVisible = () => {
        const element = getLoaderElement();
        return Boolean(element && getComputedStyle(element).display !== 'none');
    };
    const showLoader = () => {
        if (window.loader && typeof window.loader.show === 'function') {
            window.loader.show();
            return;
        }
        const element = getLoaderElement();
        if (element) element.style.display = 'flex';
    };
    const hideLoader = () => {
        if (window.loader && typeof window.loader.hide === 'function') {
            window.loader.hide();
            return;
        }
        const element = getLoaderElement();
        if (element) element.style.display = 'none';
    };
    const begin = () => {
        if (activeRequests === 0) {
            loaderWasVisible = isLoaderVisible();
            if (!loaderWasVisible) showLoader();
        }
        activeRequests += 1;
    };
    const finish = () => {
        if (activeRequests === 0) return;
        activeRequests -= 1;
        if (activeRequests === 0) {
            if (!loaderWasVisible) hideLoader();
            loaderWasVisible = false;
        }
    };
    const clearIntent = () => {
        pendingIntent = null;
        window.clearTimeout(pendingIntentTimer);
        pendingIntentTimer = 0;
    };
    const setIntent = kind => {
        clearIntent();
        pendingIntent = { kind };
        pendingIntentTimer = window.setTimeout(clearIntent, 20000);
    };
    const isSameOriginUrl = url => {
        try {
            const parsed = new URL(url, window.location.href);
            return parsed.origin === window.location.origin ? parsed : null;
        } catch (_) {
            return null;
        }
    };
    const deletePathPattern = /(?:^|\/)(?:eliminar|delete|destroy|borrar)(?=[A-Z]|[-_.\/]|$)/i;
    const actionPathPattern = /(?:^|\/)(?:guardar|save|actualizar|update|finalizar|finalize|firmar|firma|sign|token|estatus|status|de-acuerdo|aceptar|agree)(?=[A-Z]|[-_.\/]|$)/i;
    const isCommentMessageRequest = (method, url) => {
        const parsed = isSameOriginUrl(url);
        if (!parsed || !['POST', 'PUT', 'PATCH'].includes(String(method || 'GET').toUpperCase())) return false;
        if (!/(?:comentari|comment)/i.test(parsed.pathname)) return false;
        return !/(?:eliminar|borrar|remover|delete|destroy|remove)(?:[-_.\/]|$)/i.test(parsed.pathname);
    };
    const isDeleteRequest = (method, url) => {
        const parsed = isSameOriginUrl(url);
        if (!parsed) return false;
        return String(method || 'GET').toUpperCase() === 'DELETE' || deletePathPattern.test(parsed.pathname);
    };
    const isActionRequest = (method, url) => {
        const parsed = isSameOriginUrl(url);
        if (!parsed) return false;
        const verb = String(method || 'GET').toUpperCase();
        if (isDeleteRequest(verb, parsed.href)) return true;
        if (!['POST', 'PUT', 'PATCH'].includes(verb)) return false;
        return actionPathPattern.test(parsed.pathname);
    };
    const shouldTrackRequest = (method, url) => {
        // Los comentarios tienen su propio indicador "Enviando mensaje..."
        // dentro del body del modal/offcanvas; no deben abrir el loader global.
        if (isCommentMessageRequest(method, url)) {
            clearIntent();
            return false;
        }
        if (isActionRequest(method, url)) {
            clearIntent();
            return true;
        }
        if (!pendingIntent) return false;

        const verb = String(method || 'GET').toUpperCase();
        const matchesIntent = pendingIntent.kind === 'download'
            ? !['HEAD', 'OPTIONS'].includes(verb)
            : !['GET', 'HEAD', 'OPTIONS'].includes(verb);
        if (!matchesIntent) return false;
        clearIntent();
        return true;
    };

    if (window.XMLHttpRequest) {
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
        const originalSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            this.__portal3RequestAction = { method, url };
            this.__portal3RequestActionTracked = false;
            this.__portal3SkipActionLoader = false;
            return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
            if (String(name).toLowerCase() === 'x-portal3-skip-action-loader' && String(value) === '1') {
                this.__portal3SkipActionLoader = true;
            }
            return originalSetRequestHeader.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function () {
            const request = this.__portal3RequestAction;
            if (request && !this.__portal3SkipActionLoader && !this.__portal3RequestActionTracked && shouldTrackRequest(request.method, request.url)) {
                this.__portal3RequestActionTracked = true;
                begin();
                this.addEventListener('loadend', finish, { once: true });
            }
            try {
                return originalSend.apply(this, arguments);
            } catch (error) {
                if (this.__portal3RequestActionTracked) {
                    this.__portal3RequestActionTracked = false;
                    finish();
                }
                throw error;
            }
        };
    }

    if (typeof window.fetch === 'function') {
        const originalFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            const method = init?.method || input?.method || 'GET';
            const url = typeof input === 'string' || input instanceof URL ? input : input?.url;
            if (!shouldTrackRequest(method, url)) return originalFetch(input, init);

            begin();
            return originalFetch(input, init).then(
                response => {
                    finish();
                    return response;
                },
                error => {
                    finish();
                    throw error;
                }
            );
        };
    }

    const normalizeActionLabel = element => String(
        element?.getAttribute('aria-label') || element?.getAttribute('title') || element?.innerText || element?.value || ''
    ).replace(/[+✓✔]/g, ' ').replace(/\s+/g, ' ').trim().toLocaleLowerCase('es-MX');
    const getIntent = element => {
        const label = normalizeActionLabel(element);
        if (/^descargar\s+(pdf|excel)$/.test(label)) return 'download';
        if (/^nuevo(?:\s+registro)?$/.test(label)) return 'new';
        if (/^(guardar(?:\s+.*)?|actualizar(?:\s+estatus)?|finalizar(?:\s+.*)?|firmar(?:\s+.*)?|de\s+acuerdo|generar\s+token(?:\s+.*)?)$/.test(label)) return 'mutation';
        return null;
    };
    const opensModal = element => Boolean(element.closest(
        '[data-bs-toggle="modal"], [data-bs-target^="#modal"], [data-bs-target*="modal"], a[href^="#modal"]'
    ));
    const samePageLink = element => {
        if (!(element instanceof HTMLAnchorElement) || !element.href || element.target === '_blank' || element.getAttribute('href')?.startsWith('#')) return false;
        const parsed = isSameOriginUrl(element.href);
        return Boolean(parsed && parsed.href !== window.location.href);
    };
    const startNavigationLoader = () => {
        const currentUrl = window.location.href;
        let started = false;
        const onBeforeUnload = () => {
            started = true;
            begin();
            window.setTimeout(() => {
                if (window.location.href === currentUrl) finish();
            }, 30000);
        };
        window.addEventListener('beforeunload', onBeforeUnload, { once: true });
        // Si no inició navegación, quitamos el listener y no mostramos loader.
        window.setTimeout(() => {
            if (!started && window.location.href === currentUrl) {
                window.removeEventListener('beforeunload', onBeforeUnload);
            }
        }, 5000);
    };
    const downloadAnchorFile = async element => {
        begin();
        try {
            const response = await window.fetch(element.href, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const blob = await response.blob();
            if (!response.ok) {
                const message = (await blob.text()).trim();
                throw new Error(message || 'No se pudo descargar el archivo.');
            }

            const contentType = (response.headers.get('Content-Type') || blob.type || '').toLowerCase();
            const isPdf = /^descargar\s+pdf$/i.test(normalizeActionLabel(element));
            if (isPdf && !(contentType.includes('application/pdf') || contentType.includes('octet-stream'))) {
                throw new Error('El servidor no generó el PDF solicitado.');
            }
            if (!isPdf && !(contentType.includes('spreadsheet') || contentType.includes('octet-stream') || contentType.includes('excel'))) {
                throw new Error('El servidor no generó el archivo Excel solicitado.');
            }

            const disposition = response.headers.get('Content-Disposition') || '';
            const encodedFilename = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
            const plainFilename = disposition.match(/filename="?([^";]+)"?/i)?.[1];
            const extension = /^descargar\s+pdf$/i.test(normalizeActionLabel(element)) ? 'pdf' : 'xlsx';
            let filename = element.download || 'reporte.' + extension;
            if (encodedFilename) {
                try { filename = decodeURIComponent(encodedFilename); } catch (_) { filename = encodedFilename; }
            } else if (plainFilename) {
                filename = plainFilename;
            }

            const objectUrl = URL.createObjectURL(blob);
            const download = document.createElement('a');
            download.href = objectUrl;
            download.download = filename;
            download.hidden = true;
            document.body.appendChild(download);
            download.click();
            download.remove();
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        } catch (error) {
            window.Notify?.error(error.message || 'No se pudo descargar el archivo.');
        } finally {
            finish();
        }
    };

    document.addEventListener('click', event => {
        const control = event.target.closest('button, a, input[type="button"], input[type="submit"], [role="button"]');
        if (!control || control.disabled || control.getAttribute('aria-disabled') === 'true' || opensModal(control)) return;

        const intent = getIntent(control);
        if (!intent) return;

        if (intent === 'download' && control instanceof HTMLAnchorElement) {
            const hrefAttribute = (control.getAttribute('href') || '').trim();
            if (!hrefAttribute || hrefAttribute.startsWith('#') || /^javascript:/i.test(hrefAttribute)) {
                setIntent(intent);
                return;
            }
            const parsed = isSameOriginUrl(control.href);
            if (!parsed) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            clearIntent();
            const menu = control.closest('.dropdown-menu');
            const toggle = menu?.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
            if (toggle && window.bootstrap?.Dropdown) window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
            downloadAnchorFile(control);
            return;
        }

        if (intent === 'new' && samePageLink(control)) {
            startNavigationLoader();
            return;
        }
        setIntent(intent);
    }, true);

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const submitter = event.submitter;
        const url = submitter?.formAction || form.action;
        const method = submitter?.formMethod || form.method || 'GET';
        const isDelete = isDeleteRequest(method, url);
        if (isCommentMessageRequest(method, url)) {
            clearIntent();
            return;
        }
        const isPendingAction = Boolean(pendingIntent) && !['GET', 'HEAD', 'OPTIONS'].includes(String(method).toUpperCase());
        if (!isDelete && !isPendingAction) return;

        window.setTimeout(() => {
            if (!event.defaultPrevented) {
                clearIntent();
                begin();
            }
        }, 0);
    }, true);

    document.addEventListener('click', event => {
        if (!pendingIntent) return;
        const control = event.target.closest('button, a, [role="button"]');
        const label = normalizeActionLabel(control);
        if (event.target.closest('.swal2-cancel, [data-bs-dismiss="modal"], .js-cancel, .btn-cancel') || /^(cancelar|cancel|cerrar|no)$/.test(label)) {
            clearIntent();
        }
    }, true);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') clearIntent();
    }, true);
    document.addEventListener('show.bs.modal', () => {
        if (pendingIntent?.kind === 'new') clearIntent();
    }, true);
})();
