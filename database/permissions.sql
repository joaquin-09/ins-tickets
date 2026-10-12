-- Ejecutado por la cuenta de mantenimiento después del esquema, nunca por ins_app.
-- El entrypoint oficial ya crea ins_app; reemplazamos sus permisos globales de la BD.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'ins_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON ins_tickets.usuarios TO 'ins_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON ins_tickets.categorias TO 'ins_app'@'%';
GRANT SELECT, INSERT, UPDATE ON ins_tickets.tickets TO 'ins_app'@'%';
GRANT SELECT, INSERT ON ins_tickets.comentarios TO 'ins_app'@'%';
GRANT SELECT, INSERT ON ins_tickets.historial_tickets TO 'ins_app'@'%';
GRANT SELECT, INSERT, DELETE ON ins_tickets.intentos_acceso TO 'ins_app'@'%';
