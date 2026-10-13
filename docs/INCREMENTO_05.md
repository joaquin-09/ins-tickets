# Incremento 05: paneles y reportes

Base confirmada: `7dc07fd56a9d09b11b1228dd0896442d3104b508`, rama
feature/atencion limpia y sincronizada. Maestro 1.3, §14.7 y §14.8.
El usuario crea el commit del módulo; no se creó por él.

## Funciones y requisitos

- RF12: panel Inicio. Usuario: solicitudes propias. Agente: unión de propias
  y actualmente asignadas sin duplicar. Administrador: todos los tickets.
  Total, cuatro estados y tres prioridades calculados desde la base.
  Usuario/agente no reciben promedios globales ni acceso a reportes.
- RF13: reporte administrativo con total/estado/prioridad/categoría/agente
  actualmente asignado y agrupación independiente por quien resolvió.
  Incluye Sin asignar, categorías inactivas, agentes históricos y resolución
  por administrador. Cerrado se cuenta como anteriormente resuelto.
- RF14: primera respuesta y resolución promedio calculadas desde fechas UTC
  de los tickets, tiempo natural en segundos, presentado en horas con dos
  decimales y n. Sin muestra: Sin datos. Cero horas solo si la muestra válida
  realmente tiene tiempo cero. Un resuelto sin primera respuesta participa
  únicamente en el promedio de resolución.

Filtros comunes: fecha de creación desde/hasta, estado, prioridad, categoría,
agente actual. Cada filtro afecta todas las cifras. Por defecto todo el conjunto.
Desde inclusivo = medianoche local convertida a UTC; hasta exclusivo = inicio
local del día siguiente convertido a UTC. No se filtra por fecha de resolución.
Desde posterior a hasta o datos no válidos: HTTP 422, entradas conservadas,
sin ejecutar reporte ni sustituir el filtro inválido por todos los tickets.
Fechas válidas entre 1000-01-01 y 9998-12-31, evitando desbordar el límite
DATETIME con el extremo exclusivo. No se agrega filtro por resolutor.

Agregaciones por categoría, agente actual y resolutor paginadas a 20 grupos,
con orden estable y parámetros preparados. Cambiar una página conserva filtros
y páginas de otros grupos; conteos/promedios siguen correspondiendo al conjunto
completo filtrado. Una transacción de lectura REPEATABLE READ mantiene la misma
instantánea entre agregaciones, sin bloqueos de escritura ni cambios de privilegios.
Tablas con encabezados, labels y mensajes comprensibles. No hay exportaciones.

## Aplicación desde VS Code / PowerShell

Descargar ins-tickets-05-reportes.patch a Descargas. Desde la raíz del repo:

```powershell
$base = '7dc07fd56a9d09b11b1228dd0896442d3104b508'
if ((git rev-parse HEAD).Trim() -ne $base) { throw 'La base cambió; compartir el commit actual.' }
if (git status --porcelain) { throw 'Hay cambios locales; compartir git status antes de aplicar.' }
git switch -c feature/reportes
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la rama; detenerse.' }
$patch = Join-Path $env:USERPROFILE 'Downloads\ins-tickets-05-reportes.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'El parche no coincide; compartir la salida, sin forzar.' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'No se pudo aplicar; compartir la salida.' }
```

Si Descargas tiene otra ubicación, cambiar solo $patch. No copiar el parche
al repo ni renombrar archivos del proyecto. Si la rama ya existe, compartir
estado en lugar de borrar ramas. El parche no toca .env/vendor/datos y no
requiere cambios SQL, imágenes o composer.lock. No repetir semilla/setup-env.

```powershell
docker compose exec -T app composer install --no-interaction --prefer-dist
docker compose restart app
docker compose exec -T app php scripts/check.php
docker compose exec -T app php scripts/lint.php
docker compose exec -T app php vendor/bin/phpunit --log-junit storage/reports/unit-05.xml
docker compose -f compose.yaml -f compose.test.yaml --profile test config --quiet
docker compose -f compose.yaml -f compose.test.yaml --profile test run --rm tests
docker compose -f compose.yaml -f compose.test.yaml --profile test stop db-test
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\record-runtime.ps1
```

Ejecutar uno a uno; ante fallo detener la secuencia y compartir salida. Se puede
parar db-test después de una prueba fallida para liberar recursos. No ejecutar
la integración sobre la base demo: db-test tiene tmpfs separado y credenciales
ficticias. JUnit privado en storage/reports/integracion.xml y unit-05.xml.

Esperados: sintaxis 81 PHP OK; unitarias 165/467; integración 28 casos
acumulados aprobados, sin errores. Estos son esperados locales, no resultados
Windows observados. Integración incluye las pruebas pendientes de atención.

## Verificación manual (resultados esperados)

1. Usuario entra a http://localhost:8080: el panel coincide con sus solicitudes
   y no muestra promedios globales. /reportes por URL devuelve 403.
2. Agente: panel comprende propias y asignadas; comparar con ambas listas.
   /reportes devuelve 403. Cuenta sin tickets ve total cero y mensaje claro.
3. Admin: panel total global; abrir /reportes desde menú. Por defecto, comparar
   total/cuatro estados con gestión global de tickets. Comparar prioridades.
4. Filtrar un día con tickets creados (fechas locales del detalle), luego estado,
   prioridad, categoría y agente. Todas las tablas/promedios deben corresponder
   a los mismos tickets. Limpiar filtros devuelve el conjunto completo.
5. Probar sin_asignar y una categoría inactiva con referencias. Verificar que
   conservan sus grupos. Los valores reales dependen de la base utilizada.
6. En un caso resuelto por admin y asignado a agente, comprobar atribuciones
   separadas: carga en agente actual, resolución en admin. Cerrado mantiene
   esa resolución histórica. Sin comentario previo: no entra en n de respuesta.
7. Un periodo sin tickets muestra conteos cero, tablas vacías y Sin datos con
   n=0. Desde posterior a hasta: mensaje de validación y ausencia de cifras;
   entrada inválida por URL como /reportes?desde=no-es-fecha también devuelve 422.
8. Navegar con teclado por formulario y enlaces. Recargar conserva filtros.
   Los grupos con más de 20 nombres ofrecen páginas sin cambiar las métricas;
   el dataset aislado automatizado cubre ese caso sin llenar la demo.

El dataset aislado tiene cuatro tickets, uno por estado, primera respuesta
2.00 h (n=2) y resolución 6.00 h (n=2); incluye resolutor administrador y categoría
inactiva. Esa expectativa no se aplica a la demo si ya se crearon/modificaron
tickets. Las pruebas incorporan un resuelto sin respuesta, límites UTC exactos,
filtros combinados, conjuntos autorizados, vacío y 23 categorías para paginar.

## Evidencia real, defectos y pendientes

- Usuario: commit 7dc07fd56a9d09b11b1228dd0896442d3104b508 confirmado limpio
  y sincronizado para incremento 4; resultados de sus pruebas no recibidos.
- Asistente: Linux auxiliar PHP 8.3.6, PHPUnit 12.5.39. Ejecutadas 165 unitarias
  con 467 aserciones OK y lint 81 PHP OK. Configuración Compose validada.
- Renderizados tres paneles y tres reportes (dataset/vacío/inválido) con fixtures,
  comprobando escape de nombres, menú, ausencia de promedios globales en paneles,
  n/etiquetas/paginación y ausencia de cifras con HTTP 422.
- Controlador deniega a usuario/agente con 403 usando fixtures de identidad;
  no constituye una prueba de sesión y MySQL reales. HTTP anónimo / y /reportes
  redirige 303 a login, incluso con filtros inválidos.
- Parche comprobado/aplicado sobre copia exacta de incrementos 1–4 y comparación
  de archivos contra el módulo, conservando sentinelas .env/vendor.
- Sin motor Docker/MySQL aquí: 28 casos de integración preparados, no ejecutados.
  PHP 8.3.6 es auxiliar; PHP 8.4.x continúa siendo la versión objetivo del proyecto.
- Sin defectos detectados en las comprobaciones ejecutadas. Integración,
  navegación local, aceptación independiente y evidencia Windows pendientes.
  Cobertura y desempeño no medidos. No se afirma instalación/restauración exitosa.

Antes de entregar: incremento 6 (seguridad/integridad y aceptación completa,
cobertura de servicios críticos con meta ≥80 %, p95 con 500 tickets y 20 mediciones
por flujo, instalación/respaldo/restauración, documentación técnica, correcciones,
paquete y ensayo de demostración). Documento académico se trabaja en otro chat.

## Revisar y crear el commit (lo hace el usuario)

Después de aprobar pruebas y verificación manual, desde terminal de VS Code:

```powershell
git add .
git --no-pager diff --cached --stat
git --no-pager diff --cached --check
git commit -m "feat(reportes): implementar RF12 RF13 RF14"
git push -u origin feature/reportes
git status
git rev-parse HEAD
```

Comprobar que diff --check no informa errores y que no hay .env/vendor/informes
privados entre archivos preparados. Si falla un comando, detenerse y compartir
salida. El commit de entrega será el SHA que informe el usuario, no uno supuesto.

## Resumen compartible

> Incremento 5: paneles/reportes RF12–RF14 implementados contra commit
> 7dc07fd56a9d09b11b1228dd0896442d3104b508. Paneles por conjunto autorizado;
> filtros comunes por creación local/estado/prioridad/categoría/agente actual;
> agrupaciones por agente actual y resolutor separadas, incluidas categorías
> históricas; promedios desde BD en horas con n y Sin datos sin muestra.
> Auxiliar PHP 8.3.6: 165 unitarias/467 aserciones y 81 PHP válidos; render/HTTP
> comprobados con fixtures. Integración MySQL: 28 casos preparados, no ejecutados
> aquí. Commit del módulo, resultados Windows, revisión independiente y aceptación
> pendientes. Próximo: validación y entrega; no hay versión final aceptada.
