<!doctype html>
<html lang="es">
<body>
    <p>Hola, <?= esc($user->username ?? 'usuario') ?>.</p>
    <p>
        <?= $invitation
            ? 'Se creó una cuenta para ti en el sistema de inventario.'
            : 'Se solicitó restablecer tu acceso al sistema de inventario.' ?>
    </p>
    <p><a href="<?= esc($accessUrl) ?>">Establecer una contraseña segura</a></p>
    <p>El enlace vence en <?= esc((string) $expiresIn) ?> minutos y solo puede utilizarse una vez.</p>
    <p>Si no esperabas este mensaje, puedes ignorarlo.</p>
</body>
</html>
