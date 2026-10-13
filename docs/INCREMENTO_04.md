# Incremento 04: atención

Base confirmada `beba1299b5f49efec3f36a18746d34d20eee079a`, rama
feature/tickets sincronizada y limpia. Diseño definitivo: maestro 1.3, §14.
Commit de aplicación confirmado por el usuario:
`7dc07fd56a9d09b11b1228dd0896442d3104b508`, feature/atencion limpia y
sincronizada. No se recibieron salidas de pruebas locales del incremento 4.

## Requisitos implementados

- RF06: asignación/reasignación exclusivamente administrativa en abierto/en
  progreso, candidato agente activo distinto del solicitante. Solo abierto
  permite retirar agente. Se conserva estado, fechas y primera respuesta.
  Cambiar al mismo agente se rechaza sin evento ni incremento de versión.
- RF07: abierto → en progreso → resuelto → cerrado. Iniciar/resolver requiere
  agente activo asignado y actor autorizado distinto del solicitante. Un
  administrador no atiende su propia solicitud. Cierre solo por solicitante
  o administrador cuando esté resuelto. Sin saltos/reversión/reapertura ni
  transiciones repetidas. Resolución obliga resumen 10–2,000 caracteres.
- RF08: eventos inmutables de asignación/reasignación/estado, con actor y
  anterior/nuevo, fecha UTC del servidor igual a la operación. Se completan
  los eventos de creación/edición del incremento 3. Historial paginado.
- RF09: comentario 1–2,000 caracteres, visible dentro del conjunto autorizado,
  autor y rol actual capturados desde BD, fecha de servidor. Sin edición,
  borrado ni notas privadas. Resuelto admite comentar; cerrado no.

Primera respuesta: primer comentario de soporte válido, agente asignado o
administrador distinto del solicitante, mientras esté abierto/en progreso.
Asignación, inicio y comentario propio no cuentan. Se fija una sola vez;
reasignación no la reinicia. Resolver sin comentario conserva ausencia de
primera respuesta. Resumen no se duplica como comentario. Cerrar conserva
resolved_by/resolved_at; registra closed_by/closed_at por separado.

## Atomicidad y concurrencia

Orden común de bloqueo: usuarios en ID ascendente, ticket, referencias cuando
corresponde. Se comprueban actor activo, visibilidad, versión, rol, candidato
y estado dentro de la transacción. Cada UPDATE confirma una fila afectada.
Toda acción, incluido comentar, incrementa versión del agregado; un reenvío
con versión anterior devuelve 409 y no duplica comentarios/eventos.

Un conflicto conserva entradas no sensibles y exige volver al detalle actual.
Validación devuelve 422 con entradas conservadas y versión enviada; no
actualiza automáticamente la versión para sobrescribir cambios concurrentes.
Autorización devuelve 403; ticket invisible/inexistente 404; fallo interno
500 controlado. CSRF global en los tres nuevos endpoints POST; GET no muta.

Crear/editar/asignar/transicionar y sus eventos son atómicos. Comentario,
primera respuesta y versión son atómicos, sin duplicación en bitácora.
No cambian esquema, privilegios, claves o semilla de la instalación existente.

## Evidencia real y límites

Usuario: reportó funcionamiento correcto del incremento 3 y confirmó commit
limpio/sincronizado. No se recibieron resultados detallados ni se inventan.

Asistente: Linux auxiliar, PHP 8.3.6 y PHPUnit 12.5.39, sin motor Docker/
MySQL. Ejecutadas 147 unitarias/436 aserciones OK, lint de 73 PHP OK,
validación de Compose, render de detalle con 12 fixtures (tres roles × cuatro
estados) y comprobación de que cerrado no muestra formularios de atención,
asignación solo para admin y resolución no para usuario. No equivale a una
prueba con sesión/BD reales ni a aceptación de navegador en Windows.

HTTP auxiliar: GET de las tres acciones devuelve 404; POST sin CSRF 403.
Parche: check/aplicación sobre copia exacta del incremento 3 y coincidencia
de contenido, conservando .env/vendor. No se ejecutaron aquí transacciones
MySQL, concurrencia real, scripts PowerShell ni aceptación manual.

Ocho casos nuevos de integración preparados: flujo completo, reasignación,
candidatos/permisos, solicitud propia de admin, transiciones/validación,
resolución sin respuesta/atribución, rollback y escritor concurrente.
Se esperan 23 casos acumulados en db-test. Fallos provocados mediante triggers
temporales, retirados en finally. La prueba concurrente inicia otro proceso,
observa performance_schema.data_lock_waits, libera la transacción y espera
409 sin escritura de la versión vieja. Esa tabla documenta esperas entre
transacciones: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-data-lock-waits-table.html.
Se usa mantenimiento únicamente en el servidor aislado; no en la demo.

Cobertura no medida ni meta declarada cumplida. Sin defectos confirmados
en lo verificado, con integración/aceptación pendientes. Revisor real pendiente.

## Demostración local breve (esperados, aún no observados)

1. Usuario1 crea una nueva solicitud propia y comenta. Primera respuesta
   todavía Sin datos. Anotar su código; no reutilizar un ticket cerrado.
2. Admin asigna a agente1. Se conserva abierto y primera respuesta vacía.
3. Agente1 inicia atención y comenta. Cambia a en progreso y queda primera
   respuesta válida. Usuario1 consulta avance al recargar.
4. Admin reasigna a agente2. Agente1 pierde acceso salvo solicitud propia;
   su comentario sigue visible y la primera respuesta no cambia.
5. Agente2 resuelve con resumen. Quedan fecha y resolutor; no aparece un
   comentario duplicado con el resumen. Usuario1 puede comentar en resuelto.
6. Usuario1 cierra. Quedan cerrador/fecha y consulta únicamente. Recargar
   confirma persistencia. Reportes se incorporarán en incremento 5.
7. Probar negativos: agente intenta asignar; asignación a solicitante, usuario
   o inactivo; quitar agente en progreso; resolver abierto; resumen corto;
   comentar vacío/cerrado; cerrar sin resolver; reapertura; resolución propia
   de admin. No deben dejar cambios parciales ni eventos duplicados.
8. Abrir la misma acción en dos pestañas. Ejecutar una y luego la vieja:
   409 con valores conservados y sin sobrescritura/duplicado.
9. Un ticket aparte resuelto sin comentario previo permite tiempo de
   resolución, conserva primera respuesta Sin datos incluso si comentan
   después de resolver. No inventar cero horas.

No eliminar tickets de la demo. La limpieza/triggers/prueba concurrente se
ejecutan solo con Compose de pruebas y guardas APP_ENV=test/DB_HOST=db-test.

## Resumen compartible

> Incremento 4 preparado contra beba1299b5f49efec3f36a18746d34d20eee079a.
> RF06–RF09 implementados: asignación/reasignación, transiciones autorizadas,
> comentarios, historial, primera respuesta y autoría de resolución/cierre.
> Transacciones con bloqueo y versión; datos existentes conservados. Auxiliar
> PHP 8.3.6: 147 unitarias/436 aserciones, 73 PHP y render/HTTP comprobados.
> Integración MySQL (23 casos preparados) y aceptación local pendientes.
> Commit confirmado 7dc07fd56a9d09b11b1228dd0896442d3104b508; revisor pendiente. Próximo: paneles/reportes RF12–RF14; después
> validación, cobertura, desempeño, respaldo/restauración y entrega.
