<?php

declare(strict_types=1);

namespace InsTickets\Core;

final class Input
{
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value)) {
            throw new ValidationException([$key => 'El valor recibido no es válido.']);
        }
        return $value;
    }

    public static function id(mixed $value): int
    {
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new HttpException(404, 'No se encontró el registro.');
        }
        return (int) $value;
    }

    public static function page(mixed $value): int
    {
        return is_scalar($value) && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false ? (int) $value : 1;
    }

    public static function old(array $data, array $keys): array
    {
        $old = [];
        foreach ($keys as $key) {
            $old[$key] = isset($data[$key]) && is_string($data[$key]) ? $data[$key] : '';
        }
        return $old;
    }
}
