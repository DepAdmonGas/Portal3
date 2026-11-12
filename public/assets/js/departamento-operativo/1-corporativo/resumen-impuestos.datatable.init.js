document.addEventListener('DOMContentLoaded', () => {

  const c = document.getElementById('resumenImpuestosContainer');
  if (!c) return;

  const idYear = parseInt(c.dataset.idYear);
  const idMes = parseInt(c.dataset.idMes);
  const verShell = [2, 14].includes(parseInt(c.dataset.idEstacion, 10));
  if (!idYear || !idMes) return;

  function fmt(val) {
    return '$' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
  }

  function fmt2(val) {
    return '$' + parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmt4(val) {
    return parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
  }

  function fmt2n(val) {
    return parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  fetch('/departamento-operativo/resumen-aceites-mes/data/' + idYear + '/' + idMes)
    .then(r => r.json())
    .then(json => {
      if (!json.success) {
        c.innerHTML = '<div class="alert alert-danger mt-4"><i class="ti ti-alert-circle me-1"></i>' + (json.message || 'Error al cargar datos') + '</div>';
        return;
      }

      let html = '<div class="col-12 mt-4 mb-4">' +
        '<div class="table-responsive mb-3">' +
        '<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">' +
          '<thead>' +
            '<tr><th colspan="10" class="text-center align-middle"><strong>Resumen de Impuestos</strong></th></tr>' +
            '<tr>' +
              '<th class="text-center align-middle">Producto</th>' +
              '<th class="text-center align-middle">Precio al Público</th>' +
              '<th class="text-center align-middle">IEPS</th>' +
              '<th class="text-center align-middle">Precio Sin IVA</th>' +
              '<th class="text-center align-middle">IVA</th>' +
              '<th class="text-center align-middle">Volumen Vendido</th>' +
              '<th class="text-center align-middle">Importe Sin IVA</th>' +
              '<th class="text-center align-middle">IVA</th>' +
              '<th class="text-center align-middle">IEPS</th>' +
              '<th class="text-center align-middle">Total</th>' +
            '</tr></thead><tbody>';

      json.items.forEach(function (row, index) {
        html += '<tr class="pointer" data-selectable-row="impuesto-' + index + '">' +
          '<th class="text-center align-middle">' + row.producto + '</th>' +
          '<td class="text-center align-middle">' + fmt(row.precio_litro) + '</td>' +
          '<td class="text-center align-middle">' + fmt4(row.ieps) + '</td>' +
          '<td class="text-center align-middle">' + fmt4(row.precio_sin_iva) + '</td>' +
          '<td class="text-end align-middle">' + fmt4(row.iva_unidad) + '</td>' +
          '<td class="text-end align-middle">' + fmt2n(row.volumen_vendido) + '</td>' +
          '<td class="text-end align-middle">' + fmt2(row.importe_sin_iva) + '</td>' +
          '<td class="text-end align-middle">' + fmt2(row.iva_total) + '</td>' +
          '<td class="text-end align-middle">' + fmt2(row.ieps_total) + '</td>' +
          '<td class="text-end align-middle">' + fmt2(row.total) + '</td>' +
        '</tr>';
      });

      var comb = json.combustibles;
      html += '<tr class="table-dark fw-semibold">' +
        '<td colspan="5" class="text-end align-middle">Subtotal Combustibles</td>' +
        '<td class="text-end align-middle">' + fmt2n(comb.volumen) + '</td>' +
        '<td class="text-end align-middle">' + fmt2(comb.importe_sin_iva) + '</td>' +
        '<td class="text-end align-middle">' + fmt2(comb.iva) + '</td>' +
        '<td class="text-end align-middle">' + fmt2(comb.ieps) + '</td>' +
        '<td class="text-end align-middle">' + fmt2(comb.total) + '</td>' +
      '</tr>' +
      '<tr>' +
        '<th colspan="6" class="text-end align-middle">Aceites</th>' +
        '<td class="text-end align-middle">' + fmt2(json.aceites_sin_iva) + '</td>' +
        '<td class="text-end align-middle">' + fmt2(json.aceites_iva) + '</td>' +
        '<td></td>' +
        '<td class="text-end align-middle">' + fmt2(json.aceites_total) + '</td>' +
      '</tr>' +
      '<tr class="table-dark fw-semibold">' +
        '<td colspan="6" class="text-end align-middle">Total del día</td>' +
        '<td class="text-end align-middle">' + fmt2(json.total_dia.importe_sin_iva) + '</td>' +
        '<td class="text-end align-middle">' + fmt2(json.total_dia.iva) + '</td>' +
        '<td class="text-end align-middle">' + fmt2(json.total_dia.ieps) + '</td>' +
        '<td class="text-end align-middle">' + fmt2(json.total_dia.total) + '</td>' +
      '</tr></tbody></table></div></div>';

      var m = json.m;
      const columnasTarjetas = [
        ['ticketcard', 'TICKETCARD'],
        ['g500fleet', 'TICKETCARD+'],
        ['efecticard', 'EFECTICARD'],
        ['sodexo', 'SODEXO'],
        ['ultragas', 'ULTRAGAS'],
        ['energex', 'ENERGEX'],
      ];
      if (verShell) columnasTarjetas.push(['shell', 'SHELL']);

      html += '<div class="col-12">' +
        '<div class="table-responsive mb-5">' +
        '<table id="table-resumen-aceites-monedero" class="table table-bordered table-striped mb-0 text-nowrap align-middle">' +

          '<thead>' +
            '<tr><th colspan="' + (18 + (verShell ? 1 : 0)) + '" class="text-center"><strong>Resumen Monederos</strong></th></tr>' +
            '<tr>' +
              '<th class="text-center" colspan="' + (12 + (verShell ? 1 : 0)) + '">Tarjetas</th>' +
              '<th class="text-center" colspan="6">Cartera de Clientes ATIO</th>' +
            '</tr>' +
            '<tr>' +
              '<th class="text-center" colspan="5">Tarjetas Bancarias</th>' +
              '<th class="text-center" colspan="' + (7 + (verShell ? 1 : 0)) + '">Tarjetas</th>' +
              '<th class="text-center" colspan="2">Crédito</th>' +
              '<th class="text-center" colspan="2">Débito</th>' +
              '<th class="text-center">Pagos</th>' +
              '<th class="text-center">Consumos</th>' +
            '</tr>' +
            '<tr>' +
              '<th class="text-center">BANCOMER</th>' +
              '<th class="text-center">AMEX</th>' +
              '<th class="text-center">INBURGAS</th>' +
              '<th class="text-center">INBURSA</th>' +
              '<th class="text-center">Total</th>' +
              columnasTarjetas.map(columna => '<th class="text-center">' + columna[1] + '</th>').join('') +
              '<th class="text-center">Total</th>' +
              '<th class="text-center">Pagos</th>' +
              '<th class="text-center">Consumos</th>' +
              '<th class="text-center">Pagos</th>' +
              '<th class="text-center">Consumos</th>' +
              '<th class="text-center">Total</th>' +
              '<th class="text-center">Total</th>' +
            '</tr></thead><tbody>' +
            '<tr class="table-dark pointer" data-selectable-row="monedero">' +
              '<td class="text-end align-middle">' + fmt2(m.bancomer) + '</td>' +
              '<td class="text-end align-middle">' + fmt2(m.amex) + '</td>' +
              '<td class="text-end align-middle">' + fmt2(m.inburgas) + '</td>' +
              '<td class="text-end align-middle">' + fmt2(m.inbursa) + '</td>' +
              '<td class="text-end align-middle"><strong>' + fmt2(m.total_tb) + '</strong></td>' +
              columnasTarjetas.map(columna => '<td class="text-end align-middle">' + fmt2(m[columna[0]]) + '</td>').join('') +
              '<td class="text-end align-middle"><strong>' + fmt2(m.total_tarjetas) + '</strong></td>' +
              '<td class="text-end align-middle">' + fmt2(m.pago_credito) + '</td>' +
              '<td class="text-end align-middle">' + fmt2(m.consumo_credito) + '</td>' +
              '<td class="text-end align-middle">' + fmt2(m.pago_debito) + '</td>' +
              '<td class="text-end align-middle">' + fmt2(m.consumo_debito) + '</td>' +
              '<td class="text-end align-middle"><strong>' + fmt2(m.total_pago) + '</strong></td>' +
              '<td class="text-end align-middle"><strong>' + fmt2(m.total_consumo) + '</strong></td>' +
            '</tr></tbody></table></div></div>';

      c.innerHTML = html;
      c.addEventListener('click', function (event) {
        const row = event.target.closest('tbody tr[data-selectable-row]');
        if (!row || row.classList.contains('selected')) return;

        const table = row.closest('table');
        if (!table) return;

        table.querySelectorAll('tbody tr.selected').forEach(function (selectedRow) {
          selectedRow.classList.remove('selected');
        });
        row.classList.add('selected');
      });
    });
});
