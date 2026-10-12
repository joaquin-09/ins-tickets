<?php

declare(strict_types=1);

namespace InsTickets\Core;

use RuntimeException;

final class Config
{
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
                throw new RuntimeException('Formato de configuracion invalido.');
            }
            $value = trim($matches[2]);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                if (substr($value, -1) !== $value[0]) {
                    throw new RuntimeException('Comillas de configuracion invalidas.');
                }
                $value = substr($value, 1, -1);
            }
            self::$values[$matches[1]] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): string
    {
        $environment = getenv($key);
        $value = $environment !== false ? $environment : (self::$values[$key] ?? $default);
        if ($value === null || $value === '') {
            throw new RuntimeException('Falta una variable de configuracion requerida.');
        }
        return $value;
    }

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
