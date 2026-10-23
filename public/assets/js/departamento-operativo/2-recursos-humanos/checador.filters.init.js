document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-checador-filter-modal]').forEach(function (modal) {
        var filterForm = modal.querySelector('[data-checador-filter-form]');
        if (filterForm) {
            filterForm.addEventListener('submit', function (event) {
                if (!event.submitter || !event.submitter.hasAttribute('data-checador-clear-filters')) return;
                ['date', 'from_date', 'to_date', 'worker_id'].forEach(function (name) {
                    var field = filterForm.querySelector('[name="' + name + '"]');
                    if (field) field.value = '';
                });
            });
        }
        modal.addEventListener('hidden.bs.modal', function () {
            if (filterForm) filterForm.reset();
        });
    });

    var csvForm = document.querySelector('[data-checador-csv-form]');
    var filterForm = document.getElementById('punch-report-filter-form');
    if (!csvForm || !filterForm) return;

    csvForm.addEventListener('submit', function () {
        ['dt_search', 'dt_order_column', 'dt_order_direction'].forEach(function (name) {
            var source = filterForm.querySelector('[name="' + name + '"]');
            var target = csvForm.querySelector('[name="' + name + '"]');
            if (source && target) target.value = source.value;
        });
    });
});
