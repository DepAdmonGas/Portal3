document.addEventListener('alpine:init', () => {
    Alpine.data('consulta', () => ({

        lista: [],

        init(){
        this.cargarConsulta();
        },

         async cargarConsulta(){
        this.loading = true;

        try {

            const { data } = await axios.get(
                '/sasisopa/consulta/datatable'
            );

            if (data.success) {

                this.lista = data.data;
            }

        } finally {

            this.loading = false;
        }
        },

    }));
});