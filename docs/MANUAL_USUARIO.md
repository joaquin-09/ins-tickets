# Manual por rol — INS Tickets, línea base 1.3

Abrir http://localhost:8080. Cuentas iniciales ficticias (si no se modificaron):
admin@nexoti.test, agente1@nexoti.test, agente2@nexoti.test,
usuario1@nexoti.test, usuario2@nexoti.test. Clave demo: DemoLocal2026!.
No reutilizar esa clave fuera de la demostración local.

## Acceso común

Ingresar correo/contraseña; los fallos muestran mensaje genérico. Cinco fallos
por correo/IP en quince minutos bloquean temporalmente esa clave. Una sesión
inactiva treinta minutos vence. Mi contraseña exige la actual y confirmación;
10–64 caracteres UTF-8 y hasta 72 bytes, conservando espacios. Cerrar sesión
usa el botón del menú; volver a una URL privada pide login.

Todo rol puede crear una solicitud propia, abierta y sin agente. Completar
categoría activa, prioridad, título 5–150 y descripción 10–5,000 caracteres.
No incluir secretos. Al guardar se abre su detalle. Reenviar el mismo formulario
no crea otro ticket. Un formulario de creación antiguo puede expirar: abrir uno
nuevo. El código TCK identifica la solicitud.

Listas: búsqueda por ID/código/título/descripción, filtros de estado/prioridad/
categoría y veinte tickets por página. El administrador agrega agente/solicitante.
Detalle: fechas locales de El Salvador, comentarios, resolución e historial.
Comentarios e historial tienen paginación independiente y son inmutables.

## Solicitante

Inicio resume sus propias solicitudes. Mis solicitudes permite consultarlas.
Puede editar su ticket abierto; después de iniciar atención, texto/categoría/
prioridad dejan de ser editables por el solicitante. Puede comentar mientras
no esté cerrado, incluido resuelto. Comentario: 1–2,000 caracteres. El comentario
propio no inicia primera respuesta. Solo solicitante o administrador cierra
cuando esté resuelto. Cerrado permite consulta únicamente, sin reapertura.

## Agente

Inicio resume solicitudes propias y tickets actualmente asignados. Mis solicitudes
y Asignados a mí separan ambas listas. Actúa como solicitante en sus tickets
propios; no puede atender su propio ticket. En uno asignado abierto/en progreso
puede ajustar categoría/prioridad, iniciar atención y resolver. Para resolver
se exige resumen 10–2,000 caracteres, que se conserva separado de comentarios.
El primer comentario de soporte válido inicia primera respuesta una sola vez.
No puede asignar ni cerrar tickets ajenos. Al reasignar pierde acceso al ticket
anterior, salvo que sea solicitante; sus comentarios históricos se conservan.

## Administrador

Acceso global a tickets, usuarios, categorías y reportes. Asigna/reasigna a un
agente activo distinto del solicitante, solo abierto/en progreso. Retirar agente
solo se permite en abierto. Iniciar/resolver requiere asignación válida y ser
distinto del solicitante. Puede cerrar un resuelto. La asignación no reinicia
fechas ni cambia el estado. No puede atender una solicitud propia.

Usuarios: crear, editar, activar/desactivar y eliminar solo sin referencias.
No puede eliminarse, desactivarse, quitarse su rol ni dejar cero administradores
activos. Agente con abiertos/en progreso debe reasignarse antes de perder rol/
actividad. Usuarios con historial no se eliminan. Contraseña propia se cambia
en Mi contraseña, verificando la actual.

Categorías: nombre único; crear/editar/desactivar; borrar solo sin tickets.
Una inactiva conserva históricos, pero no se asigna como nueva categoría.

Reportes: filtros comunes por fecha de creación (días completos locales), estado,
prioridad, categoría y agente actualmente asignado. Carga por agente actual y
resueltos por resolutor se muestran por separado. La resolución incluye cerrado
y administradores que resolvieron. Promedios de horas naturales y muestra n;
sin muestra: Sin datos. Paginación de grupos conserva filtros. No hay exportaciones.

## Mensajes y recuperación

403: función no autorizada. 404: ticket inexistente/no visible. 409: otra acción
cambió la versión; abrir detalle actual y revisar antes de volver a enviar.
422: corregir datos conservados por el formulario. 500: no asumir guardado;
conservar referencia del incidente y avisar al equipo. La aplicación no muestra
SQL, contraseñas o trazas. Comentarios no admiten HTML ejecutable.
