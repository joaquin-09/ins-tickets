# Ajuste de interfaz 01: cerrar sesión visible

Base confirmada: 431faea983d1b5f5940b8874cfd5bb577263c20f,
feature/validacion-entrega limpia y sincronizada según la última salida recibida.
Maestro 1.3, RF01/RF02 y §14.10 (navegación/usabilidad).

Cerrar sesión se mueve de la parte inferior de la barra lateral a la esquina
superior derecha. La cabecera usa position: sticky y permanece visible durante
el desplazamiento del contenido largo. Solo existe un formulario de logout por
sesión autenticada. Conserva POST y token CSRF; invitado no recibe el botón.
Los destinos por ID tienen margen de desplazamiento para evitar quedar ocultos
bajo la cabecera. No hay nuevas dependencias, configuración o migraciones.

## Comprobación local

Después de git apply --check y git apply desde raíz:

```powershell
docker compose restart app
docker compose exec -T app php -l app/Views/layouts/base.php
```

En localhost:8080, actualizar con Ctrl+F5. Con los tres roles, abrir un detalle
largo y bajar al final: el botón debe permanecer arriba a la derecha. Verificar
también ancho móvil de 320/390 px: texto completo y sin desplazamiento horizontal.
Con teclado: Tab llega al botón con foco visible y Enter cierra sesión. El botón
redirige a login; una URL privada después de cerrar requiere autenticación.
El login no muestra Cerrar sesión. No repetir semilla.

## Evidencia y pendientes

Asistente: sintaxis de la plantilla PHP válida en auxiliar 8.3.6; render con
fixtures de usuario/agente/admin/invitado, un formulario POST/CSRF para cada
identidad autenticada y ninguno para invitado. Parche comprobado sobre copia
exacta de incrementos 1–6 y comparación de archivos, conservando .env/vendor.
No se ejecutó prueba visual de scroll en navegador ni logout real aquí: el
navegador auxiliar no estuvo disponible. Verificación local y commit pendientes.

Las pruebas anteriores están vinculadas a 431faea...: 179 unitarias/483 aserciones,
209 casos combinados/718 aserciones, sin fallos/errores/omisiones; cobertura de
líneas app/Services 96.04%, críticos 94.48%; tres p95 <3s y 34 checks HTTP aprobados,
instalación y restauración automática comprobadas en Windows por el usuario.
No se atribuyen esos resultados al nuevo commit de interfaz. Al preparar paquete
final, los scripts exigen evidencia del commit limpio que se va a entregar.

## Commit desde VS Code (usuario)

```powershell
git add app/Views/layouts/base.php public/assets/css/app.css docs/AJUSTE_01_LOGOUT.md
git --no-pager diff --cached --stat
git --no-pager diff --cached --check
git commit -m "fix(ui): mantener cerrar sesion visible en la cabecera"
git push
git status
git rev-parse HEAD
```

## Resumen compartible

Ajuste de usabilidad preparado contra 431faea983d1b5f5940b8874cfd5bb577263c20f:
logout en cabecera sticky, conserva POST/CSRF y visibilidad por sesión. Sintaxis
PHP/render de identidades comprobados en auxiliar; aplicación del parche verificada.
Scroll escritorio/móvil, logout local y commit pendientes. No altera el maestro
ni agrega funciones. Evidencias finales deberán identificar la nueva versión.
