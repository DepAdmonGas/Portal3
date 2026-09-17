<div id="container" class="mt-4 mb-5"
     data-id-year="<?= $idYear ?>"
     data-id-mes="<?= $idMes ?>"
     data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>">

<style>
@media (min-width: 992px) {
    #container .table-responsive { overflow-x: auto; }
}
</style>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-precios" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

</div>
