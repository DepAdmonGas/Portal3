document.addEventListener('alpine:init', () => {
    Alpine.data('programaMantenimiento', () => ({

   async nuevo(){
    try {

        const res = await this.createAction({
            url: '/sasisopa/control-actividades-procesos/create-programa-mantenimiento'
            });

            if (res && res.success) {

                 window.location.href =
                '/sasisopa/control-actividades-procesos/programa-anual-mantenimiento/' +
                res.data.id;

            }

        } catch (e) {
            this.notify('error','Error al guardar');
        }
    }
      
    }));
});
