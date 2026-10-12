<?php

declare(strict_types=1);

namespace InsTickets\Core;

final class Submission
{
    public static function issue(int $userId): string
    {
        $key = bin2hex(random_bytes(32));
        $_SESSION['submissions'][$key] = ['user_id' => $userId, 'used' => false];
        while (count($_SESSION['submissions']) > 20) {
            unset($_SESSION['submissions'][array_key_first($_SESSION['submissions'])]);
        }
        return $key;
    }

    public static function verify(mixed $key, int $userId): string
    {
        if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key) || ($_SESSION['submissions'][$key]['user_id'] ?? null) !== $userId) {
            throw new HttpException(409, 'El formulario de creación expiró. Abrí uno nuevo.');
        }
        // Un reenvío conserva la clave para recuperar el mismo ticket, no crear otro.
        return $key;
    }

    public static function markUsed(string $key): void
    {
        $_SESSION['submissions'][$key]['used'] = true;
    }
}
