document.addEventListener('alpine:init', () => {

    Alpine.data('mantenimientoQuincenal', () => ({

        modalCrear: null,

        folio: '',
        carpeta: '',

        form: {
            fecha: '',
            archivos: {}
        },

        errors: {
            fecha: false
        },

        formatos: [],

        mode: 'create',
        editId: null,

        init() {

            window.mantenimiento = this;

            this.modalCrear = new bootstrap.Modal(
                document.getElementById(
                    'ModalCrearMantenimiento'
                )
            );

            const container = document.getElementById('container');
            this.carpeta = container.dataset.carpeta || '';

            this.formatos = [
                {
                    campo: 'Formato1_file',
                    nombre:
                        'Formato de Mantenimiento PREVENTIVO',
                    template:
                        '/uploads/archivos/mantenimiento-quincenal/' +
                        this.carpeta +
                        '/Formato de Mantenimiento Preventivo.pdf'
                },
                {
                    campo: 'Formato2_file',
                    nombre: 'Prueba de sensores',
                    template:
                        '/uploads/archivos/mantenimiento-quincenal/' +
                        this.carpeta +
                        '/Pruebade sensores.pdf'
                },
                {
                    campo: 'Formato3_file',
                    nombre:
                        'Cumplimiento apartados 8.9.1 al 8.11.1',
                    template:
                        '/uploads/archivos/mantenimiento-quincenal/' +
                        this.carpeta +
                        '/CUMPLIMIENTO A LOS APARTADOS 8.9.1 AL 8.11.1.pdf'
                },
                {
                    campo: 'Formato4_file',
                    nombre:
                        'Cumplimiento apartados 8.12 al 8.17.4',
                    template:
                        '/uploads/archivos/mantenimiento-quincenal/' +
                        this.carpeta +
                        '/CUMPLIMIENTO A LOS APARTADOS 8.12 al 8.17.4.pdf'
                },
                {
                    campo: 'Formato5_file',
                    nombre:
                        'Cumplimiento apartados 8.17.5 al 8.19.5',
                    template:
                        '/uploads/archivos/mantenimiento-quincenal/' +
                        this.carpeta +
                        '/CUMPLIMIENTO A LOS APARTADOS 8.17.5 AL 8.19.5.pdf'
                },
                {
                    campo: 'Formato6_file',
                    nombre:
                        'Revisión y mantenimiento planta de luz',
                    template:
                        '/uploads/archivos/mantenimiento-quincenal/' +
                        this.carpeta +
                        '/REVISIÓN Y MANTENIMIENTO PLANTA DE LUZ.pdf'
                },
                {
                    campo: 'Formato7_file',
                    nombre:
                        'Revisión al compresor',
                    template:
                        '/uploads/archivos/mantenimiento-quincenal/' +
                        this.carpeta +
                        '/REVISIÓN AL COMPRESOR.pdf'
                }
            ];
        },

        openNuevoModal() {

             this.limpiar();

            this.mode = 'create';

            this.editId = null;

            this.form = {
                fecha: '',
                archivos: {}
            };

            this.modalCrear.show();
        },

        openModalEditar(row) {

            this.resetFiles();

            this.mode = 'edit';

            this.editId = row.id;

            this.form = {

                fecha: row.fecha,

                archivos: {}
            };

            this.modalCrear.show();
        },

        validarPdf(file) {

            if (!file) {
                return true;
            }

            const ext =
                file.name
                .split('.')
                .pop()
                .toLowerCase();

            return ext === 'pdf';
        },

         validate() {
            Object.keys(this.errors).forEach(k => this.errors[k] = false);
            let valid = true;

            if (!this.form.fecha) {
                this.errors.fecha = true;
                valid = false;
            }

            return valid;
        },

       limpiar() {

        Object.keys(this.errors).forEach(k => {

            this.errors[k] = false;

        });

        this.mode = 'create';

        this.editId = null;

        this.form = {

            fecha: '',

            archivos: {}

        };

        this.resetFiles();
    },

    resetFiles() {

    document
        .querySelectorAll(
            '#ModalCrearMantenimiento input[type="file"]'
        )
        .forEach(input => {

            input.value = '';

        });
},


        async submit() {

    if (!this.validate()) {

        this.notify(
            'error',
            'Completa todos los campos obligatorios'
        );

        return;
    }

    const formData = new FormData();

    formData.append(
        'Fecha',
        this.form.fecha
    );

    for (const [campo,file] of Object.entries(
        this.form.archivos
    )) {

        if (
            file &&
            !this.validarPdf(file)
        ) {

            this.notify(
                'error',
                'Todos los archivos deben ser PDF'
            );

            return;
        }

        if (file) {
            formData.append(
                campo,
                file
            );
        }
    }

    let url =
        '/sasisopa/control-actividades-procesos/bitacora-mantenimiento-quincenal/create';

    if (this.mode === 'edit') {

        formData.append(
            'id',
            this.editId
        );

        url =
            '/sasisopa/control-actividades-procesos/bitacora-mantenimiento-quincenal/update';
    }

    try {

        const res =
            await this.createAction({

                url,

                data: formData,

                table:
                    '#table-mantenimiento-quincenal'

            });

        if (
            !res ||
            !res.success
        ) {
            return;
        }

        this.limpiar();

        this.modalCrear.hide();

    } catch (error) {

        this.notify(
            'error',
            'Error al guardar'
        );
    }
},

async eliminar(id, name){

      await this.deleteAction({

                url: '/sasisopa/control-actividades-procesos/bitacora-mantenimiento-quincenal/delete',

                id,
                name,

                table: '#table-mantenimiento-quincenal'
            });

}

    }));

});