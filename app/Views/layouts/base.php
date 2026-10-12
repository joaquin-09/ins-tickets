<?php

use InsTickets\Core\View;

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
    <header class="brand-header"><div class="container py-3"><strong>INS Tickets</strong><span class="ms-3">NexoTI Solutions</span></div></header>
    <main id="contenido" class="container py-5" tabindex="-1"><?= $content ?></main>
    <footer class="container pb-4 text-secondary">Organización ficticia · Proyecto académico INS104</footer>
</body>
</html>
