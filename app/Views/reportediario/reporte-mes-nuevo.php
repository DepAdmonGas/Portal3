<div id="container" class="mb-4"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...corteNuevo({
        idReporteCre: <?= $idReporteCre ?>,
        modo: '<?= $modo ?? 'nuevo' ?>',
        fecha: '<?= $fecha ?? '' ?>'
     }) }">

<?php if (empty($estacionId)): ?>

    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

    <div id="sasisopa-content">
<div class="row mt-3">
   

          <div class="col-md-6">
        
            <div class="input-group mb-3">
            <span class="input-group-text fw-bolder">Fecha:</span>
            <input
                type="date"
                class="form-control"
                x-model="fecha"
                min="<?= $diamin ?>"
                max="<?= $diamax ?>"
                :class="{ 'is-invalid': errors.fecha }"
                @input="errors.fecha = false">
            </div>

            </div>
               <div class="col-md-6">
      <div class="text-end mb-3">
        <button
            class="btn btn-success"
            @click="submit()"
            :disabled="loading">
<i class="ti ti-check"></i>
            Finalizar

        </button>
      </div>
</div>
        

          </div>


<template x-for="(producto,index) in productos" :key="index">


  <!-----------card principal------>

<div class="card">
<div class="card-header text-white" :style="`background-color: var(--bs-${producto.color})`">
    <!-- Titulo -->
                <label>
                    <b x-text="producto.nombre"></b>
                </label>
</div>
        



 <div class="card-body pb-0">
<div class="row">
<div class="col-md-12">


<div class="card">
    <div class="card-header bg-primary">

                        <h5 class="mb-0 text-white"> <i class="ti ti-cash me-1"></i>
1. Agregar el volumen inicial, final y ventas en (Lt).</h5>
                    </div>
    


    <div class="card-body p-0 ">
  <!-- ========================= -->
                <!-- VOLUMEN -->
                <!-- ========================= -->
                   

                    <div class="table-responsive">

                        <table class="table table-striped table-bordered  align-middle mb-0">

                            <thead>

                            <tr>
                                <th class="text-center text-muted">
                                    Volumen (Lt) Inicial
                                </th>

                                <th class="text-center text-muted">
                                    Volumen (Lt) de venta
                                </th>

                                <th class="text-center text-muted">
                                    Volumen (Lt) Final
                                </th>

                            </tr>

                            </thead>

                            <tbody>

                            <tr>

                                <td class="p-0">

                                    <input
                                        type="number"
                                        min="0"
                                        step="any"
                                        class="form-control border-0 text-center"
                                        
                                        x-model="producto.volumen.inicial"
                                        :class="{ 'is-invalid': errors.volumen[index]?.inicial }"
                                        @input="errors.volumen[index].inicial = false">

                                </td>

                                <td class="p-0">

                                    <input
                                        type="number"
                                        min="0"
                                        step="any"
                                        class="form-control border-0 text-center"
                                        
                                        x-model="producto.volumen.venta"
                                        :class="{ 'is-invalid': errors.volumen[index]?.venta }"
                                        @input="errors.volumen[index].venta = false">

                                </td>

                                <td class="p-0">

                                    <input
                                        type="number"
                                        min="0"
                                        step="any"
                                        class="form-control border-0 text-center"
                                        
                                        x-model="producto.volumen.final"
                                        :class="{ 'is-invalid': errors.volumen[index]?.final }"
                                        @input="errors.volumen[index].final = false">

                                </td>

                            </tr>

                            </tbody>

                        </table>

                    </div>

          
    </div>

</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
    <div class="card">
<div class="card-header bg-primary">
<div class="row  align-items-center">


    <div class="col-9">
                        <h5 class="mb-0 text-white">                <i class="ti ti-truck me-1"></i>
2. Agregar el volumen de las compras de pipas.</h5>
                   
    </div>


    <div class="col-3 text-end">
 <a class="btn bg-success-subtle text-success"                   
                    href="javascript:void(0)"
                    @click="agregarPipa(producto)">
                    <i class="ti ti-plus"></i> Nuevo
                    </a>
    </div>
 
</div>
                   

</div>

<div class="card-body p-0 pb-0">
<!-- ========================= -->
                <!-- PIPAS -->
                <!-- ========================= -->
 

                    <div class="table-responsive">

                   

                        <table class="table table-striped table-bordered align-middle mb-0 " >

                            <thead>

                            <tr>

                                <th class="text-center align-middle">
                                    Volumen (Lt) de Compra
                                </th>

                                <th class="text-center align-middle">
                                    Precio ($) por litro de producto
                                </th>

                                <th class="text-center align-middle">
                                    Costo ($) del flete mas IVA
                                </th>

                                <th class="text-center align-middle">
                                    No. De factura
                                </th>

                                <th class="text-center align-middle">
                                    Nombre o Razón Social del Transportista
                                </th>

                                <th class="text-center align-middle">
                                    Importe
                                </th>

                                <th class="text-center align-middle">
                                   <i class="ti ti-trash fs-6"></i>
                                </th>

                            </tr>

                            </thead>

                            <tbody>

                            <template x-for="(pipa,i) in producto.pipas.filter(p => !p.eliminar)" :key="i">

                                <tr>

                                    <td>

                                        <div class="input-group">

                                            <span
                                                class="input-group-text border-0 rounded-0 bg-transparent"
                                                >

                                                Pipa <span x-text="i+1"></span>

                                            </span>

                                            <input
                                                type="number"
                                                min="0"
                                                step="any"
                                                class="form-control border-0 text-center"
                                                x-model="pipa.volumen"
                                                @input="calcularPrecio(pipa)">

                                        </div>

                                    </td>

                                    <td>

                                        <input
                                            type="number"
                                            min="0"
                                            step="any"
                                            class="form-control border-0 rounded-0  text-center"
                                            x-model="pipa.precio"
                                            readonly
                                            tabindex="-1">

                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="number"
                                            min="0"
                                            step="any"
                                            class="form-control border-0 text-center"
                                            x-model="pipa.costo">

                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="text"
                                            class="form-control border-0 text-center"
                                            x-model="pipa.factura">

                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="text"
                                            class="form-control border-0 text-center"
                                            x-model="pipa.transportista">

                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="number"
                                            min="0"
                                            step="any"
                                            class="form-control border-0 text-center"
                                            x-model="pipa.importe"
                                            @input="calcularPrecio(pipa)">

                                    </td>

                                    <td class="text-center align-middle">
                                    <a href="javascript:void(0)" @click="eliminarPipa(producto,i)">
                                    <i class="ti ti-trash text-danger fs-6"></i>
                                    </a>

                                    </td>

                                </tr>

                            </template>

                            </tbody>

                        </table>

                    </div>


</div>
    </div>
</div>
</div>

              

                

            
        </div>
       


        </div>
<!-----------aqui termina el card principal------>

</template>



    </div>

    <?php endif; ?>

</div>