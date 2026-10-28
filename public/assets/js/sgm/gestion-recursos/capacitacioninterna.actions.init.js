document.addEventListener('alpine:init', () => {
    Alpine.data('capacitacion', () => ({

      year: new Date().getFullYear(),

      init(){

        const yearContainer = document.getElementById('container');
        const initialYear = Number(yearContainer?.dataset.year);
        const currentYear = new Date().getFullYear();

        if (Number.isInteger(initialYear) && initialYear >= 2020 && initialYear <= currentYear) {
            this.year = initialYear;
        }

        this.actualizarYearBreadcrumb();

        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : event.target?.parentElement;
            const opcion = target?.closest('[data-capacitacion-interna-year]');
            if (!opcion) return;

            event.preventDefault();
            this.cambiarYear(opcion.dataset.capacitacionInternaYear);
        });

      },

      cambiarYear(year){

          const seleccionado = Number(year);
          if (!Number.isInteger(seleccionado) || seleccionado < 2020 || seleccionado > new Date().getFullYear()) return;

          this.year = seleccionado;
          this.actualizarYearBreadcrumb();

          const selectorTabla = '#table-capacitacion-interna';
          if (window.jQuery?.fn?.DataTable?.isDataTable(selectorTabla)) {
              window.jQuery(selectorTabla).DataTable().ajax.url(
                  `/sgm/gestion-recursos/programa-capacitacion-interna/datatable/${seleccionado}`
              ).load();
          }

      },

      actualizarYearBreadcrumb(){

          const etiqueta = document.getElementById('capacitacion-interna-year-label');
          if (etiqueta) etiqueta.textContent = this.year;

      }

   }));
});
