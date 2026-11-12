<!-- Filtro por responsable (se imprime únicamente para el usuario 496) -->
<div class="d-inline-flex align-items-center bg-light border rounded-pill px-3 py-1 gap-1">
    <span class="text-muted fw-semibold text-nowrap d-flex align-items-center gap-1">
        <i class="ti ti-user fs-4 text-primary"></i>
        <label for="se-responsable" class="form-label mb-0 small text-secondary fw-semibold">Responsable:</label>
    </span>
    <select id="se-responsable" class="form-select form-select-sm border-0 bg-transparent fw-semibold w-auto shadow-none" style="min-width:200px;" x-model="idResponsable" @change="cargarTabla()">
        <option value="">Todos los responsables</option>
        <template x-for="r in responsables" :key="r.id">
            <option :value="r.id" x-text="r.nombre"></option>
        </template>
    </select>
</div>
