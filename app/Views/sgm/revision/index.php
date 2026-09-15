<div id="container" class="pb-4" x-data="{ ...actions(), ...editRevision(<?= $id ?>)}">

    <div
class="text-end mb-3"
x-show="revision.estado==0">
    
<button
class="btn btn-success"
@click="finalizar()">
<i class="ti ti-check"></i>
Finalizar 
</button>



</div>

<div class="card">
    <div class="card-body">
    <div class="row g-3">

    <div class="col-md-4">

    <label class="form-label mb-1">Fecha:</label>

    <input
    type="date"
    class="form-control"

    x-model="revision.fecha"

    @change="actualizar('fecha',revision.fecha)"
    >

    </div>

    <div class="col-md-4">

    <label class="form-label mb-1">Hora:</label>

    <input
    type="time"
    class="form-control"
    x-model="revision.hora"
    @change="actualizar('hora',revision.hora)"
    >

    </div>

    <div class="col-md-4">

    <label class="form-label mb-1">Lugar:</label>

    <input
    class="form-control"
    x-model="revision.lugar"
    @blur="actualizar('lugar',revision.lugar)"
    >
    </div>

    </div>
    </div>
    </div>

    <div class="mt-3">
    <template
        x-for="(items,categoria) in revision.categorias"
        :key="categoria"
    >


<!-- card -->
<div class="card">
    <div class="card-header card-colored-header bg-primary">
 <h4 class="card-title text-white mb-0" x-text="categoria"></h4>
    </div>
      <div class="card-body">
    <div class="row">
        
      
                
            <template
            x-for="item in items"
            :key="item.id"
        >
        <div class="col-12 mb-3">
    

                <label class="form-label" x-text="item.pregunta"></label>

                <textarea
                    class="form-control"
                    x-model="item.respuesta"
                    @blur="actualizarDetalle(item)"

                ></textarea>

       

        </template>

        
    </div>
 
    </div>

</div>
</div>

   

    </template>


</div>



