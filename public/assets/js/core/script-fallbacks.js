(function () {
    document.addEventListener('error', function (event) {
        const failedScript = event.target;
        if (!(failedScript instanceof HTMLScriptElement)) return;

        const fallbackSrc = failedScript.dataset.fallbackSrc;
        if (!fallbackSrc || failedScript.dataset.fallbackAttempted === 'true') return;

        failedScript.dataset.fallbackAttempted = 'true';

        const fallbackScript = document.createElement('script');
        fallbackScript.src = fallbackSrc;
        fallbackScript.defer = failedScript.defer;
        fallbackScript.async = failedScript.async;
        fallbackScript.dataset.fallbackFor = failedScript.src;
        failedScript.insertAdjacentElement('afterend', fallbackScript);
    }, true);
})();
