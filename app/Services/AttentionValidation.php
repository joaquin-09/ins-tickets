<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Core\ValidationException;

final class AttentionValidation
{
    public static function text(string $value, string $field, int $min, int $max): string
    {
        $value = trim($value);
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) < $min || mb_strlen($value) > $max) {
            throw new ValidationException([$field => "Ingresá entre $min y $max caracteres; no se admiten solo espacios."]);
        }
        return $value;
    }

    public static function agent(mixed $input): ?int
    {
        if ($input === '') {
            return null;
        }
        if (!is_string($input) || filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new ValidationException(['agente_id' => 'Seleccioná un agente válido.']);
        }
        return (int) $input;
    }
}
