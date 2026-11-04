<?php if ($success !== ''): ?>
    <div class="notice notice-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($dbError): ?>
    <div class="notice notice-error">No se pudo consultar la base de datos. Revisá el registro de errores del servidor.</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="notice notice-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
