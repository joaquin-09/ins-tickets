# Trazabilidad de desarrollo: línea base 1.3

| Incremento | Alcance | Estado de entrega |
|---|---|---|
| 01-base | MVC, Composer, Compose, configuración, esquema y semilla. Soporte técnico para todos los RF. | Comprobaciones de consola confirmadas por el usuario en a6905809b1de967c33c1edc00ec5c625113d0a14: PHP 8.4.26, MySQL 8.4.11, ambos servicios healthy, seis tablas y semilla OK, sintaxis de 17 PHP OK. No implica aceptación RF. |
| 02-acceso-admin | RF01, RF02, RF10, RF11; contraseña propia y controles de acceso. | Commit 2463f7bbe8837051cf4c96f6ad8a8e724733bf95. El usuario informa pruebas aprobadas, sin salidas detalladas. Aceptación manual y revisión por otro integrante no confirmadas. |
| 03-tickets | RF03, RF04, RF05, RF15; autorización, paginación y doble envío. | Commit beba1299b5f49efec3f36a18746d34d20eee079a. Usuario informa «Todo bien», sin resultados detallados; revisión/aceptación individual no confirmadas. |
| 04-atencion | RF06, RF07, RF08, RF09; eventos, transacciones y concurrencia. | Commit 7dc07fd56a9d09b11b1228dd0896442d3104b508 confirmado limpio y subido. 147 unitarias/436 aserciones y 73 PHP válidos en auxiliar 8.3.6. Sin resultados locales recibidos para este módulo; integración y aceptación pendientes. |
| 05-reportes | RF12, RF13, RF14; paneles autorizados y métricas desde BD. | Implementado contra 7dc07fd56a9d09b11b1228dd0896442d3104b508. Auxiliar PHP 8.3.6: 165 unitarias/467 aserciones y 81 PHP válidos. 28 casos MySQL acumulados preparados, no ejecutados aquí. Commit, pruebas locales y aceptación pendientes. |
| 06-validacion | Pruebas, instalación, backup/restauración, correcciones y demo. | Pendiente. |

Cada entrega debe registrar su commit local, verificaciones y revisión de un
integrante distinto del autor. El commit y la aceptación no se inventan.
Se conservarán resultados por entorno y versión, sin porcentaje ficticio.
