# Guía rápida para superadmin

## Crear un superadmin nuevo

Si eres el técnico de la página y necesitas crear otro superadmin, se puede hacer desde MySQL con este comando:

```sql
INSERT INTO admins (username, password_hash, is_super)
VALUES ('nuevo_super', '$2y$10$Q8uY5x2nV2h8xP6O2JjQ2e8s2h2u7hTn8q8Gz1jZy1YQ7M0D9ZK6', 1);
```

Luego inicia sesión con:
- usuario: nuevo_super
- contraseña: 123456

## Convertir un administrador normal en superadmin

Si ya existe un administrador y quieres convertirlo en superadmin:

```sql
UPDATE admins SET is_super = 1 WHERE username = 'nombre_usuario';
```

## Eliminar un superadmin siendo técnico

Si eres el técnico y necesitas eliminar un superadmin distinto al actual, ejecuta:

```sql
DELETE FROM admins WHERE username = 'nuevo_super';
```

## Importante

- El sistema ya está configurado para que un superadmin no pueda borrar a otro superadmin.
- Además, no es posible eliminar su propia cuenta desde el panel.
- Para cambiar la contraseña de un usuario, se usa:

```sql
UPDATE admins SET password_hash = '$2y$10$Q8uY5x2nV2h8xP6O2JjQ2e8s2h2u7hTn8q8Gz1jZy1YQ7M0D9ZK6' WHERE username = 'nuevo_super';
```
