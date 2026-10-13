<?php

declare(strict_types=1);

namespace InsTickets\Services;

use DateTimeImmutable;
use DateTimeZone;
use InsTickets\Core\Input;
use InsTickets\Core\ValidationException;

final class ReportFilter
{
    public const KEYS = ['desde', 'hasta', 'estado', 'prioridad', 'categoria_id', 'agente_id'];

    public static function validate(array $input): array
    {
        $result = [];
        $errors = [];
        foreach (self::KEYS as $key) {
            $result[$key] = trim(Input::string($input, $key));
        }
        foreach (['desde', 'hasta'] as $key) {
            if ($result[$key] !== '' && self::localDate($result[$key]) === null) {
                $errors[$key] = 'Ingresá una fecha válida entre 1000-01-01 y 9998-12-31.';
            }
        }
        if (!isset($errors['desde']) && !isset($errors['hasta'])
            && $result['desde'] !== '' && $result['hasta'] !== '' && $result['desde'] > $result['hasta']) {
            $errors['desde'] = 'Desde no puede ser posterior a hasta.';
        }
        foreach (['estado' => TicketValidation::STATES, 'prioridad' => TicketValidation::PRIORITIES] as $key => $allowed) {
            if ($result[$key] !== '' && !isset($allowed[$result[$key]])) {
                $errors[$key] = 'Filtro no válido.';
            }
        }
        foreach (['categoria_id', 'agente_id'] as $key) {
            $value = $result[$key];
            if ($value !== '' && !($key === 'agente_id' && $value === 'sin_asignar')
                && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $errors[$key] = 'Filtro no válido.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $result;
    }

    public static function utcBounds(array $filters): array
    {
        // Valida también invocaciones que no provengan del controlador.
        $filters = self::validate($filters);
        $utc = new DateTimeZone('UTC');
        $from = $filters['desde'] === '' ? null : self::localDate($filters['desde'])->setTimezone($utc)->format('Y-m-d H:i:s');
        $until = $filters['hasta'] === '' ? null : self::localDate($filters['hasta'])->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
        return [$from, $until];
    }

    private static function localDate(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) || $value < '1000-01-01' || $value > '9998-12-31') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('America/El_Salvador'));
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
