# Incremento 06: validación y entrega

Base confirmada: e64b4140140bd6dcb83cdbba8fb220c99ff58d76, feature/reportes
limpia y sincronizada. Maestro 1.3, §14.9–14.12. No cambia decisiones de producto.
El commit del incremento 5 está confirmado; sus pruebas locales y las del 4 no
se recibieron aquí. RF01–RF15 implementados, aceptación final aún pendiente.

## Contenido del módulo

Pruebas CSRF de las doce rutas de escritura; privilegios mínimos/CHECK/FK en
MySQL; validación HTTP real contra sitio/base temporales; cobertura PCOV; carga
de 500 tickets y medición p95; instalación limpia separada; respaldo byte a byte,
restauración en otra base y navegación de copia; manuales técnico/por rol, guion
de demo, matriz de aceptación y generador de paquete identificado por commit.

Corrección concreta de entrada: búsqueda q ahora rechaza bytes UTF-8 inválidos
antes de consultar MySQL, con validación 422 en vez de posible error interno.
Prueba unitaria específica incluida. No hay cambio de requisito o esquema.

## Aplicación PowerShell desde raíz

Descargar ins-tickets-06-validacion.patch en Descargas:

```powershell
$base = 'e64b4140140bd6dcb83cdbba8fb220c99ff58d76'
if ((git rev-parse HEAD).Trim() -ne $base) { throw 'La base cambió. Compartir commit.' }
if (git status --porcelain) { throw 'Hay cambios locales. Compartir git status.' }
git switch -c feature/validacion-entrega
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la rama.' }
$patch = Join-Path $env:USERPROFILE 'Downloads\ins-tickets-06-validacion.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'Check falló. Compartir salida sin forzar.' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'Aplicación falló. Compartir salida.' }
```

Se conservan .env, dependencias y datos. No reinicializar SQL/semilla de la demo.

```powershell
docker compose exec -T app composer install --no-interaction --prefer-dist
docker compose restart app
docker compose exec -T app php scripts/check.php
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\run-validation.ps1
```

El script se detiene ante fallo. Primera construcción de imagen de pruebas PCOV
requiere Internet. App de demo no se reconstruye. Bases de QA usan credenciales
ficticias y tmpfs, puertos MySQL sin publicar; detenerlas borra solo esos datos
temporales. No ejecutar integración en app con su BD demo.

Esperados: 91 archivos PHP válidos, 179 unitarias/483 aserciones; suite combinada
209 casos (179 + 30 integración), cobertura ≥80 % en ámbitos declarados, tres p95
<3 s con 20 mediciones/500 tickets, y checks HTTP completos. Ninguna cifra de
cobertura/p95 se afirma alcanzada antes de ejecutar. Si falla una meta, compartir
salida y reporte para corregir; no ocultar ni cambiar el ámbito. El script detiene
servidores temporales al terminar. Evidencia queda en storage/reports.

## Verificación humana y commit del módulo

Recorrer los tres roles conforme a MANUAL_USUARIO.md y DEMOSTRACION.md. Comprobar
formularios/mensajes, conjunto visible, cambios de estado, conflictos de dos
pestañas, filtros y teclado/móvil. Revisión por otro integrante real requerida.
Primero ejecutar pruebas; después crear el commit desde terminal de VS Code:

```powershell
git add .
git --no-pager diff --cached --stat
git --no-pager diff --cached --check
git commit -m "test(entrega): validar RF01-RF15 y preparar respaldo y demostracion"
git push -u origin feature/validacion-entrega
git status
git rev-parse HEAD
```

El commit identifica código/herramientas; no constituye aceptación completa.
No hay commits/tags creados por el asistente. Revisar que .env/vendor/reports/
backups no estén preparados. Si un comando falla, detenerse y compartir salida.

## Validación del commit e instalación/respaldo/restauración

Para que la evidencia final identifique el mismo commit limpio que el paquete,
repetir run-validation tras confirmar el código (la primera ejecución tuvo
source_dirty=true). Sus temporales ya se detuvieron y vuelven a inicializarse:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\run-validation.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\test-installation.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\backup.ps1
```

Revisar la instalación nueva en localhost:8082 con login por los tres roles y
un flujo breve. El script muestra carpeta/proyecto para detenerla después de
esa comprobación; sus volúmenes no son mysql_data de la demo.

backup informa una carpeta única. Usar la ruta REAL, no un nombre supuesto:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\restore-test.ps1 -BackupDirectory ".\backups\CARPETA_INFORMADA"
```

Esperado: seis conteos/hashes coincidentes y lectura ins_app OK. En localhost:8083
comprobar login/detalle/reportes de copia; las cuentas/claves son las del momento
del dump. Comparar con demo en 8080. No sobrescribe la base principal. No repetir
contra un db-restore con tablas: lo rechaza. Tras revisión se puede detener
web-restored/db-restore para retirar exclusivamente la copia temporal:

```powershell
docker compose -f compose.yaml -f compose.validation.yaml --profile validation stop web-restored db-restore
```

## Matriz y paquete final

Crear una copia privada de la matriz sin sobrescribir resultados anteriores:

```powershell
if (!(Test-Path .\storage\reports\acceptance.csv)) { Copy-Item .\docs\ACEPTACION.csv .\storage\reports\acceptance.csv }
code .\storage\reports\acceptance.csv
```

Completar únicamente con ejecución real: resultado PASS/FAIL/PENDIENTE, fecha,
commit, revisor real y referencia de evidencia; hallazgos/severidad cuando existan.
Los RF pueden apoyarse en pruebas automatizadas y revisión manual; no inventar
capturas, firmas o revisión independiente. Los casos instalación/restauración/
demo/teclado exigen revisión humana adicional a sus scripts.

Cuando todos los casos estén aceptados, sin crítico abierto y con documento final
del otro chat disponible (PDF o DOCX), usar su ruta REAL:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\make-release.ps1 -AcademicDocument "C:\RUTA_REAL\documento-final.pdf"
```

Se rechaza paquete si falta evidencia/matriz/commit limpio. El ZIP contiene código
versionado, documentos técnicos, resultados reales seleccionados y documento
académico, con manifiesto SHA256. SQL/.env/logs privados quedan aparte. La primera
instalación del código exportado requiere Composer; para clase usar la instalación
ensayada con dependencias e imágenes ya descargadas. El script no publica ni crea
etiqueta. Un paquete creado no sustituye revisión de su documento académico.

Después de aceptación, integrar la rama en main desde VS Code/GitHub conservando
la cadena acumulada de los incrementos. Si main tiene cambios ajenos o conflicto,
compartir estado y resolver con parche/revisión; no forzar ni resetear. Evitar
merge squash si se desea conservar todos los commits de evidencia. Registrar
nuevo SHA si el merge crea uno; regenerar evidencia/paquete de la versión entregada
cuando corresponda. La etiqueta final v1.0.0 la crea el usuario después de aceptar,
no antes. No se asume que main está lista o que ya se hizo el merge.

## Evidencia disponible y pendientes reales

Asistente: PHP 8.3.6 auxiliar Linux, PHPUnit 12.5.39. Ejecutadas 179 unitarias/
483 aserciones OK; lint 91 PHP OK; configuraciones Compose validadas; XML de suite
combinada y enumeración de 209 casos comprobados sin ejecutar MySQL. Verificadas
las guardas de scripts: rechazan entorno demo antes de conectar para fixtures/
HTTP/desempeño/restauración. Parser de cobertura comprobado con XML sintéticos,
sin atribuir esos porcentajes al proyecto. Parche check/aplicación sobre base
exacta de incrementos 1–5 y comparación de archivos, .env/vendor preservados.

Sin motor Docker/MySQL/PowerShell en este entorno: integración, construcción
PCOV, cobertura real, p95, flujo HTTP con BD, instalación nueva, backup/restauración,
paquete y aceptación manual no ejecutados aquí. Resultados locales del usuario
para incrementos 4 y 5 no recibidos. Sin defectos detectados en lo ejecutado;
las verificaciones no ejecutadas pueden revelar defectos y se corregirán mediante
parche contra el commit confirmado. No se declara sistema aceptado ni entrega final
hasta contar con evidencia. La matriz comienza completamente PENDIENTE.

## Resumen para análisis/documentación

> Incremento 6 preparado contra e64b4140140bd6dcb83cdbba8fb220c99ff58d76.
> RF01–RF15 tienen implementación; validación/entrega incorporan suite acumulada,
> cobertura, seguridad HTTP, rendimiento, instalación aislada, backup/restauración,
> manuales, aceptación, demo y generador de paquete. Búsqueda inválida UTF-8 corregida.
> Auxiliar: 179 unitarias/483 aserciones y 91 PHP válidos; Compose y parche comprobados.
> MySQL: 30 casos preparados; cobertura, p95, HTTP real con BD, PowerShell e instalación/
> restauración/paquete no ejecutados aquí. Commit 5 confirmado e64b414...; commit 6,
> evidencia Windows, revisión independiente, aceptación y documento final pendientes.
> No hay porcentaje inventado, certificación o aceptación final declarada.
