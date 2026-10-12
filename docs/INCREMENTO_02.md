# Incremento 02: acceso y administración base

Base confirmada: `a6905809b1de967c33c1edc00ec5c625113d0a14`, incremento 1
aplicado, rama sincronizada y árbol limpio. Línea base de producto: 1.3, §14.
Commit del incremento 2: pendiente de creación por el usuario en VS Code.

## Requisitos implementados, aceptación pendiente

- RF01: alta exclusivamente administrativa, correo normalizado/único,
  contraseña inicial bcrypt costo 12, sin registro público.
- RF02: acceso/cierre, sesión de servidor, regeneración de ID, verificación de
  rol/activo desde BD en cada request, inactividad 30 minutos, cookies
  HttpOnly/SameSite=Lax y CSRF en login/logout y demás escrituras.
- RF10: listado paginado, alta, edición, activación/desactivación y borrado
  confirmado de categorías sin referencias. Referencias históricas preservadas.
- RF11: listado/alta/edición de usuarios, rol/activo, restablecimiento de
  contraseña ajena y eliminación confirmada sin referencias. Bloqueos de
  autodesactivación, autoeliminación, autoquitar rol, pérdida del último admin
  y cambios/borrado de agentes con tickets abiertos/en progreso.
- Contraseña propia: exige actual y confirmación, conserva espacios, valida
  10–64 caracteres UTF-8 y hasta 72 bytes. La contraseña actual no se muestra.
  Un admin usa este flujo también para cambiar la suya, verificando la actual.
- Login: cinco fallos/15 minutos por HMAC de correo/IP; éxito no borra fallos;
  depuración de registros mayores de 24 horas; bloqueo serializado por clave.
- Formularios: validación servidor/nativa HTML, escape de salida, errores
  403/404/422/500, conservación de entradas no sensibles, PRG al guardar.

Los servicios de administración vuelven a comprobar actor activo/rol dentro
de la transacción. Cambios de usuarios bloquean filas en orden de ID para
serializar reglas de administrador; tickets pendientes también se bloquean.
Las referencias y restricciones FK complementan la comprobación de servicio.
No se altera ninguna tabla ni privilegio de la instalación existente.

## Entornos y comprobaciones reales

### Local del usuario: incremento 1

Recibidas salidas de PHP 8.4.26, MySQL 8.4.11, app/db healthy, cuenta ins_app,
seis tablas, semilla válida (5 usuarios/5 categorías/4 tickets/2 comentarios/
13 eventos/0 intentos), tiempos sintéticos 2.00 h n=2 y 6.00 h n=2,
bcrypt costo 12 y 17 PHP sin errores. No confundir con aceptación del módulo 2.

### Auxiliar del asistente: incremento 2

Linux, PHP 8.3.6, PHPUnit 12.5.39, sin motor Docker/MySQL. Ejecutadas:

- 43 pruebas unitarias, 63 aserciones: OK. Límites de contraseñas UTF-8/bytes,
  normalización y preservación, validaciones, restricciones administrativas,
  CSRF, inactividad, umbral y clave de intentos, escape HTML/atributos.
- Lint: 49 PHP OK.
- HTTP sobre servidor incorporado auxiliar: login 200 con CSRF/cookies,
  raíz y tres funciones privadas redirigen al login sin sesión; POST login
  sin CSRF devuelve 403 antes de consultar BD; /.env, /database/schema.sql
  y GET /logout devuelven 404.
- Compose de referencia más configuración de pruebas: validación sintáctica OK.
- Parche: check/aplicación sobre copia exacta del incremento 1, sin commits;
  contenido final comparado byte a byte: OK.

Limitaciones: PHP 8.3.6 auxiliar no sustituye la repetición en PHP 8.4.26.
No se ejecutaron login real con BD, CRUD persistente, integración MySQL ni
aceptación manual del módulo 2. Cobertura no medida; meta del proyecto no se
declara alcanzada. Scripts PowerShell no ejecutados por el asistente.
Nueve pruebas de integración preparadas, todavía pendientes de ejecución.

Durante la comprobación HTTP auxiliar se ajustó su directorio privado de
sesiones; el código trata una falla de inicio de sesión como error controlado.
No hay defectos confirmados abiertos en lo probado; los flujos pendientes de
integración siguen sin verificar. Revisión por otro integrante pendiente.

## Cómo aplicar y verificar

Ver README. Sobre la instalación existente, conservar .env, dependencias y
volúmenes; aplicar parche, composer install, lint y PHPUnit Unit. No repetir
la semilla ni usar check-demo tras empezar a modificar cuentas/categorías o
iniciar sesión, porque ese script exige cantidades de semilla intacta.

Pruebas de integración mediante compose.test.yaml: servidor db-test separado
en tmpfs, sin puertos públicos, credenciales ficticias y limpieza antes de cada
caso con mantenimiento de pruebas. El bootstrap impide usar el servidor db.
Nunca ejecutar esas limpiezas manualmente sobre la base de demo.

### Verificación manual mínima (resultados esperados, aún no observados)

| Caso | Procedimiento | Esperado |
|---|---|---|
| Tres roles | Login/logout con admin, agente1 y usuario1, contraseña demo. | Rol correcto; solo admin ve gestión de usuarios/categorías. |
| Denegación por URL | Como usuario1, abrir /usuarios y /categorias. | 403, sin datos administrativos. |
| Credenciales | Contraseña errónea y cuenta ficticia inexistente. | Mensaje genérico, no sesión autenticada. |
| Alta y unicidad | Admin crea una cuenta QA; repetir correo en mayúsculas. | Una cuenta; repetición 422 sin duplicado. |
| Roles/activo | Editar esa cuenta QA, iniciar sesión y desactivarla desde admin en otro navegador. | El siguiente request autenticado deja de autorizarla. |
| Contraseña propia | En QA, probar actual errónea; luego actual válida y nueva confirmada. | Rechazo sin cambio; éxito, nueva clave sirve y anterior falla. |
| Restablecimiento ajeno | Admin establece nueva contraseña a QA. | Nueva válida, ninguna contraseña expuesta en formulario de consulta. |
| Admin esencial | Abrir edición propia; manipulación manual equivalente cubierta en integración. | No se permite perder acceso/rol propio ni el último administrador activo. |
| Agente pendiente | Intentar desactivar agente2 demo o quitarle rol. | Rechazo: primero reasignar ticket en progreso (asignación llega en incremento 4). |
| Referencias usuario | Intentar borrar usuario1 demo. | Rechazo; ticket/historial permanecen. |
| Categorías | Crear QA categoría, editar y eliminar; duplicar Hardware; intentar borrar Hardware demo. | CRUD sin referencias funciona; duplicado/referencia rechazados. |
| Categoría inactiva | Desactivar/activar Hardware demo. | Tickets permanecen; condición guardada. Restricción de selección se verifica en incremento 3. |
| CSRF | POST sin token; automatizado auxiliar para login y política. | 403, ninguna escritura. Validar endpoints autenticados en aceptación. |
| XSS | Nombre/descripción de categoría QA con texto HTML. | Texto visible escapado; no ejecución. |
| Sesión inactiva | Permanecer 30 minutos sin solicitudes; volver a función privada. | Requiere login. Umbral automatizado, espera manual pendiente. |
| Límite login | Cinco fallos con bloqueoqatest@nexoti.test; mantener separado de las cuentas demo. | Bloqueo genérico de esa clave; expira al pasar la ventana. Integración prueba ventana y conservación de fallos. |

Los registros QA sin referencias pueden eliminarse al terminar. No borrar
tickets ni reinicializar la demo. Agregar observado/evidencia a cada caso.

## Resumen para análisis y documentación

> Incremento 2 preparado contra a6905809b1de967c33c1edc00ec5c625113d0a14.
> RF01/RF02/RF10/RF11 y contraseña propia implementados, sin aceptación local
> todavía. 43 unitarias/63 aserciones y lint/HTTP auxiliar aprobados en PHP
> 8.3.6; integración MySQL (9 casos) y aceptación Windows pendientes. Base 1
> verificada por el usuario con PHP 8.4.26/MySQL 8.4.11. Se conservan esquema,
> datos y secretos. Commit del incremento 2 y revisor pendientes. Próximo:
> tickets RF03/RF04/RF05/RF15, sin modificar decisiones del maestro 1.3.

## Confirmación recibida del usuario

Commit 2463f7bbe8837051cf4c96f6ad8a8e724733bf95, feature/acceso-admin
sincronizada y árbol limpio. El 12/10/2026 el usuario confirmó que las pruebas
pasaron. Se registra como aprobación reportada por el usuario, sin salidas
detalladas ni evidencia de cada caso manual. No inventar número observado de
aserciones, capturas, revisión de otro integrante ni aceptación final.
