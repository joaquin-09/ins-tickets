<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Core\Input;
use InsTickets\Core\ValidationException;

final class Validation
{
    public static function password(string $password): ?string
    {
        if (!mb_check_encoding($password, 'UTF-8') || mb_strlen($password, 'UTF-8') < 10 || mb_strlen($password, 'UTF-8') > 64 || strlen($password) > 72 || str_contains($password, "\0")) {
            return 'Usá entre 10 y 64 caracteres UTF-8, con máximo 72 bytes y sin caracteres nulos. Los espacios se conservan.';
        }
        return null;
    }

    public static function user(array $input, bool $creating): array
    {
        $name = trim(Input::string($input, 'nombre'));
        $email = mb_strtolower(trim(Input::string($input, 'email')), 'UTF-8');
        $role = Input::string($input, 'rol');
        $active = Input::string($input, 'activo', '0');
        $password = Input::string($input, 'password');
        $errors = [];
        if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['nombre'] = 'El nombre debe tener entre 2 y 100 caracteres.';
        }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Ingresá un correo válido de hasta 254 caracteres.';
        }
        if (!in_array($role, ['usuario', 'agente', 'administrador'], true)) {
            $errors['rol'] = 'Seleccioná un rol válido.';
        }
        if (!in_array($active, ['0', '1'], true)) {
            $errors['activo'] = 'Seleccioná una condición válida.';
        }
        if (($creating || $password !== '') && ($error = self::password($password)) !== null) {
            $errors['password'] = $error;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return ['nombre' => $name, 'email' => $email, 'rol' => $role, 'activo' => (int) $active, 'password' => $password];
    }

    public static function category(array $input): array
    {
        $name = trim(Input::string($input, 'nombre'));
        $description = trim(Input::string($input, 'descripcion'));
        $active = Input::string($input, 'activa', '0');
        $errors = [];
        if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['nombre'] = 'El nombre debe tener entre 2 y 80 caracteres.';
        }
        if (!mb_check_encoding($description, 'UTF-8') || mb_strlen($description) > 255) {
            $errors['descripcion'] = 'La descripción admite hasta 255 caracteres.';
        }
        if (!in_array($active, ['0', '1'], true)) {
            $errors['activa'] = 'Seleccioná una condición válida.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return ['nombre' => $name, 'descripcion' => $description === '' ? null : $description, 'activa' => (int) $active];
    }
}
