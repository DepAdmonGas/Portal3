<div class="mt-3">
  <div class="action-btn layout-top-spacing d-flex align-items-center justify-content-between flex-wrap">
    <h4 class="fw-semibold"><?=$title;?></h4>

    <div class="d-flex flex-wrap gap-6">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-help"></i> Ayuda</a></li>
            </ul>
        </div>
    </div>


  </div>
</div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a class="link-info text-decoration-none" href="">SGM</a>
        </li>
        <li class="breadcrumb-item" aria-current="page"><?=$title;?></li>
    </ol>
</nav>

<div class="row mt-4">
<div class="col-md-6">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Dispensadores de Combustible</h4>
      <div class="ms-auto">
        <button type="button" class="btn">
        <i class="ti ti-plus fs-7 text-primary"></i>
        </button>
      
      </div>
  </div>
Calibración regular y revisión técnica semestral o de acuerdo a lo que indique el proveedor y la normatividad para los medidores de flujo, garantizando mediciones precisas y confiables.

Inspecciones mensuales y reemplazo preventivo cada 5 años (o de acuerdo a lo que indique el proveedor y la normatividad) de mangueras y boquillas para prevenir fugas y riesgos de seguridad.

Mantenimiento preventivo y mejoras en la protección contra el clima para pantallas y teclados, reduciendo fallos operativos.

Actualizaciones automáticas y auditorías de software anuales para el sistema de control electrónico, asegurando operaciones sin interrupciones y seguridad de datos.
<hr>
Tanques de Almacenamiento

Calibración regular e inspecciones trimestrales (o de acuerdo a lo que indique el proveedor y la normatividad) de sensores de nivel para mantener un inventario preciso y prevenir sobrellenados.

Limpieza semestral y revisión de integridad estructural del sistema de ventilación para evitar presiones excesivas y riesgos de explosión.

Revisión y mantenimiento anual y actualización tecnológica cada 10 años (o de acuerdo a lo que indique el proveedor y la normatividad) del sistema de recuperación de vapores para cumplir con regulaciones ambientales y reducir emisiones.
<hr>
Sistema de Software de Gestión

Backups diarios y sistemas de seguridad cibernética robustos para proteger la base de datos de transacciones contra corrupción de datos y ataques cibernéticos.

Pruebas de usuario continuas y actualizaciones basadas en feedback para mejorar la interfaz de usuario y asegurar eficiencia operativa.
                    
  </div>
</div>

</div>

<div class="col-md-6">

<div class="card">
  <div class="card-body">

  <div class="d-flex align-items-center">
    <h4 class="card-title mb-0">Fo.SGM.001 Lista de asistencia</h4>
      <div class="ms-auto">
      <button type="button" class="btn">
        <i class="ti ti-plus fs-7 text-primary"></i>
        </button>
      </div>
  </div>

  <div class="datatables mt-3">
    <div class="table-responsive">
      <table id="table-lista-comprobacion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>#</th>
            <th>Fecha</th>
            <th>Hora</th>
          <th class="text-center">
          <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
                    
  </div>
</div>
 

</div>
</div>
