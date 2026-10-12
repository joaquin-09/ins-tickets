# Trazabilidad de desarrollo: línea base 1.3

| Incremento | Alcance | Estado de entrega |
|---|---|---|
| 01-base | MVC, Composer, Compose, configuración, esquema y semilla. Soporte técnico para todos los RF. | Comprobaciones de consola confirmadas por el usuario en a6905809b1de967c33c1edc00ec5c625113d0a14: PHP 8.4.26, MySQL 8.4.11, ambos servicios healthy, seis tablas y semilla OK, sintaxis de 17 PHP OK. No implica aceptación RF. |
| 02-acceso-admin | RF01, RF02, RF10, RF11; contraseña propia y controles de acceso. | Commit 2463f7bbe8837051cf4c96f6ad8a8e724733bf95. El usuario informa pruebas aprobadas, sin salidas detalladas. Aceptación manual y revisión por otro integrante no confirmadas. |
| 03-tickets | RF03, RF04, RF05, RF15; autorización, paginación y doble envío. | Implementado con verificación auxiliar: 96 unitarias/199 aserciones, sintaxis. Integración y aceptación local pendientes. |
| 04-atencion | RF06, RF07, RF08, RF09; eventos, transacciones y concurrencia. | Pendiente. |
| 05-reportes | RF12, RF13, RF14; paneles autorizados y métricas reales. | Pendiente. |
| 06-validacion | Pruebas, instalación, backup/restauración, correcciones y demo. | Pendiente. |

Cada entrega debe registrar su commit local, verificaciones y revisión de un
integrante distinto del autor. El commit y la aceptación no se inventan.
Se conservarán resultados por entorno y versión, sin porcentaje ficticio.
