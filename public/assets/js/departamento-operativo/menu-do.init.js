/**
 * Menú lateral de Departamento Operativo.
 *
 * Espejo de actions-home.init.js (que alimenta el sidebar de /home), pero
 * consume /menu-do. No conoce permisos: el servidor ya filtra.
 */
function menuDoApp() {
    return {
        menus: [],
        loading: false,

        init() {
            this.cargarMenus();
        },

        async cargarMenus() {
            this.loading = true;

            try {
                const res = await fetch('/menu-do');
                const json = await res.json();

                let data = json.data ?? json;

                if (!Array.isArray(data)) {
                    console.warn('Menú DO no es array:', data);
                    data = [];
                }

                this.menus = data;

            } catch (err) {
                console.error('Error cargando menú de Departamento Operativo:', err);
                this.menus = [];
            } finally {
                this.loading = false;
            }
        }
    }
}
