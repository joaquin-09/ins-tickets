<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Core\Input;
use InsTickets\Core\ValidationException;

final class TicketValidation
{
    public const STATES = ['abierto' => 'Abierto', 'en_progreso' => 'En progreso', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'];
    public const PRIORITIES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta'];

    public static function data(array $input, bool $text): array
    {
        $errors = [];
        $data = [];
        if ($text) {
            foreach (['titulo' => [5, 150], 'descripcion' => [10, 5000]] as $key => [$min, $max]) {
                $value = trim(Input::string($input, $key));
                if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) < $min || mb_strlen($value) > $max) {
                    $errors[$key] = "El campo $key requiere entre $min y $max caracteres y no puede estar vacío.";
                }
                $data[$key] = $value;
            }
        }
        $priority = Input::string($input, 'prioridad');
        $category = Input::string($input, 'categoria_id');
        if (!isset(self::PRIORITIES[$priority])) {
            $errors['prioridad'] = 'Seleccioná una prioridad válida.';
        }
        if (filter_var($category, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors['categoria_id'] = 'Seleccioná una categoría válida.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $data + ['prioridad' => $priority, 'categoria_id' => (int) $category];
    }
}
