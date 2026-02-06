const notyf = new Notyf({
    duration: 3000,
    position: {
        x: 'right',
        y: 'top'
    },
    dismissible: true
});

function grupoForm() {
    return {
        idGrupo: null,
        nombreGrupo: '',
        modo: 'create', // create | edit
        enviando: false,
        error: false,

        guardarGrupo() {
            if (!this.nombreGrupo.trim()) {
                this.error = true;
                return;
            }

            this.enviando = true;

            axios.post('/grupos/create', {
                nombre: this.nombreGrupo
            }).then(() => {
                this.cerrarModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Creado',
                    text: 'Grupo creado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                });

                notyf.success('Grupo creado correctamente');
                
            }).finally(() => this.enviando = false);
        },

        actualizarGrupo() {
            if (!this.nombreGrupo.trim()) {
                this.error = true;
                return;
            }

            this.enviando = true;

            axios.post('/grupos/update', {
                id: this.idGrupo,
                nombre: this.nombreGrupo
            }).then(() => {
                this.cerrarModal();

                Swal.fire({
                    icon: 'success',
                    title: 'Actualizado',
                    text: 'Grupo actualizado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                });

                notyf.success('Grupo actualizado correctamente');

            }).finally(() => this.enviando = false);
        },

        abrirEditar(grupo) {
            this.modo = 'edit';
            this.idGrupo = grupo.id;
            this.nombreGrupo = grupo.nombre;

            new bootstrap.Modal(
                document.getElementById('nuevoGrupo')
            ).show();
        },

        cerrarModal() {
            $('#table-grupos').DataTable().ajax.reload(null, false);
            bootstrap.Modal.getInstance(
                document.getElementById('nuevoGrupo')
            ).hide();
            this.resetForm();
        },

        resetForm() {
            this.idGrupo = null;
            this.nombreGrupo = '';
            this.modo = 'create';
            this.error = false;
            this.enviando = false;
        }
    }
}

 

document.addEventListener('click', function (e) {

    const btn = e.target.closest('.btn-delete');
    if (!btn || btn.classList.contains('disabled')) return;

    const id = btn.dataset.id;

    Swal.fire({
        title: '¿Cancelar grupo?',
        text: 'El grupo será marcado como cancelado',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d'
    }).then((result) => {

        if (!result.isConfirmed) return;

        axios.post('/grupos/delete', { id })
            .then(response => {

                // Recargar DataTable sin perder página
                $('#table-grupos').DataTable().ajax.reload(null, false);

                Swal.fire({
                    icon: 'success',
                    title: 'Cancelado',
                    text: response.data.message ?? 'Grupo cancelado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                });

                notyf.success('Grupo cancelado correctamente');

            })
            .catch(err => {

                const mensaje =
                    err.response?.data?.message ||
                    'Error al cancelar el grupo';

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: mensaje,
                    timer: 2000,
                    showConfirmButton: false
                });

                notyf.error(mensaje);

            });
    });

});

document.addEventListener('click', function (e) {
   
    const btn = e.target.closest('.btn-edit');
    if (!btn) return;

    window.dispatchEvent(new CustomEvent('editar-grupo', {
        detail: {
            id: btn.dataset.id,
            nombre: btn.dataset.nombre
        }
    }));
});