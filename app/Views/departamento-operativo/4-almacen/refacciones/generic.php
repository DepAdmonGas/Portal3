<section class="card mb-0"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><div><h2 class="h5 mb-1"><?= $escape($sectionName) ?></h2><p class="text-muted small mb-0">Información limitada a las ubicaciones autorizadas para tu usuario.</p></div></div>
    <?php if ($items === []): ?><div class="refacciones-empty text-center py-5"><i class="ti ti-inbox fs-8 text-muted"></i><p class="text-muted mt-2 mb-0">No hay registros para mostrar en esta sección.</p></div>
    <?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><?php foreach (array_keys($items[0]) as $column): ?><th><?= $escape(ucwords(str_replace('_', ' ', $column))) ?></th><?php endforeach; ?></tr></thead><tbody>
        <?php foreach ($items as $row): ?><tr><?php foreach ($row as $value): ?><td><?= $escape(is_scalar($value) ? $value : '') ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div></section>
