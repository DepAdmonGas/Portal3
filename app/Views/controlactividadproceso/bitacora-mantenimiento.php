<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>


<div class="row mt-4">
  
  <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-2 mt-2">
    <div class="card">
      <div class="card-header bg-primary">
    <h5 class="text-white mb-0">
      <i class="ti ti-label"></i>
      Mantenimiento Preventivo</h5>

      </div>
    <div class="card-body">
      <p>Su objetivo es mantener un nivel de servicio determinado en los equipos mediante la planificación de acciones de mantenimiento orientadas a evitar que se produzcan incidencias y fallos. Para ello, utilizan información basada en el histórico de funcionamiento del aparato y en sus características.</p>
      <p>Las intervenciones se programan de manera sistemática, aunque el equipo no haya dado ningun síntoma de fallo, y se centran especialmente sobre aquellos componentes mas vulnerables. Este tipo de mantenimiento permite ampliar el tiempo de funcionamiento de los equipos en las condiciones apropiadas y ayuda a evitar o reducir las paradas inesperadas.</p>
      <div class="text-end">
        
    </div>
    </div>
    <div class="card-footer">
<a type="button" href="/sasisopa/control-actividades-procesos/bitacora-mantenimiento-quincenal" class="btn bg-primary-subtle text-primary"> <i class="ti ti-tool"></i> Mantenimiento Quincenal</a>
        </div>
  </div>
  </div>
  

  <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-2 mt-2">
    <div class="card">
      <div class="card-header bg-primary">
    <h5 class="text-white mb-0">
      <i class="ti ti-label"></i>
      Mantenimiento Correctivo</h5>
      </div>
    <div class="card-body">
    <p>Se refiere él conjunto de acciones ejecutadas para a corregir incidencias que van presentándose. Es completamente reactivo, la acción se lleva a cabo una vez que se ha producido la incidencia. A partir de ahí los usuarios informan al departamento de mantenimiento que diagnóstica las causas y trata de buscar soluciones. Este nivel de mantenimiento no permite actuar de manera proactiva antes de que se produzca el falló.</p>
  </div>
  </div>
  </div>
 

    <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-2 mt-2">
      <div class="card">
        <div class="card-header bg-primary">
        <h5 class="text-white mb-0">
          <i class="ti ti-label"></i>Mantenimiento Predictivo</h5>
        </div>
    <div class="card-body">
        <p class="card-text">Este tipo de mantenimiento es capaz de predecir cuando van a producirse averías y solucionarlas antes de que sucedan. En base a toda la información recogida, a las condiciones de funcionamiento y a las acciones realizadas previamente, el sistema detecta fallos potenciales y actúa de acuerdo a un conjunto de acciones previas diseñado para evitar que ocurran las incidencias.</p>
        <p class="card-text">Busca conocer en tiempo real y permanente el estado y la capacidad de funcionamiento de las instalaciones. Para ello, identifica y mide múltiples parámetros representativos que ayudan a describir ese estado general y cuya variación pueda indicar potenciales problemas para el equipo. Esto ayuda a mejorar la eficiencia de los equipos de producción.</p>
    </div>
    </div>
    </div>

  </div>
  <?php endif; ?>
