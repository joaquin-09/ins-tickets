# Alternativa manual: mismo diseño 1.3

Requiere PHP 8.4.x, Composer 2, MySQL 8.4.x y extensiones pdo_mysql, mbstring,
dom, xml, xmlwriter y zip. No sustituir MySQL por MariaDB/XAMPP sin evaluación.

Desde la raíz, en PowerShell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup-env.ps1 -Manual
composer install --no-interaction --prefer-dist
```

No sobrescribir un `.env` existente. En una instalación manual nueva, la cuenta
MySQL de mantenimiento debe crear la base y `ins_app`. El secreto generado
DB_PASSWORD se consulta **solo localmente** en `.env` y se usa al crear la cuenta;
no se publica ni se incorpora al historial de Git. Desde `mysql -u root -p`:

```sql
CREATE DATABASE ins_tickets CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'ins_app'@'%' IDENTIFIED BY 'REEMPLAZAR_POR_DB_PASSWORD_LOCAL';
SOURCE database/schema.sql;
SOURCE database/permissions.sql;
```

Iniciar la consola mysql desde la raíz para que SOURCE resuelva rutas. Permisos
requieren mantenimiento, pero la aplicación jamás utiliza root. MySQL debe
escuchar solo en loopback si se ejecuta directamente en Windows.

Preparar directorios `storage/logs`, `sessions`, `cache` y `reports` con escritura
para el usuario que ejecuta PHP. Confirmar sesión y log en configuración PHP
privada; errores en pantalla desactivados. Después:

```powershell
php scripts/check.php
php scripts/seed.php
php scripts/check-demo.php
php scripts/lint.php
php -S 127.0.0.1:8080 -t public scripts/dev-router.php
```

El servidor incorporado PHP sirve solo para desarrollo/demo local. Visitar
http://localhost:8080. No usarlo para publicación. En Apache, fijar DocumentRoot
a `public/`, desactivar índices y redirigir rutas no estáticas a `index.php`,
siguiendo `docker/apache/000-default.conf`.

En HTTP local las cookies no serán Secure; acceso remoto requiere HTTPS y
configuración correspondiente en el módulo de sesiones. Login aún pendiente.
No probar restauraciones ni borrar registros sobre la base de exposición.
