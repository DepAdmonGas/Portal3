(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const key = 'scroll-' + window.location.pathname;
        const scrollPosition = sessionStorage.getItem(key);

        if (scrollPosition) window.scrollTo(0, parseInt(scrollPosition, 10));

        window.addEventListener('scroll', function () {
            sessionStorage.setItem(key, window.scrollY);
        });
    });
})();
