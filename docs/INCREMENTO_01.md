# Incremento 01-base: registro de entrega

Línea base funcional: maestro 1.3, sección 14.
Base Git del destinatario: `4754a95b71f2ff39a7c47bc4f37850b68ffdeb1f`, árbol vacío.
Rama: `feature/base-proyecto`. Commit de aplicación: **pendiente de Daniel/equipo**.
Origen remoto confirmado: https://github.com/joaquin-09/ins-tickets.git.

## Implementación preparada

MVC con controlador frontal, autoload PSR-4, vistas en español, Bootstrap
5.3.8 local con licencia MIT, PHP/Apache 8.4 y MySQL 8.4 mediante Compose.
Imágenes fijadas por digest oficial; Composer lock con PHPUnit 12.5.39.
Seis tablas, FK sin cascadas, unicidad, índices, CHECK de integridad y
privilegios mínimos por tabla. Configuración de ejemplo, generación de secretos
en PowerShell, semilla transaccional sin sobrescritura, healthchecks y scripts
de lectura/lint/manifiesto. Alternativa manual incluida.

Soporta técnicamente RF01–RF15, pero no implementa todavía sus flujos web.
Autenticación, permisos de roles, CRUD, CSRF, atención, paneles y reportes
siguen pendientes. La infraestructura por sí sola no acredita aceptación RF.

## Comprobaciones ejecutadas durante preparación

Entorno auxiliar Linux: PHP CLI **8.3.6**, Composer 2 y Docker Compose 2.39.4
como validador de configuración, **sin motor Docker ni servidor MySQL**.
PHP 8.4 sigue siendo el entorno de referencia y no se modificó la especificación.

| Comprobación real | Observado | Límite |
|---|---|---|
| Sintaxis de los 17 archivos PHP | OK | PHP 8.3.6; repetir con PHP 8.4 del contenedor. |
| Composer validate --strict | OK | Manifiesto/lock coherentes. |
| Resolución e instalación de 25 dependencias dev | OK, PHPUnit 12.5.39 | Resolución para plataforma 8.4; instalación auxiliar omitiendo exclusivamente el requisito PHP del proyecto. No usar esa omisión en la instalación local. |
| Compose config --quiet | OK | Secretos temporales solo para validar; no se construyeron ni ejecutaron contenedores. |
| GET / mediante servidor incorporado PHP | 200, portada del incremento 1 | No es una prueba de Apache ni de MySQL. |
| GET CSS Bootstrap local | 200 | Recurso 5.3.8 descargado y entregado localmente. |
| GET /no-existe, /.env, /composer.json, /database/schema.sql y /app/Core/Config.php | 404 en los cinco casos | Raíz public en servidor auxiliar; repetir con Apache. |
| Cabeceras defensivas en portada | CSP y X-Frame-Options: DENY presentes | No constituye auditoría de seguridad integral. |
| git apply --check y aplicación del parche en árbol vacío | OK | Prueba sobre árbol vacío, no sobre el equipo del usuario. |
| Comparación del contenido aplicado | Todos los archivos coinciden byte a byte | Sin crear commits en nombre del usuario. |

No se ejecutaron PHPUnit de negocio, SQL en MySQL, semilla, transacciones de
BD, scripts PowerShell, instalación Docker ni aceptación de usuarios. No se
declara cobertura ni porcentaje de avance. No hay defectos confirmados en las
comprobaciones listadas; persiste el riesgo de ejecución pendiente del entorno
de referencia y será diagnosticado con la salida real del usuario.

## Verificación local pendiente

Ejecutar los comandos del README, de uno en uno, y detenerse si un comando
devuelve error. No ejecutar la semilla de nuevo sobre una base con datos.

| Comprobación | Esperado, aún no observado localmente |
|---|---|
| docker compose ps | app y db en ejecución, ambos healthy tras el arranque. |
| scripts/check.php | PHP 8.4.x, MySQL 8.4.x, ins_app y seis tablas. |
| scripts/seed.php | 5 usuarios, 5 categorías, 4 tickets, 2 comentarios, 13 eventos. |
| scripts/check-demo.php | Un ticket por estado; respuesta 2.00 h n=2 y resolución 6.00 h n=2; hashes costo 12. |
| scripts/lint.php | 17 archivos PHP OK en PHP 8.4. |
| Navegador localhost:8080 | Portada real, sin botones de funciones pendientes. |
| URL /.env y /database/schema.sql | 404, sin contenido privado. |
| scripts/record-runtime.ps1 | Manifiesto privado sin secretos, con versiones reales y digests/IDs. |

## Resumen para análisis y documentación

> Incremento 01-base preparado conforme al maestro 1.3. Base:
> 4754a95b71f2ff39a7c47bc4f37850b68ffdeb1f; commit de aplicación pendiente.
> Se entregan MVC, Composer, Docker PHP/Apache/MySQL, configuración, esquema
> de seis tablas y datos ficticios. Comprobaciones auxiliares: sintaxis PHP,
> Composer, Compose y HTTP de portada/recursos/rutas privadas aprobadas dentro
> del entorno descrito. Instalación Docker, MySQL, semilla y verificaciones en
> Windows pendientes. RF01–RF15 aún no aceptados. Continúa incremento 2:
> acceso, roles, sesiones, usuarios, contraseña propia y categorías.

Antes de compartirlo como avance ejecutado, agregar commit local, versión
del entorno, verificaciones observadas, defectos e integrante revisor real.
