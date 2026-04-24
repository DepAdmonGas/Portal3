function menuApp() {
    return {
        menus: [],
        modulo: null,
        loading: false,

        init() {
            // obtener módulo desde el HTML
            this.modulo = this.$el.dataset.modulo || '';

            this.cargarMenus();
        },

        async cargarMenus() {
            this.loading = true;

            try {
                const res = await fetch(`/menu?modulo=${encodeURIComponent(this.modulo)}`);
                const json = await res.json();

                // SOPORTA {success, data} o array directo
                let data = json.data ?? json;

                if (!Array.isArray(data)) {
                    console.warn('Menu no es array:', data);
                    data = [];
                }

                this.menus = this.buildTree(data);

            } catch (err) {
                console.error('Error cargando menú:', err);
                this.menus = [];
            } finally {
                this.loading = false;
            }
        },

        buildTree(data) {

            return data.map(grupo => {

                //VALIDACIÓN CRÍTICA
                if (!grupo.items || !Array.isArray(grupo.items)) {
                    grupo.items = [];
                }

                let map = {};
                let items = [];

                grupo.items.forEach(item => {
                    item.children = [];
                    item.open = false;
                    map[item.id] = item;
                });

                grupo.items.forEach(item => {
                    if (item.padre_id && map[item.padre_id]) {
                        map[item.padre_id].children.push(item);
                    } else {
                        items.push(item);
                    }
                });

                grupo.items = items;
                return grupo;
            });
        },

        toggle(item) {
            if (item.children && item.children.length > 0) {
                item.open = !item.open;
            } else if (item.ruta) {
                window.location.href = item.ruta;
            }
        },

        //detectar ruta activa (mejorado)
        isActive(ruta) {
            if (!ruta) return false;

            const current = window.location.pathname;

            return current === ruta || current.startsWith(ruta + '/');
        }
    }
}