<?php

declare(strict_types=1);

namespace InsTickets\Core;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Revisá los datos del formulario.');
    }
}
