document.addEventListener('DOMContentLoaded', function () {

    var visorModal = null;

    function abrirVisor(src) {
        if (!src) return;
        var img = document.getElementById('imgVisor');
        if (img) {
            img.src = src;
        }
        if (!visorModal) {
            visorModal = new bootstrap.Modal(document.getElementById('modalVisorImagen'));
        }
        visorModal.show();
    }

    document.addEventListener('click', function (e) {
        var img = e.target.closest('[data-img-viewer="1"]');
        if (img) {
            abrirVisor(img.getAttribute('src'));
        }
    });
});