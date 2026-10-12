<?php

declare(strict_types=1);

namespace InsTickets\Services;

final class LoginPolicy
{
    public static function key(string $email, string $ip, string $secret): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email), 'UTF-8') . "\0" . $ip, $secret);
    }

    public static function blocked(int $failures): bool
    {
        return $failures >= 5;
    }
}
