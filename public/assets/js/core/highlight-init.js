(function () {
    if (typeof hljs === 'undefined') {
        return;
    }

    hljs.initHighlightingOnLoad();
    document.querySelectorAll('pre.code-view > code').forEach(function (codeBlock) {
        codeBlock.textContent = codeBlock.innerHTML;
    });
})();
