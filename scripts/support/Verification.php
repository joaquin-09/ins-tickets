<?php

declare(strict_types=1);

namespace InsTickets\Verification;

use InsTickets\Core\Config;
use PDO;
use RuntimeException;

final class Verification
{
    public const TABLES = ['usuarios', 'categorias', 'tickets', 'comentarios', 'historial_tickets', 'intentos_acceso'];

    public static function isolated(string $host = 'db-validation'): void
    {
        if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'validation'
            || getenv('DB_HOST') !== $host || getenv('DB_NAME') !== 'ins_tickets') {
            throw new RuntimeException('Requiere CLI y servidor aislado de validación.');
        }
    }

    public static function maintenance(): PDO
    {
        return new PDO('mysql:host=' . Config::get('DB_HOST') . ';dbname=ins_tickets;charset=utf8mb4',
            'root', Config::get('VALIDATION_ROOT_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }

    public static function fingerprint(PDO $pdo): array
    {
        $pdo->exec("SET time_zone='+00:00'");
        $result = [];
        foreach (self::TABLES as $table) {
            $hash = hash_init('sha256');
            $count = 0;
            $query = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id');
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
                $count++;
            }
            $result[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
        }
        return $result;
    }

    public static function evidence(string $name, array $data): void
    {
        $path = Config::root() . '/storage/reports/' . $name . '.json';
        $runtime = Config::root() . '/storage/reports/runtime.json';
        $data = ['recorded_at_utc' => gmdate('c'), 'php' => PHP_VERSION,
            'environment' => Config::get('APP_ENV'),
            'runtime' => is_file($runtime) ? json_decode(file_get_contents($runtime), true, 512, JSON_THROW_ON_ERROR) : null] + $data;
        if (file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            throw new RuntimeException('No se pudo escribir evidencia.');
        }
        echo 'Evidencia: storage/reports/' . $name . ".json\n";
    }
}
