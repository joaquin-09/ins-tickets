# Incremento 03: tickets

Línea base 1.3, §14. Base Git confirmada:
`2463f7bbe8837051cf4c96f6ad8a8e724733bf95`. Commit del incremento 3 pendiente.

## Implementación y trazabilidad

- RF03: creación de solicitud propia por cualquier rol activo, categoría
  activa, prioridad cerrada, fechas UTC, estado abierto y agente nulo. Código
  TCK- con seis dígitos mínimos. Validación de textos en servidor/HTML.
- RF04: mis solicitudes, asignados al agente y gestión global administrativa;
  detalle con autores, fechas locales, resolución, comentarios e historial
  existentes, sin revelar un ticket ajeno por ID (404).
- RF05: título/descripcion solo en abierto, conforme a actor; clasificación
  abierta/en progreso según matriz. Resuelto/cerrado inmutables. Categoría
  inactiva conservable cuando ya era la del ticket; nunca seleccionable nueva.
- RF15: búsqueda por ID/código/título/descripción, filtros combinados por
  estado/prioridad/categoría; agente/solicitante adicionales para admin.
  Autorización forma parte del WHERE de datos/conteo, antes de los filtros.
  Orden creación descendente/ID descendente, 20 por página, filtros preservados.
- Soporte de RF08: creación/edición dejan eventos atómicos consultables;
  transiciones de estado e historial de atención aún pendientes de incremento 4.
- Comentarios/historial paginados en bloques cronológicos de 20 con navegación
  independiente; consulta únicamente en este incremento.

Token de envío aleatorio ligado a sesión/actor, distinto de CSRF. Cada token
representa una creación; reenvío recupera el mismo registro, sin nuevo evento.
La restricción UNIQUE complementa el control. Hasta 20 formularios conservados
en sesión; una clave retirada se rechaza con 409, sin generar duplicado.
PRG tras éxito. Fallos de validación conservan entradas no sensibles.

La transacción bloquea usuarios en el orden común de administración, luego
ticket y categoría. Revalida activo/permiso/estado/versión. No se acepta
sobrescribir un formulario viejo; 409 conserva los valores enviados para
compararlos tras recarga. Crear/editar y evento hacen COMMIT juntos o ROLLBACK.
Guardar valores idénticos no agrega un evento ni altera versión. No hay
borrado de tickets, asignación ni transiciones web en este incremento.

## Evidencia y límites

El usuario informó que las pruebas del incremento 2 pasaron, sin salidas
detalladas. Base limpia/sincronizada y commit confirmados. No se infiere de
esa frase que todos los casos manuales hayan sido ejecutados.

Entorno auxiliar del asistente: PHP 8.3.6, PHPUnit 12.5.39, Linux, sin motor
Docker ni MySQL. Ejecutado: 96 unitarias/199 aserciones OK; lint de 63 PHP OK;
Compose con pruebas válido; HTTP de rutas anónimas/CSRF y recursos locales;
check/aplicación del parche sobre base exacta y comparación de contenido OK.
No se ejecutó integración MySQL ni aceptación manual del incremento 3 aquí.

Se prepararon seis casos MySQL nuevos: creación/duplicado, validación sin
parciales, categoría histórica/edición, agente/solicitante, listas/filtros/
paginación, conflicto y rollback ante fallo del evento. Junto a los nueve
anteriores se esperan 15 casos en la configuración aislada. Fixtures de
atención preparados por SQL de prueba no acreditan flujo implementado.
El fallo de auditoría usa un trigger temporal únicamente en db-test y lo
retira en finally; no ejecutar ese SQL en el servidor de demostración.

Sin cobertura medida ni porcentaje de avance. Sin defectos confirmados en
lo comprobado, con riesgo de SQL/UI pendiente de ejecución local. Revisión
por otro integrante y aceptación final siguen sin confirmar.

## Comprobaciones locales (esperados, no resultados)

1. Usuario1 crea un ticket válido. Consultar detalle/recargar: abierto, propio,
   sin asignar, fecha local y un evento de creación persistentes.
2. Probar título corto/espacios y descripción corta: validación, sin registros
   parciales. Mantener texto al corregir. No incluir contraseñas en tickets.
3. Reenviar el mismo POST desde herramientas del navegador o doble clic:
   mismo ID, un solo ticket y evento. Actualizar página tras PRG no duplica.
4. Usuario2 intenta URL del ticket de usuario1: 404. Buscarlo por código como
   usuario2: no aparece. Admin lo consulta globalmente.
5. Usuario1 edita ticket abierto: datos/versión/evento persistentes. Ticket
   resuelto/cerrado de demo no permite editar. Un agente asignado en progreso
   ajusta categoría/prioridad y no título/descripcion; solicitante no lo edita.
6. Admin desactiva la categoría actual del ticket abierto: conservarla al
   editar otro campo funciona; ticket nuevo no la ofrece. Reactivar después.
7. Búsqueda por TCK-ID, título/descripcion y filtros combinados, incluidos
   agente/solicitante admin. Entrada XSS se muestra escapada, SQL como texto
   no expande acceso. Vista vacía explica ausencia de resultados.
8. Abrir edición en dos pestañas. Guardar una; intentar la anterior: 409 sin
   sobrescritura, con valores enviados visibles para comparación y recarga.
9. Paginación de 20/filtros/order comprobada con datos sintéticos en integración;
   no crear ni borrar 21 tickets manualmente solo para demostrarla.
10. Ejecutar lint, unitarias e integración aislada, guardar salidas/reportes.

## Resumen compartible

> Incremento 3 implementado contra 2463f7bbe8837051cf4c96f6ad8a8e724733bf95:
> RF03/RF04/RF05/RF15, autorización por ticket, búsqueda/filtros/paginación,
> doble envío, fechas locales y creación/edición con evento/versionado.
> 96 unitarias/199 aserciones y 63 PHP válidos en PHP 8.3.6 auxiliar.
> Integración MySQL (15 casos acumulados) y aceptación Windows pendientes.
> No hay cambios de esquema/semilla. Atención RF06–RF09 y reportes RF12–RF14
> pendientes. Commit local/revisor del incremento 3 todavía pendientes.
