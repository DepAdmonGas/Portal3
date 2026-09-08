document.addEventListener('alpine:init', () => {

    Alpine.data('cambioPrecio', (idEstacion) => ({

      idEstacion,

      init(){
        window.cambioPrecio = this;
      },

async actualiza(id) {

    id = Number(id);


    if (!id) {

        this.notify(
            'error',
            'El registro no es válido.'
        );

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Confirmación
    |--------------------------------------------------------------------------
    */

    const result =
        await Swal.fire({

            title:
                '¿Actualizar precio?',

            text:
                'Confirma que deseas marcar este cambio de precio como actualizado.',

            icon:
                'warning',

            showCancelButton:
                true,

            confirmButtonText:
                'Sí, actualizar',

            cancelButtonText:
                'Cancelar',

            reverseButtons:
                true

        });


    /*
     * El usuario canceló.
     */
    if (!result.isConfirmed) {

        return;

    }

   try {

        const res =
            await this.createAction({

                url:
                    '/gestoria/cambio-precio/' + this.idEstacion + '/actualizar',

                data: {

                    idReporte:
                        id

                },

                table:
                    '#table-cambio-precio'

            });

    } catch (e) {

        console.error(
            'Error al actualizar cambio de precio:',
            e
        );


        this.notify(
            'error',
            'Error al actualizar.'
        );

    }

},

    }));

});