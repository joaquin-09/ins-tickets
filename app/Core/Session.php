<?php

declare(strict_types=1);

namespace InsTickets\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $url = parse_url(Config::get('APP_URL'));
        $secure = ($url['scheme'] ?? '') === 'https';
        $local = in_array($url['host'] ?? '', ['localhost', '127.0.0.1', '::1'], true);
        if (!$secure && !$local) {
            throw new HttpException(500, 'El acceso remoto requiere HTTPS.');
        }
        if ($secure && ($_SERVER['HTTPS'] ?? '') !== 'on') {
            throw new HttpException(500, 'No se dispone del transporte HTTPS configurado.');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '1800');
        session_name('INSSESSID');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        if (!session_start()) {
            throw new HttpException(500, 'No se pudo iniciar una sesión segura. Intentá nuevamente.');
        }
        if (isset($_SESSION['user_id'], $_SESSION['last_activity']) && self::expired((int) $_SESSION['last_activity'], time())) {
            self::forgetIdentity();
            self::flash('La sesión expiró por inactividad. Iniciá sesión nuevamente.');
        }
        if (isset($_SESSION['user_id'])) {
            $_SESSION['last_activity'] = time();
        }
    }

    public static function expired(int $lastActivity, int $now): bool
    {
        return $now - $lastActivity >= 1800;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION = ['user_id' => $userId, 'last_activity' => time()];
    }

    public static function forgetIdentity(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        session_destroy();
    }

    public static function flash(string $message): void
    {
        $_SESSION['flash'] = $message;
    }

    public static function takeFlash(): ?string
    {
        $message = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $message;
    }
}
