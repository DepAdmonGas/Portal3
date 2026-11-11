class ModuleStationSelector {
    static _instances = {};
    static dataTableMap = {
        'solicitud-gafetes': '#table-gafetes',
        'solicitud-tarjetas': '#table-tarjetas',
        'corte-diario': '#table-corte-diario',
    };

    constructor(moduleKey) {
        this.moduleKey = moduleKey;
        this.selector = document.getElementById('module-station-selector-' + moduleKey);
        this.badge = document.getElementById('module-station-badge-' + moduleKey);
        this.loadEmpty = this.selector ? this.selector.dataset.loadEmpty === 'true' : true;
        this._customReload = null;
        this._confirmedValue = this.selector ? this.selector.value : '';
        this._bindChange();
    }

    _bindChange() {
        if (!this.selector) return;
        this.selector.addEventListener('change', () => {
            if (this._saving) {
                this._pendingChange = true;
                return;
            }
            this._saving = true;
            this._pendingChange = false;
            this.selector.disabled = true;
            var requestedValue = this.selector.value;
            var previousValue = this._confirmedValue;
            this.saveContext(requestedValue).then(() => {
                this._confirmedValue = requestedValue;
                this.updateBadge();
                this._saving = false;
                this.selector.disabled = false;
                if (this._pendingChange) {
                    this._pendingChange = false;
                    this.selector.dispatchEvent(new Event('change'));
                } else {
                    try {
                        this.reloadDataTable(previousValue);
                    } catch (e) {
                        console.error('[ModuleStationSelector] Error al recargar datos:', e);
                    }
                }
            }).catch(() => {
                this._saving = false;
                this.selector.disabled = false;
                this._pendingChange = false;
                this.selector.value = this._confirmedValue;
                this.updateBadge();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo guardar la selección. Intente de nuevo.', timer: 3000, showConfirmButton: false });
                }
            });
        });
    }

    getValue() {
        if (!this.selector) return { id_estacion: null, id_depto: null };
        var val = this.selector.value;
        if (!val) return { id_estacion: null, id_depto: null };
        if (val.startsWith('depto_')) return { id_estacion: null, id_depto: parseInt(val.replace('depto_', '')) };
        return { id_estacion: parseInt(val.replace('estacion_', '')), id_depto: null };
    }

    saveContext(value) {
        var selectorValue = value === undefined ? (this.selector ? this.selector.value : '') : value;
        var v = { id_estacion: null, id_depto: null };
        if (selectorValue) {
            if (selectorValue.startsWith('depto_')) {
                v.id_depto = parseInt(selectorValue.replace('depto_', ''));
            } else {
                v.id_estacion = parseInt(selectorValue.replace('estacion_', ''));
            }
        }
        return axios.post('/api/module-context/set', {
            module_key: this.moduleKey,
            id_estacion: v.id_estacion,
            id_depto: v.id_depto
        }).then(function (response) {
            if (!response || !response.data || response.data.ok !== true) {
                throw new Error('El servidor no confirmó el contexto de Checador.');
            }
            return response;
        });
    }

    updateBadge() {
        if (!this.badge) return;
        if (!this.selector) return;
        var opt = this.selector.options[this.selector.selectedIndex];
        if (opt && this.selector.value) {
            this.badge.textContent = opt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
            this.badge.style.display = '';
        } else {
            this.badge.style.display = 'none';
        }
    }

    reloadDataTable(previousValue) {
        if (this._customReload) {
            this._customReload(this, previousValue);
            return;
        }
        var tableSelector = ModuleStationSelector.dataTableMap[this.moduleKey];
        if (tableSelector && window.$ && $.fn && $.fn.DataTable && $.fn.DataTable.isDataTable(tableSelector)) {
            $(tableSelector).DataTable().ajax.reload(null, false);
        } else {
            window.location.reload();
        }
    }

    hideBadge() {
        if (!this.badge) return;
        this.badge.textContent = '';
        this.badge.style.display = 'none';
    }

    static init(moduleKey, options) {
        options = options || {};
        if (!ModuleStationSelector._instances[moduleKey]) {
            ModuleStationSelector._instances[moduleKey] = new ModuleStationSelector(moduleKey);
        }
        var inst = ModuleStationSelector._instances[moduleKey];
        if (options.customReload) {
            inst._customReload = options.customReload;
        }
        return inst;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[id^="module-station-selector-"]').forEach(function (el) {
        var moduleKey = el.dataset.moduleKey;
        if (moduleKey) {
            ModuleStationSelector.init(moduleKey);
        }
    });
});
