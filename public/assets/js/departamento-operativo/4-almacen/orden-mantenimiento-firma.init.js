document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('orden-firma-app');
    if (!app) return;

    const base = app.dataset.baseUrl;
    const id = Number(app.dataset.recordId);
    const type = app.dataset.signType;
    const tokenInput = document.getElementById('orden-firma-token');
    const signButton = document.getElementById('orden-firma-confirmar');
    const tokenButtons = [...app.querySelectorAll('.js-orden-token')];
    const cooldownKey = 'orden-mantenimiento-firma-cooldown-' + id + '-' + type;
    let sendingToken = false;
    let signing = false;
    let cooldownTimer = 0;

    const setCooldown = remaining => {
        window.clearInterval(cooldownTimer);
        const update = () => {
            const seconds = Math.ceil(Math.max(remaining, 0) / 1000);
            tokenButtons.forEach(button => {
                button.disabled = sendingToken || seconds > 0;
                button.dataset.originalText ||= button.innerHTML;
                button.innerHTML = seconds > 0
                    ? '<i class="ti ti-clock me-1"></i> Espera ' + seconds + ' s'
                    : button.dataset.originalText;
            });
            if (seconds <= 0) {
                window.clearInterval(cooldownTimer);
                localStorage.removeItem(cooldownKey);
            }
            remaining -= 1000;
        };
        update();
        if (remaining > 0) cooldownTimer = window.setInterval(update, 1000);
    };

    const savedCooldown = Number(localStorage.getItem(cooldownKey) || 0);
    if (savedCooldown > Date.now()) setCooldown(savedCooldown - Date.now());

    tokenInput?.addEventListener('input', () => {
        tokenInput.value = tokenInput.value.replace(/\D/g, '').slice(0, 6);
        if (signButton) signButton.disabled = tokenInput.value.length !== 6 || signing;
    });

    tokenButtons.forEach(button => button.addEventListener('click', async () => {
        if (sendingToken || button.disabled || !id) return;
        sendingToken = true;
        tokenButtons.forEach(item => { item.disabled = true; });
        const originalText = button.dataset.originalText || button.innerHTML;
        button.dataset.originalText = originalText;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Enviando…';
        try {
            const { data } = await axios.post(base + '/firma/token', { id, canal: button.dataset.channel });
            if (!data.success) throw new Error(data.message || 'No se pudo enviar el token.');
            Notify.success(data.message || 'Token enviado.');
            const expiresAt = Date.now() + 30000;
            localStorage.setItem(cooldownKey, String(expiresAt));
            setCooldown(30000);
        } catch (error) {
            tokenButtons.forEach(item => { item.disabled = false; });
            button.innerHTML = originalText;
            Notify.error(error.response?.data?.message || error.message || 'No se pudo enviar el token.');
        } finally {
            sendingToken = false;
        }
    }));

    signButton?.addEventListener('click', async () => {
        const token = tokenInput?.value.trim() || '';
        if (signing || !/^\d{6}$/.test(token)) {
            Notify.error('Ingresa el token de seguridad de seis dígitos.');
            tokenInput?.focus();
            return;
        }

        signing = true;
        signButton.disabled = true;
        const originalText = signButton.innerHTML;
        signButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Firmando…';
        try {
            const { data } = await axios.post(base + '/firma/confirmar', { id, token });
            if (!data.success) throw new Error(data.message || 'No se pudo registrar la firma.');
            localStorage.removeItem(cooldownKey);
            const redirect = () => { window.location.href = base; };
            if (window.Swal) {
                await Swal.fire({ icon: 'success', title: 'Firma registrada', text: 'La firma de ' + (type === 'B' ? 'VO.BO.' : 'Autorización') + ' se registró correctamente.', timer: 1800, showConfirmButton: false });
            } else {
                Notify.success(data.message || 'Firma registrada.');
            }
            redirect();
        } catch (error) {
            Notify.error(error.response?.data?.message || error.message || 'No se pudo registrar la firma.');
            signing = false;
            signButton.innerHTML = originalText;
            signButton.disabled = (tokenInput?.value.trim().length || 0) !== 6;
        }
    });
});
