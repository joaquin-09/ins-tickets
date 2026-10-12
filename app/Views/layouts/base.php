<?php

use InsTickets\Core\View;
use InsTickets\Core\Auth;
use InsTickets\Core\Csrf;
use InsTickets\Core\Session;

$currentUser = Auth::user();
$flash = Session::takeFlash();

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::escape($title ?? 'INS Tickets') ?> · NexoTI</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <a href="#contenido" class="skip-link">Saltar al contenido</a>
    <div class="app-shell <?= $currentUser === null ? 'guest-shell' : '' ?>">
    <?php if ($currentUser !== null): ?>
        <aside class="sidebar">
            <a class="brand" href="/">INS Tickets<span>NexoTI Solutions</span></a>
            <nav aria-label="Navegación principal">
                <a href="/">Inicio</a>
                <?php if ($currentUser->role === 'administrador'): ?>
                    <a href="/usuarios">Usuarios</a>
                    <a href="/categorias">Categorías</a>
                <?php endif; ?>
                <a href="/cuenta/password">Mi contraseña</a>
            </nav>
            <div class="sidebar-account">
                <strong><?= View::escape($currentUser->name) ?></strong><span><?= View::escape($currentUser->role) ?></span>
                <form method="post" action="/logout"><input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>"><button class="btn btn-outline-light w-100 mt-3" type="submit">Cerrar sesión</button></form>
            </div>
        </aside>
    <?php endif; ?>
    <div class="workspace">
        <header class="workspace-header"><span>Soporte interno</span><span>Proyecto académico</span></header>
        <main id="contenido" class="content" tabindex="-1">
            <?php if ($flash !== null): ?><div class="alert alert-info" role="status"><?= View::escape($flash) ?></div><?php endif; ?>
            <?= $content ?>
        </main>
        <footer class="content pt-0 text-secondary">Organización ficticia · INS104</footer>
    </div></div>
</body>
</html>
