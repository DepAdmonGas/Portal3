<?php
declare(strict_types=1);

$moduleNavigation = is_array($moduleNavigation ?? null) ? $moduleNavigation : [];
$isModuleDirectory = ($isModuleDirectory ?? false) === true;
$featuredModule = $moduleNavigation[0] ?? null;
$secondaryModules = array_slice($moduleNavigation, 1);
?>
<?php if ($moduleNavigation !== []): ?>
    <section class="<?= $isModuleDirectory ? 'mt-3 mb-4' : 'mt-4' ?>" aria-label="Directorio de módulos de Checador">
        <nav aria-label="Módulos de Checador" class="d-grid gap-3">
            <?php if (is_array($featuredModule)): ?>
                <?php
                $featuredLabel = (string) ($featuredModule['label'] ?? 'Asistencia del día');
                $featuredDescription = (string) ($featuredModule['description'] ?? 'Consulta las checadas, horarios y estado de asistencia del personal de tu estación.');
                $featuredUtilities = array_values(array_filter(array_map('trim', explode('·', (string) ($featuredModule['utility'] ?? ''))), static fn (string $item): bool => $item !== ''));
                $featuredIcon = (string) ($featuredModule['icon'] ?? 'ti-calendar-check');
                $featuredHref = is_string($featuredModule['href'] ?? null) ? $featuredModule['href'] : '';
                $featuredAvailable = ($featuredModule['available'] ?? false) === true && $featuredHref !== '';
                ?>
                <?php if ($featuredAvailable): ?>
                    <a href="<?= htmlspecialchars($featuredHref, ENT_QUOTES, 'UTF-8') ?>" class="d-block text-decoration-none focus-ring focus-ring-primary rounded-3">
                <?php endif; ?>
                <article class="card body-container-do overflow-hidden card-hover shadow-sm rounded-3 mb-0" data-checador-featured-card<?= $featuredAvailable ? '' : ' aria-disabled="true"' ?>>
                    <div class="row g-0 align-items-stretch">
                        <div class="col-12 col-md-3 col-lg-2 icon-container-do text-center">
                            <span class="rounded-3 bg-primary text-white p-4 d-inline-flex align-items-center justify-content-center">
                                <i class="ti <?= htmlspecialchars($featuredIcon, ENT_QUOTES, 'UTF-8') ?> fs-13 text-white" aria-hidden="true"></i>
                            </span>
                        </div>
                        <div class="col p-4 p-lg-5 d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h2 class="display-6 fw-bolder text-white mb-3"><?= htmlspecialchars($featuredLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                                <p class="fs-7 text-white mb-4"><?= htmlspecialchars($featuredDescription, ENT_QUOTES, 'UTF-8') ?></p>
                                <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0" aria-label="Contenido incluido">
                                    <?php foreach ($featuredUtilities as $item): ?>
                                        <li><span class="badge rounded-pill bg-white text-primary fw-semibold px-3 py-2"><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (!$featuredAvailable): ?>
                                    <small class="d-block mt-3 text-white"><i class="ti ti-lock me-1" aria-hidden="true"></i>Acceso según permisos del perfil.</small>
                                <?php endif; ?>
                            </div>
                            <?php if ($featuredAvailable): ?>
                                <i class="ti ti-chevron-right fs-9 text-white ms-4 d-none d-md-inline-block" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php if ($featuredAvailable): ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                <?php foreach ($secondaryModules as $module): ?>
                    <?php
                    $label = (string) ($module['label'] ?? 'Módulo');
                    $description = (string) ($module['description'] ?? 'Consulta información disponible.');
                    $utilities = array_values(array_filter(array_map('trim', explode('·', (string) ($module['utility'] ?? ''))), static fn (string $item): bool => $item !== ''));
                    $icon = (string) ($module['icon'] ?? 'ti-layout-grid');
                    $href = is_string($module['href'] ?? null) ? $module['href'] : '';
                    $available = ($module['available'] ?? false) === true && $href !== '';
                    ?>
                    <div class="col" data-checador-module-card>
                        <?php if ($available): ?>
                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="d-block h-100 text-decoration-none focus-ring focus-ring-primary rounded-3">
                        <?php endif; ?>
                        <article class="card bg-body border rounded-3 shadow-sm card-hover h-100 mb-0"<?= $available ? '' : ' aria-disabled="true"' ?>>
                            <div class="card-body d-flex align-items-center gap-3 p-3 p-lg-4">
                                <span class="rounded-3 bg-primary-subtle text-primary p-3 d-inline-flex align-items-center justify-content-center flex-shrink-0">
                                    <i class="ti <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> fs-9" aria-hidden="true"></i>
                                </span>
                                <div class="d-flex flex-column flex-grow-1 h-100">
                                    <h3 class="card-title fs-6 fw-bolder mb-2"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p class="text-body-secondary mb-3"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                                    <ul class="list-unstyled d-flex flex-wrap gap-2 mt-auto mb-0" aria-label="Contenido incluido">
                                        <?php foreach ($utilities as $item): ?>
                                            <li><span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2 py-1"><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (!$available): ?>
                                        <small class="mt-2 text-secondary"><i class="ti ti-lock me-1" aria-hidden="true"></i>Acceso según permisos del perfil.</small>
                                    <?php endif; ?>
                                </div>
                                <?php if ($available): ?>
                                    <i class="ti ti-chevron-right fs-7 text-primary align-self-center flex-shrink-0" aria-hidden="true"></i>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php if ($available): ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </nav>
    </section>
<?php endif; ?>
