<?php

declare(strict_types=1);

namespace InsTickets\Tests\Unit;

use InsTickets\Core\ValidationException;
use InsTickets\Services\Validation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public static function passwords(): array
    {
        return [
            'mínimo' => [str_repeat('a', 10), true],
            'muy corta' => [str_repeat('a', 9), false],
            'máximo caracteres' => [str_repeat('a', 64), true],
            'demasiados caracteres' => [str_repeat('a', 65), false],
            'límite UTF8 bytes' => [str_repeat('á', 36), true],
            'exceso UTF8 bytes' => [str_repeat('á', 37), false],
            'espacios permitidos' => ['  clave válida  ', true],
            'nulo bcrypt' => ["contraseña\0válida", false],
            'UTF8 inválido' => [str_repeat("\xFF", 12), false],
        ];
    }

    #[DataProvider('passwords')]
    public function testPasswordBoundaries(string $value, bool $valid): void
    {
        self::assertSame($valid, Validation::password($value) === null);
    }

    public function testUserNormalizesEmailAndPreservesPassword(): void
    {
        $input = ['nombre' => '  Ana Demo  ', 'email' => ' ANA@NEXOTI.TEST ', 'rol' => 'usuario', 'activo' => '1', 'password' => '  clave válida  '];
        $data = Validation::user($input, true);
        self::assertSame('ana@nexoti.test', $data['email']);
        self::assertSame('Ana Demo', $data['nombre']);
        self::assertSame($input['password'], $data['password']);
    }

    public static function invalidUsers(): array
    {
        return [['rol', 'superadmin'], ['activo', '2'], ['email', 'sin-correo'], ['nombre', ' '], ['nombre', str_repeat('n', 101)], ['email', ['inyectado']]];
    }

    #[DataProvider('invalidUsers')]
    public function testInvalidUserInputIsRejected(string $field, mixed $value): void
    {
        $input = ['nombre' => 'Ana Demo', 'email' => 'ana@nexoti.test', 'rol' => 'usuario', 'activo' => '1', 'password' => 'ClaveLocal2026!'];
        $input[$field] = $value;
        $this->expectException(ValidationException::class);
        Validation::user($input, true);
    }

    public function testBlankPasswordMeansKeepOnEdit(): void
    {
        $input = ['nombre' => 'Ana Demo', 'email' => 'ana@nexoti.test', 'rol' => 'usuario', 'activo' => '1', 'password' => ''];
        self::assertSame('', Validation::user($input, false)['password']);
        $this->expectException(ValidationException::class);
        Validation::user($input, true);
    }

    public static function invalidCategories(): array
    {
        return [['nombre', ' '], ['nombre', 'a'], ['nombre', str_repeat('c', 81)], ['descripcion', str_repeat('d', 256)], ['activa', 'yes']];
    }

    #[DataProvider('invalidCategories')]
    public function testInvalidCategoryInputIsRejected(string $field, mixed $value): void
    {
        $input = ['nombre' => 'Categoría Demo', 'descripcion' => '', 'activa' => '1'];
        $input[$field] = $value;
        $this->expectException(ValidationException::class);
        Validation::category($input);
    }

    public function testOptionalCategoryDescription(): void
    {
        $result = Validation::category(['nombre' => ' Red ', 'descripcion' => ' ', 'activa' => '0']);
        self::assertSame('Red', $result['nombre']);
        self::assertNull($result['descripcion']);
        self::assertSame(0, $result['activa']);
    }
}
