(function () {

    let container = null;

    function createContainer() {
        if (container) return;

        container = document.createElement('div');
        container.id = 'app-toast-container';

        container.className = 'position-fixed top-0 end-0 p-3';
        container.style.zIndex = 9999;

        document.body.appendChild(container);
    }

    function createToast(type, message) {

        createContainer(); // IMPORTANTE asegurar contenedor

        const types = {
            success: 'bg-success text-white',
            error: 'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info: 'bg-primary text-white'
        };

        const toast = document.createElement('div');

        toast.className = `
            toast show ${types[type] || 'bg-primary text-white'} border-0 mb-2
        `;

        toast.style.minWidth = '250px';
        toast.style.fontSize = '15px';
        toast.style.padding = '10px 12px';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.4s ease';

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"></button>
            </div>
        `;

        container.appendChild(toast);

        // FORZAR REPAINT
        toast.offsetHeight;

        // ANIMACIÓN ENTRADA
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';

        // AUTO REMOVE
        const duration = 3000;

        setTimeout(() => {
            removeToast(toast);
        }, duration);

        // CLOSE
        toast.querySelector('.btn-close').addEventListener('click', () => {
            removeToast(toast);
        });
    }

    function removeToast(toast) {

        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';

        setTimeout(() => {
            toast.remove();
        }, 400);
    }

    window.Notify = {
        show(type, message) {
            createToast(type, message);
        },
        success(message) {
            this.show('success', message);
        },
        error(message) {
            this.show('error', message);
        },
        warning(message) {
            this.show('warning', message);
        },
        info(message) {
            this.show('info', message);
        }
    };

    window.alerts = {
        success(msg) {
            if (window.Notify) window.Notify.success(msg);
        },
        error(msg) {
            if (window.Notify) window.Notify.error(msg);
        },
        confirm(title, msg, callback) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title,
                    text: msg,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'S\u00ed',
                    cancelButtonText: 'Cancelar'
                }).then(result => {
                    if (result.isConfirmed && typeof callback === 'function') {
                        callback();
                    }
                });
            }
        }
    };

    window.loader = {
        _el: null,
        _getEl() {
            if (!this._el) {
                this._el = document.querySelector('.loader-admongas');
            }
            return this._el;
        },
        show() {
            var el = this._getEl();
            if (el) el.style.display = 'flex';
        },
        hide() {
            var el = this._getEl();
            if (el) el.style.display = 'none';
        }
    };

})();