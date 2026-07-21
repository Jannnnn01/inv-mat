<?= $this->extend('layouts/app') ?>

<?= $this->section('title') ?>Usuarios<?= $this->endSection() ?>

<?= $this->section('main') ?>
<section class="app-page-section" aria-labelledby="users-title">
    <div class="app-page-header">
        <div>
            <p class="app-eyebrow mb-1">Administración</p>
            <h1 class="h2 mb-1" id="users-title">Usuarios</h1>
            <p class="app-page-description mb-0">Gestiona las cuentas, su estado y el rol principal asignado.</p>
        </div>
        <div class="app-page-actions">
            <a class="btn btn-outline-secondary" href="<?= url_to('dashboard') ?>">Volver al dashboard</a>
            <a class="btn btn-primary" href="<?= url_to('admin-users-new') ?>">
                <span aria-hidden="true">+</span>
                Crear usuario
            </a>
        </div>
    </div>

    <?= $this->include('partials/flash') ?>

    <div class="card app-table-card">
        <div class="app-table-responsive">
            <table class="table app-data-table align-middle mb-0">
                <caption class="visually-hidden">Listado de usuarios del sistema</caption>
                <thead>
                    <tr>
                        <th scope="col">Usuario</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Rol principal</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $currentRole = $user->getGroups()[0] ?? 'viewer';
                    $username    = $user->username ?? 'Usuario';
                    $isCurrent   = (int) $user->id === $currentId;
                    ?>
                    <tr>
                        <td data-label="Usuario">
                            <div class="app-user-identity">
                                <span class="app-user-avatar" aria-hidden="true"><?= esc(strtoupper(substr($username, 0, 1))) ?></span>
                                <span>
                                    <strong class="d-block"><?= esc($username) ?></strong>
                                    <?php if ($isCurrent): ?>
                                        <span class="app-current-user">Tu cuenta</span>
                                    <?php endif ?>
                                </span>
                            </div>
                        </td>
                        <td class="app-email-cell" data-label="Correo"><?= esc($user->email ?? '—') ?></td>
                        <td data-label="Estado">
                            <span class="app-status app-status-<?= $user->active ? 'active' : 'inactive' ?>">
                                <span class="app-status-dot" aria-hidden="true"></span>
                                <?= $user->active ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td data-label="Rol principal">
                            <form class="app-inline-form" method="post" action="<?= url_to('admin-users-role', $user->id) ?>" data-loading-form>
                                <?= csrf_field() ?>
                                <label class="visually-hidden" for="role-<?= (int) $user->id ?>">Rol principal de <?= esc($username) ?></label>
                                <select class="form-select form-select-sm" id="role-<?= (int) $user->id ?>" name="role" <?= $isCurrent ? 'disabled' : '' ?>>
                                    <?php foreach ($roles as $key => $role): ?>
                                        <option value="<?= esc($key) ?>" <?= $currentRole === $key ? 'selected' : '' ?>><?= esc($role['title']) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" type="submit" <?= $isCurrent ? 'disabled title="No puedes cambiar tu propio rol"' : '' ?>>Guardar</button>
                            </form>
                        </td>
                        <td data-label="Acciones">
                            <div class="app-row-actions">
                                <?php if ($user->active): ?>
                                    <form method="post" action="<?= url_to('admin-users-invite', $user->id) ?>" data-loading-form>
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Reenviar acceso</button>
                                    </form>
                                <?php endif ?>
                                <form method="post" action="<?= url_to('admin-users-status', $user->id) ?>" data-loading-form>
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-<?= $user->active ? 'danger' : 'success' ?>" type="submit" <?= $isCurrent ? 'disabled title="No puedes desactivar tu propia cuenta"' : '' ?>>
                                        <?= $user->active ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($users === []): ?>
                    <tr><td class="app-empty-state" colspan="5">No hay usuarios registrados.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4"><?= $pager->links('default', 'app_full') ?></div>
</section>
<?= $this->endSection() ?>
