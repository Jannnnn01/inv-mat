<?= $this->extend('auth/layout') ?>

<?= $this->section('title') ?>Usuarios<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Usuarios</h1>
            <p class="text-body-secondary mb-0">Cuentas, estado y rol principal.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= url_to('dashboard') ?>">Dashboard</a>
            <a class="btn btn-primary" href="<?= url_to('admin-users-new') ?>">Crear usuario</a>
        </div>
    </div>

    <?php foreach (['message' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $type): ?>
        <?php if (session($key) !== null): ?>
            <div class="alert alert-<?= $type ?>"><?= esc(session($key)) ?></div>
        <?php endif ?>
    <?php endforeach ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th>Rol principal</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $currentRole = $user->getGroups()[0] ?? 'viewer'; ?>
                    <tr>
                        <td><?= esc($user->username ?? '—') ?><?= (int) $user->id === $currentId ? ' (tú)' : '' ?></td>
                        <td><?= esc($user->email ?? '—') ?></td>
                        <td><span class="badge text-bg-<?= $user->active ? 'success' : 'secondary' ?>"><?= $user->active ? 'Activo' : 'Inactivo' ?></span></td>
                        <td>
                            <form class="d-flex gap-2" method="post" action="<?= url_to('admin-users-role', $user->id) ?>">
                                <?= csrf_field() ?>
                                <select class="form-select form-select-sm" name="role" aria-label="Rol principal" <?= (int) $user->id === $currentId ? 'disabled' : '' ?>>
                                    <?php foreach ($roles as $key => $role): ?>
                                        <option value="<?= esc($key) ?>" <?= $currentRole === $key ? 'selected' : '' ?>><?= esc($role['title']) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" type="submit" <?= (int) $user->id === $currentId ? 'disabled' : '' ?>>Guardar</button>
                            </form>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <?php if ($user->active): ?>
                                    <form method="post" action="<?= url_to('admin-users-invite', $user->id) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Reenviar acceso</button>
                                    </form>
                                <?php endif ?>
                                <form method="post" action="<?= url_to('admin-users-status', $user->id) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-<?= $user->active ? 'danger' : 'success' ?>" type="submit" <?= (int) $user->id === $currentId ? 'disabled' : '' ?>>
                                        <?= $user->active ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($users === []): ?>
                    <tr><td class="text-center text-body-secondary py-4" colspan="5">No hay usuarios registrados.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3"><?= $pager->links() ?></div>
</div>
<?= $this->endSection() ?>
