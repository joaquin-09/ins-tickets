# Guion operativo de demostración

Entrega: miércoles 14/10/2026, exposición en clase. Duración por confirmar;
este guion no presupone minutos ni resultados exitosos aún no observados.

## Preparación

Código confirmado, pruebas/aceptación documentadas, documento académico final,
respaldo y restauración ensayada. Imágenes/Composer/Bootstrap descargados antes
de clase. Iniciar Docker Desktop y docker compose up -d; comprobar ps/check.php.
Abrir localhost:8080. Preparar sesiones de usuario1/admin/agente1 en navegadores
separados o perfiles distintos (no solo pestañas que compartan sesión). Conservar
credenciales demo local y código del nuevo ticket. No repetir semilla en la demo.

## Recorrido

1. Usuario: «Centralizamos una incidencia y podemos seguir su atención». Mostrar
   panel propio, abrir Crear ticket y probar título vacío; luego crear uno válido.
2. Admin: buscar el código, mostrar abierto/sin agente y asignar a agente1.
3. Agente: abrir Asignados a mí, iniciar atención y comentar. Mostrar primera
   respuesta e historial sin atribuir a la asignación ese tiempo.
4. Usuario: recargar para ver comentario/avance. Mostrar edición ya denegada.
5. Agente: resolver con resumen claro. Mostrar resolución/autor y estado resuelto.
6. Usuario: consultar resultado y cerrar. Recargar confirma persistencia; cerrado
   deja consulta únicamente y conserva historial/comentarios.
7. Admin: mostrar usuarios/categorías; desactivar categoría de prueba sin borrar
   históricos, si ese paso se ensayó. Mostrar reportes y aplicar un filtro;
   explicar creación local, agente actual frente a resolutor, horas naturales y n.
8. Usuario: abrir /reportes para evidenciar 403. No proyectar .env, dumps o logs.

Los promedios de este recorrido corto son de datos ficticios; no demostrar ahorros,
satisfacción o eficacia empresarial que no se midieron. Mostrar resultados de QA
solo del entorno y commit realmente ejecutados. No simular resultados visuales.

## Contingencia

Arranque lento: esperar servicios healthy antes de presentar. Fallo de red:
recursos del sitio ya son locales; la primera instalación sí requirió Internet.
Fallo de aplicación: conservar referencia, comprobar servicios sin resetear BD.
Disponer del código/SQL privados y copia de instalación/restauración ensayada;
no restaurar improvisadamente sobre la base principal. Ensayar este recorrido
antes de clase y registrar quién revisó y los defectos pendientes.
