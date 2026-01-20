function grupoForm() {
    return {
        nombreGrupo: '',
        enviando: false,
        error: false,

        guardarGrupo() {
            if (!this.nombreGrupo.trim()) {
                 this.error = true;
                return;
            }

            this.error = false;
            this.enviando = true;

            axios.post('/grupos/create', {
                nombre: this.nombreGrupo
            })
            .then(() => {
                // 1️⃣ Recargar DataTable
                $('#table-grupos').DataTable().ajax.reload(null, false);

                // 2️⃣ Limpiar textarea
                this.nombreGrupo = '';
                this.error = false;

                // 3️⃣ Cerrar modal
                bootstrap.Modal.getInstance(
                    document.getElementById('nuevoGrupo')
                ).hide();
            })
            .catch(error => {
                console.error(error);
                
            })
            .finally(() => {
                // 4️⃣ Botón vuelve a estado normal
                this.enviando = false;
            });
        }
    }
}
 

document.addEventListener('click', function(e) {
    // Busca si el click fue en un btn-delete o dentro de él
    const btn = e.target.closest('.btn-delete');
    if (!btn) return;

    const id = btn.dataset.id;

    if (!confirm('¿Seguro que deseas eliminar este grupo?')) return;

    axios.post('/grupos/delete', { id })
        .then(response => {
        // Recargar DataTable
        $('#table-grupos').DataTable().ajax.reload(null, false);

        // Mostrar el mensaje que viene del servidor
        const mensaje = response.data.message; // <- aquí está el texto enviado por PHP
        alert(mensaje); // o usar toast
        })
        .catch(err => {
             // Si el backend devuelve error de validación
            if (err.response && err.response.data && err.response.data.message) {
                alert(err.response.data.message);
            } else {
                alert('Error al eliminar el grupo');
            }
        });
});

