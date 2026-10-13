# Ajuste de interfaz 02: cuenta visible junto al cierre de sesión

Base confirmada: 025d8f8e6841ec3fd9b282aa1b74786c4bbc3d81,
feature/validacion-entrega limpia y sincronizada. Continúa ajuste 01 de logout.
Cubre presentación de identidad/acceso RF01/RF02 y usabilidad §14.10 del maestro
1.3. No modifica reglas de permiso, sesión o especificación.

Nombre y rol se trasladan desde el pie de la barra lateral a una tarjeta junto
al botón Cerrar sesión en la cabecera sticky. Nombre destacado, rol con etiqueta
legible y avatar decorativo con inicial. Botón azul con texto completo. El nombre
se escapa y permite ajuste de línea; el rol proviene de la identidad autenticada.
El avatar se oculta a <=420 px para dejar espacio al nombre y al botón. En móvil
la tarjeta usa la anchura disponible en una fila bajo Soporte interno.

Se conserva un único formulario POST /logout con token CSRF por cuenta activa.
Invitado no ve tarjeta/botón. No hay dependencias, migraciones o nueva configuración.

## Aplicación y verificación PowerShell

Descargar ins-tickets-08-cuenta-visible.patch a Descargas y desde raíz:

```powershell
$base = '025d8f8e6841ec3fd9b282aa1b74786c4bbc3d81'
if ((git rev-parse HEAD).Trim() -ne $base) { throw 'La base cambió; compartir commit.' }
if (git status --porcelain) { throw 'Hay cambios locales; compartir git status.' }
$patch = Join-Path $env:USERPROFILE 'Downloads\ins-tickets-08-cuenta-visible.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'Check falló; compartir salida sin forzar.' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'Aplicación falló; compartir salida.' }
docker compose restart app
docker compose exec -T app php -l app/Views/layouts/base.php
```

Sintaxis esperada: No syntax errors detected. En localhost:8080 actualizar con
Ctrl+F5. Revisar nombre/rol correctos junto al botón, ausencia del bloque anterior
al pie y cabecera visible al bajar por un detalle largo. Probar tres roles y ancho
móvil 320/390 px, sin desplazamiento horizontal ni texto del botón recortado.
Probar nombres largos. Tab/Enter permiten cerrar sesión; regreso a login y una
URL privada pide autenticación. Login no muestra tarjeta ni Cerrar sesión.

## Evidencia y pendientes

Asistente: plantilla PHP válida en auxiliar 8.3.6. Render de tres roles con nombre
normal/largo/texto HTML: nombre escapado, etiqueta de rol correcta, identidad en
cabecera y único formulario POST/CSRF. Render invitado sin logout. Parche check/
aplicación en base exacta de los siete parches anteriores y comparación de archivos;
.env/vendor preservados. No se ejecutó navegador, scroll ni logout con BD reales
para este ajuste. Verificación visual/local y nuevo commit pendientes.

Las evidencias automatizadas recibidas previamente identifican 431faea... y no
este nuevo cambio de interfaz. Al cerrar código para el paquete final, actualizar
pruebas/manifiestos al commit limpio de entrega conforme a INCREMENTO_06.md;
la matriz debe registrar solo comprobaciones y revisores reales.

## Commit: lo hace el usuario desde VS Code

```powershell
git add app/Views/layouts/base.php public/assets/css/app.css docs/AJUSTE_02_CUENTA.md
git --no-pager diff --cached --stat
git --no-pager diff --cached --check
git commit -m "fix(ui): reunir identidad y cierre de sesion en la cabecera"
git push
git status
git rev-parse HEAD
```

Resumen compartible: ajuste de cuenta preparado contra 025d8f8...; identidad/rol
junto a logout en cabecera sticky, tarjeta responsive y salida escapada. POST/CSRF
conservados; sintaxis/render/parche comprobados en auxiliar. Revisión visual/local,
commit y evidencias de la versión final pendientes. No se modifica maestro 1.3.
