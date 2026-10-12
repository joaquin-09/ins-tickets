<?php

declare(strict_types=1);

namespace InsTickets\Models;

final readonly class User
{
    public function __construct(public int $id, public string $name, public string $email, public string $role, public bool $active, private string $hash)
    {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], $row['nombre'], $row['email'], $row['rol'], (bool) $row['activo'], $row['password_hash']);
    }

    public function passwordHash(): string
    {
        return $this->hash;
    }
}
