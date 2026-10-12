# INS Tickets

Sistema de soporte interno para la organización **ficticia** NexoTI Solutions.
Proyecto académico INS104. Diseño definitivo: maestro 1.3, sección 14.
Entrega prevista: miércoles 14 de octubre de 2026.

## Estado: incremento 01-base

Base de origen del parche: `4754a95b71f2ff39a7c47bc4f37850b68ffdeb1f` (árbol vacío).
MVC, configuración, autoload PSR-4, entorno PHP/Apache y MySQL, esquema de seis
tablas, semilla ficticia y verificaciones por consola. **No hay autenticación,
CRUD web, atención ni paneles/reportes todavía.** La portada declara ese estado.
Ningún RF01–RF15 se considera aceptado por este incremento de infraestructura.

## Tecnologías y estructura

PHP 8.4.x, MVC propio, PDO nativo, MySQL 8.4.x InnoDB/utf8mb4, Composer 2,
Bootstrap 5.3.8 local y PHPUnit 12.5.x (dependencia preparada para los módulos).
Las imágenes de referencia se fijan por digest en Compose y Dockerfile;
`composer.lock` fija el árbol de dependencias. Los parches no ejecutan commits.

- `public/`: única raíz pública; controlador frontal y recursos locales.
- `app/Controllers`, `Models`, `Services`, `Repositories`, `Views`: módulos MVC.
- `app/Core`: infraestructura mínima. Reglas de negocio se agregarán en servicios.
- `bootstrap/`, `config/`: configuración privada y rutas internas.
- `database/`: esquema y privilegios, ejecutados por mantenimiento.
- `docker/`, `compose.yaml`: instalación de referencia.
- `scripts/`: instalación, semilla y verificaciones CLI, no rutas web.
- `storage/`: logs, sesiones, caché y evidencia técnica privada, fuera de Git.
- `docs/`: instalación, trazabilidad y estado real del incremento.

## Instalación Docker: PowerShell desde la raíz

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup-env.ps1
docker compose config --quiet
docker compose build app
docker compose up -d db
docker compose run --rm --no-deps app composer install --no-interaction --prefer-dist
docker compose run --rm app php scripts/check.php
docker compose run --rm app php scripts/seed.php
docker compose up -d app
docker compose ps
docker compose exec -T app php scripts/check-demo.php
docker compose exec -T app php scripts/lint.php
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\record-runtime.ps1
```

Ejecutar cada comando y detenerse si falla. `Bypass` solo aplica al proceso
invocado para este script; no modifica la política permanente de Windows.
La primera descarga/construcción necesita Internet. Después de construir las
imágenes e instalar Composer, la web usa recursos locales sin CDN.
Visitar <http://localhost:8080>. Debe mostrar la portada del incremento 1.
El registro de versiones se crea tras el arranque; no acredita aceptación.

La semilla exige una base totalmente vacía y se ejecuta **una vez**. Si se repite,
rechaza la operación sin eliminar ni sobrescribir registros. No realizar resets.
`docker compose stop` conserva contenedores y datos. Para continuar:
`docker compose up -d`. Evitar eliminar los volúmenes del proyecto.

## Datos ficticios

Cuentas locales: `admin@nexoti.test`, `agente1@nexoti.test`,
`agente2@nexoti.test`, `usuario1@nexoti.test`, `usuario2@nexoti.test`.
Contraseña compartida exclusivamente para demo local: `DemoLocal2026!`.
No se pueden usar aún en la web: el acceso corresponde al incremento 2.
Hashes bcrypt costo 12; nunca se guarda la contraseña en las tablas.

Semilla esperada: 5 usuarios, 5 categorías, 4 tickets (uno por estado),
2 comentarios, 13 eventos, 0 intentos de acceso. Fechas UTC relativas al momento
de creación. Promedios sintéticos esperados: respuesta 2.00 h n=2;
resolución 6.00 h n=2. Son fixtures, no métricas de atención real ni pruebas
ejecutadas. `check-demo.php` es de lectura y exige esa semilla sin cambios.

## Comprobaciones y próximos módulos

Ver `docs/INCREMENTO_01.md` para resultados realmente ejecutados en el entorno
de preparación, limitaciones y el registro de verificación local pendiente.
Ver `docs/INSTALACION_MANUAL.md` para la misma aplicación sin Docker.
Próximos: acceso/administración; tickets; atención; paneles/reportes; validación
y entrega. No etiquetar `v1.0.0` hasta satisfacer aceptación de RF01–RF15.

## Seguridad e integridad de esta base

Puerto web ligado a 127.0.0.1; MySQL sin puerto publicado. `.env` ignorado,
secretos aleatorios, raíz pública limitada, consultas PDO preparadas,
cabeceras defensivas, mensajes internos genéricos y cuenta MySQL `ins_app`
con privilegios por tabla. Sin DELETE sobre tickets ni UPDATE/DELETE sobre
comentarios o historial. PK, FK sin cascadas, unicidad, índices y CHECK en BD.
Permisos por rol, CSRF, sesiones e intentos se implementarán en incremento 2;
políticas de tickets y concurrencia, en incrementos 3–4.

Para futuras publicaciones remotas se requerirá HTTPS y cookies Secure; esta
instalación local usa HTTP y no constituye despliegue público.
