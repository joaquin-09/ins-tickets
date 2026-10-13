# Manual técnico — INS Tickets, línea base 1.3

PHP 8.4.x/Apache, MVC propio, MySQL 8.4.x/InnoDB/utf8mb4, Composer 2 con lock,
Bootstrap 5.3.8 local y PHPUnit 12.5.x. No requiere CDN ni SMTP. La aplicación
usa ins_app, jamás root. Puerto web 127.0.0.1:8080; MySQL no publica puerto.

## Instalación y arranque

Instalación nueva: seguir README, generar .env una sola vez, construir app,
levantar db, composer install, check.php, seed.php una vez y levantar app.
Semilla rechaza base no vacía. Instalación existente: docker compose up -d;
composer install si falta vendor; nunca repetir inicialización SQL/semilla.
Conservar .env fuera de Git. Documentar versiones con record-runtime.ps1.
Alternativa sin Docker en INSTALACION_MANUAL.md; no cambiar MySQL por MariaDB.

public/index.php aplica cabeceras, inicia sesión, refresca cuenta desde BD y
resuelve rutas. Core ofrece config, PDO, autorización, CSRF, entradas y vistas.
Controllers reciben HTTP; Services imponen políticas/transacciones; Repositories
consultan BD; Views escapan texto. public es la única raíz expuesta.

Seis tablas: usuarios, categorias, tickets, comentarios, historial_tickets,
intentos_acceso. Esquema/índices/CHECK/FK en database/schema.sql y privilegios
mínimos en permissions.sql. Tickets no se borran; comentarios/eventos no se
actualizan/borran por ins_app. Fechas UTC; presentación America/El_Salvador.
Migración inicial existente: no hay modificación de esquema en incremento 6.

Escrituras del agregado: bloqueo transaccional, versión y registro atómico.
Un formulario obsoleto recibe 409. Comentario de soporte/primera respuesta/
versión se confirman juntos. No se duplica el comentario como evento. Reportes
leen una instantánea REPEATABLE READ sin alterar aislamiento de otras transacciones.

## Seguridad y operación

bcrypt costo 12, CSRF en todas las escrituras, PDO sin emulación, HTML escapado,
rol/activo refrescados por petición, cookies HttpOnly/SameSite Lax y expiración
30 minutos. HTTP es solo local; acceso remoto exige HTTPS/Secure. HMAC de
correo/IP para fallos de login; secreto en .env. No publicar logs/sesiones/dumps.
Errores privados en storage/logs y mensajes HTTP controlados. No afirmar cifrado
de la base ni certificación de seguridad. La contraseña tiene hash, no cifrado
reversible. El proyecto no incluye adjuntos, reapertura, notas privadas o API.

Para parar sin perder demo: docker compose stop. Para volver: docker compose
up -d. Evitar down -v y resets. Los servidores db-test/db-validation/db-restore
usan tmpfs temporal: sus datos desaparecen al detenerlos; mysql_data conserva
la demo. No confundir esas bases. Fuente: https://docs.docker.com/engine/storage/tmpfs/.

## Verificación

run-validation.ps1 ejecuta lint, 179 unitarias/483 aserciones esperadas, suite
acumulada de 209 casos (179 unitarias + 30 MySQL), cobertura, rendimiento y
seguridad HTTP. PCOV 1.0.12 solo en imagen ins-tickets-tests; la app de demo
continúa con su imagen original. Fuente: https://pecl.php.net/package/pcov/1.0.12.

Cobertura: todas las clases app/Services, incluidas no ejecutadas. Meta ≥80 %
de líneas en ámbito completo y en cinco servicios transaccionales críticos
(AuthService, UserService, CategoryService, TicketService, AttentionService).
El resumen presenta porcentajes por archivo. No equivale a cobertura de ramas,
controllers/repos o procesos HTTP/auxiliares. Si la meta falla, corregir pruebas
pertinentes; no alterar el ámbito para aparentar cumplimiento.

Desempeño: 500 tickets sintéticos en db-validation, 20 peticiones por lista/
detalle/reportes, administrador autenticado. p95 nearest rank (19.ª muestra
ordenada), meta estrictamente <3 s. Se excluyen arranque, instalación, calentamiento
y login; login se informa aparte. Medición HTTP desde contenedor, incluye Apache,
sesión y BD, no renderizado del navegador. Documentar Windows/Docker/recursos;
comparar percepción del navegador durante aceptación. No son tiempos empresariales.

HTTP automático en entorno aislado: acceso/cookies/cabeceras, archivos privados,
CSRF, permisos, ticket ajeno, creación inválida/válida/doble envío, texto XSS
escapado, comentario propio, versión obsoleta, asignación, inicio, comentario,
resolución, cierre, rechazo cerrado, reporte vacío/inválido, cuenta desactivada
y logout. Los resultados se escriben solo después de ejecutar, con passed real.
El script retira únicamente el ticket que creó en db-validation.

## Instalación nueva, respaldo y restauración

Tras commit limpio: test-installation.ps1 exporta HEAD y crea carpeta/proyecto
Docker independientes con puerto 8082. Comprueba build/Composer/BD/semilla/lint/
login; requiere además revisión humana de navegación. No elimina esa instalación
ni la demo. La carpeta y proyecto se informan al ejecutar. Detenerla al terminar.

backup.ps1 exige HEAD limpio, pausa app temporalmente si estaba activa y la vuelve
a levantar al terminar. Guarda código de HEAD, SQL mediante mysqldump dentro de db,
y manifiesto de conteos/hashes por tabla; conserva bytes con docker cp, evitando
redirección de PowerShell 5.1. Durante la pausa no escribir con otros clientes.
Conservar .env por separado de forma privada. El dump contiene hashes de claves.

restore-test.ps1 exige dump/manifiesto coincidentes bajo backups, destino db-restore
sin tablas y esquema ins_tickets. Importa allí, repone privilegios y compara datos
de las seis tablas por conteos/SHA256 con acceso ins_app. Inicia copia web en 8083
para revisión de login/tickets/reportes. Rechaza un destino con tablas; no lo borra.
Para repetir el ensayo, parar únicamente web-restored y db-restore (tmpfs temporal).
No orientar scripts de restauración a db; recuperar una exposición en instalación
separada comprobada, manteniendo original hasta verificar. No ejecutar resets.

## Evidencia y paquete

storage/reports es privado e ignorado: JUnit, cobertura HTML/XML/resumen, versiones,
HTTP, desempeño, restauración, instalación y acceptance.csv. Guardar capturas
reales propias fuera del repo o en esa carpeta; nunca inventar ejecución/revisores.
Registrar hallazgos con severidad, reproducción, versión, responsable y comprobación
posterior. Corregir mediante parche contra commit confirmado, conservando datos.

make-release.ps1 exige HEAD limpio, evidencias automáticas del mismo commit,
aceptación de todos los casos y ausencia de defecto crítico, más documento académico
PDF/DOCX. Exporta código, evidencias seleccionadas, documento y hashes. SQL/.env/
logs/sesiones quedan fuera del paquete compartible. Mantener respaldo SQL privado.
No crea commits, tags o publicaciones. No etiquetar v1.0.0 antes de aceptación.
