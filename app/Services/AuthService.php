<?php

declare(strict_types=1);

namespace InsTickets\Services;

use InsTickets\Core\Config;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use InsTickets\Repositories\UserRepository;
use PDO;

final class AuthService
{
    private const FAILURE = 'Credenciales no válidas o acceso temporalmente no disponible.';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function login(string $email, string $password, string $ip): User
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        $key = LoginPolicy::key($email, $ip, Config::get('APP_KEY'));
        $lockName = hash('sha256', 'login:' . $key);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?,5)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new ValidationException(['general' => self::FAILURE]);
        }
        try {
            $this->pdo->exec('DELETE FROM intentos_acceso WHERE created_at < UTC_TIMESTAMP(6) - INTERVAL 24 HOUR');
            $count = $this->pdo->prepare('SELECT COUNT(*) FROM intentos_acceso WHERE clave=? AND exitoso=0 AND created_at > UTC_TIMESTAMP(6) - INTERVAL 15 MINUTE');
            $count->execute([$key]);
            if (LoginPolicy::blocked((int) $count->fetchColumn())) {
                throw new ValidationException(['general' => self::FAILURE]);
            }
            $user = strlen($email) <= 254 ? (new UserRepository($this->pdo))->byEmail($email) : null;
            $dummy = '$2y$12$ESHfGE4HfQNz3kVT9gBXE..Upn6adnUld06a1lBndoR1.vPLdNshG';
            $safePassword = strlen($password) <= 72 && mb_check_encoding($password, 'UTF-8') && !str_contains($password, "\0");
            $verified = password_verify($safePassword ? $password : '', $user?->passwordHash() ?? $dummy);
            $success = $user !== null && $user->active && $safePassword && $verified;
            $record = $this->pdo->prepare('INSERT INTO intentos_acceso (clave,exitoso) VALUES (?,?)');
            $record->execute([$key, $success ? 1 : 0]);
            if (!$success) {
                throw new ValidationException(['general' => self::FAILURE]);
            }
            // Un login correcto conserva todos los fallos recientes.
            if (password_needs_rehash($user->passwordHash(), PASSWORD_BCRYPT, ['cost' => 12])) {
                $query = $this->pdo->prepare('UPDATE usuarios SET password_hash=? WHERE id=? AND password_hash=?');
                $query->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $user->id, $user->passwordHash()]);
            }
            return $user;
        } finally {
            $release = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }

    public function changePassword(int $id, string $current, string $next, string $confirmation): void
    {
        $errors = [];
        if (($error = Validation::password($next)) !== null) {
            $errors['password'] = $error;
        }
        if ($next !== $confirmation) {
            $errors['confirmacion'] = 'Las contraseñas nuevas no coinciden.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT * FROM usuarios WHERE id=? FOR UPDATE');
            $query->execute([$id]);
            $row = $query->fetch();
            if (!$row || !(bool) $row['activo'] || strlen($current) > 72 || !password_verify($current, $row['password_hash'])) {
                throw new ValidationException(['actual' => 'No se pudo verificar la contraseña actual.']);
            }
            $query = $this->pdo->prepare('UPDATE usuarios SET password_hash=? WHERE id=?');
            $query->execute([password_hash($next, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
