(function () {
    'use strict';

    document.addEventListener('error', function (event) {
        var failedScript = event.target;
        if (!failedScript || failedScript.tagName !== 'SCRIPT' || !failedScript.dataset) return;

        var fallbackUrl = failedScript.dataset.fallbackSrc;
        if (!fallbackUrl || failedScript.dataset.fallbackAttempted === 'true') return;

        failedScript.dataset.fallbackAttempted = 'true';

        var fallbackScript = document.createElement('script');
        fallbackScript.src = fallbackUrl;
        if (failedScript.defer) fallbackScript.defer = true;
        fallbackScript.addEventListener('load', function () {
            window.dispatchEvent(new CustomEvent('portal3:script-fallback-loaded', {
                detail: { failedSource: failedScript.src, fallbackSource: fallbackUrl }
            }));
        }, { once: true });
        document.head.appendChild(fallbackScript);
    }, true);
})();
