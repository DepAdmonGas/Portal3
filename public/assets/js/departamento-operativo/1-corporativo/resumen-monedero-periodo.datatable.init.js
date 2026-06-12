document.addEventListener('DOMContentLoaded', () => {

  const c = document.getElementById('resumenPeriodoContainer');
  if (!c) return;

  const idYear = parseInt(c.dataset.idYear);
  const idMes = parseInt(c.dataset.idMes);
  if (!idYear || !idMes) return;

  function formatMoney(val) {
    return '$' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  const keys = ['Toinburgas', 'Toticketcard', 'Tog500fleet', 'Toefecticard', 'Tosodexo', 'Toultragas', 'Toenergex'];

  fetch('/departamento-operativo/resumen-monedero/periodo-data/' + idYear + '/' + idMes)
    .then(r => r.json())
    .then(json => {
      if (!json.success) return;
      if (!json.periodos || json.periodos.length === 0) {
        c.innerHTML = '<div class="alert alert-warning border-0 text-center py-4 mt-4">No hay datos disponibles para el periodo seleccionado.</div>';
        return;
      }

      let html = '<div class="table-responsive mt-4">' +
        '<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">' +
          '<thead><tr>' +
            '<th class="text-center align-middle"></th>' +
            '<th class="text-center align-middle"></th>' +
            '<th class="text-center align-middle fw-bold">INBURGAS</th>' +
            '<th class="text-center align-middle fw-bold">TICKETCARD</th>' +
            '<th class="text-center align-middle fw-bold">G500 FLETT</th>' +
            '<th class="text-center align-middle fw-bold">EFECTICARD</th>' +
            '<th class="text-center align-middle fw-bold">SODEXO</th>' +
            '<th class="text-center align-middle fw-bold">ULTRAGAS</th>' +
            '<th class="text-center align-middle fw-bold">ENERGEX</th>' +
            '<th class="text-center align-middle fw-bold">TOTAL</th>' +
            '<th class="text-center align-middle fw-bold">VALE ACCORD</th>' +
            '<th class="text-center align-middle fw-bold">VALE EFECTIVALE</th>' +
            '<th class="text-center align-middle fw-bold">VALE SODEXO</th>' +
            '<th class="text-center align-middle fw-bold">SI VALE</th>' +
            '<th class="text-center align-middle fw-bold">Total</th>' +
          '</tr></thead><tbody>';

      json.periodos.forEach(function (p) {
        html += '<tr>' +
          '<td class="text-nowrap">' + p.label + '</td>' +
          '<td class="text-center">' + p.hasta + '</td>';
        keys.forEach(function (k) {
          html += '<td class="text-end">' + formatMoney(p.data[k]) + '</td>';
        });
        html += '<td class="text-end fw-bold bg-light">' + formatMoney(p.primer_total) + '</td>';
        html += '<td class="text-end">' + formatMoney(p.data.Tovalaccord) + '</td>' +
          '<td class="text-end">' + formatMoney(p.data.Tovalefectivale) + '</td>' +
          '<td class="text-end">' + formatMoney(p.data.Tovalsodexo) + '</td>' +
          '<td class="text-end">' + formatMoney(p.data.Tovalvale) + '</td>' +
          '<td class="text-end fw-bold bg-light">' + formatMoney(p.segundo_total) + '</td>' +
        '</tr>';
      });

      const t = json.totales;
      html += '</tbody><tfoot><tr class="table-dark fw-semibold">' +
        '<th colspan="2">TOTAL</th>';
      keys.forEach(function (k) {
        html += '<td class="text-end">' + formatMoney(t[k]) + '</td>';
      });
      html += '<td class="text-end">' + formatMoney(t.primer_total) + '</td>';
      html += '<td class="text-end">' + formatMoney(t.Tovalaccord) + '</td>' +
        '<td class="text-end">' + formatMoney(t.Tovalefectivale) + '</td>' +
        '<td class="text-end">' + formatMoney(t.Tovalsodexo) + '</td>' +
        '<td class="text-end">' + formatMoney(t.Tovalvale) + '</td>' +
        '<td class="text-end">' + formatMoney(t.segundo_total) + '</td>' +
      '</tr></tfoot></table></div>';

      c.innerHTML = html;
    });
});
