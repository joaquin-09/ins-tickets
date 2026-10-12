<?php

declare(strict_types=1);

namespace InsTickets\Tests\Unit;

use InsTickets\Core\Dates;
use InsTickets\Core\HttpException;
use InsTickets\Core\Submission;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use InsTickets\Repositories\TicketRepository;
use InsTickets\Services\TicketPolicy;
use InsTickets\Services\TicketValidation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TicketTest extends TestCase
{
    public static function permissions(): array
    {
        $cases = [];
        foreach (['usuario', 'agente', 'administrador'] as $role) {
            foreach (['abierto', 'en_progreso', 'resuelto', 'cerrado'] as $state) {
                foreach (['own', 'assigned', 'other'] as $relation) {
                    $visible = $role === 'administrador' || $relation === 'own' || ($role === 'agente' && $relation === 'assigned');
                    $text = $visible && $state === 'abierto' && ($role === 'administrador' || $relation === 'own');
                    $classification = $visible && (($state === 'abierto' && ($role === 'administrador' || $relation === 'own')) || (in_array($state, ['abierto', 'en_progreso'], true) && ($role === 'administrador' || ($role === 'agente' && $relation === 'assigned'))));
                    $cases[$role . '-' . $state . '-' . $relation] = [$role, $state, $relation, $visible, $text, $classification];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('permissions')]
    public function testPermissionMatrix(string $role, string $state, string $relation, bool $visible, bool $text, bool $classification): void
    {
        $user = new User(1, 'Demo', 'demo@nexoti.test', $role, true, '');
        $ticket = ['solicitante_id' => $relation === 'own' ? 1 : 2, 'agente_id' => $relation === 'assigned' ? 1 : null, 'estado' => $state];
        self::assertSame($visible, TicketPolicy::visible($user, $ticket));
        self::assertSame($text, TicketPolicy::text($user, $ticket));
        self::assertSame($classification, TicketPolicy::classification($user, $ticket));
    }

    public function testInactiveAdminCannotSeeOrEdit(): void
    {
        $user = new User(1, 'Demo', 'demo@nexoti.test', 'administrador', false, '');
        $ticket = ['solicitante_id' => 1, 'agente_id' => null, 'estado' => 'abierto'];
        self::assertFalse(TicketPolicy::visible($user, $ticket));
        self::assertFalse(TicketPolicy::text($user, $ticket));
        self::assertFalse(TicketPolicy::classification($user, $ticket));
    }

    public static function invalidData(): array
    {
        return [['titulo', '    '], ['titulo', 'abcd'], ['titulo', str_repeat('a',151)], ['descripcion', '123456789'], ['descripcion', str_repeat('d',5001)], ['prioridad', 'critica'], ['categoria_id', '0'], ['categoria_id', '1 OR 1=1'], ['titulo', ['inyeccion']]];
    }

    #[DataProvider('invalidData')]
    public function testInvalidTicketDataIsRejected(string $key, mixed $value): void
    {
        $input = ['titulo' => 'Título válido', 'descripcion' => 'Descripción válida de prueba', 'categoria_id' => '1', 'prioridad' => 'media'];
        $input[$key] = $value;
        $this->expectException(ValidationException::class);
        TicketValidation::data($input, true);
    }

    public function testClassificationDoesNotRequireTextAndMaxBoundaries(): void
    {
        $data = TicketValidation::data(['categoria_id' => '1', 'prioridad' => 'alta'], false);
        self::assertSame(['prioridad' => 'alta', 'categoria_id' => 1], $data);
        $data = TicketValidation::data(['titulo' => str_repeat('á',150), 'descripcion' => str_repeat('ñ',5000), 'categoria_id' => '1', 'prioridad' => 'baja'], true);
        self::assertSame(150, mb_strlen($data['titulo']));
    }

    public function testSubmissionIsSessionAndUserBound(): void
    {
        $_SESSION = [];
        $key = Submission::issue(1);
        self::assertSame($key, Submission::verify($key,1));
        Submission::markUsed($key);
        self::assertSame($key, Submission::verify($key,1));
        $this->expectException(HttpException::class);
        Submission::verify($key,2);
    }

    public function testSubmissionFromOtherSessionIsRejected(): void
    {
        $_SESSION = [];
        $key = Submission::issue(1);
        $_SESSION = [];
        $this->expectException(HttpException::class);
        Submission::verify($key,1);
    }

    public function testOldSubmissionFormsAreBounded(): void
    {
        $_SESSION = [];
        $first = Submission::issue(1);
        for ($i=0;$i<20;$i++) { Submission::issue(1); }
        self::assertCount(20,$_SESSION['submissions']);
        $this->expectException(HttpException::class);
        Submission::verify($first,1);
    }

    public function testNonAdminFiltersCannotAddAgentOrRequester(): void
    {
        $user = new User(1,'Demo','demo@nexoti.test','usuario',true,'');
        $filters = TicketRepository::filters($user,['q' => 'TCK-000001', 'agente_id' => '2', 'solicitante_id' => '3']);
        self::assertArrayNotHasKey('agente_id',$filters);
        self::assertArrayNotHasKey('solicitante_id',$filters);
        self::assertSame('TCK-000001',$filters['q']);
    }

    public function testInvalidFilterIsRejected(): void
    {
        $user = new User(1,'Demo','demo@nexoti.test','usuario',true,'');
        $this->expectException(ValidationException::class);
        TicketRepository::filters($user,['estado' => 'reabierto']);
    }

    public function testLocalDatesAndCodes(): void
    {
        self::assertSame('12/10/2026 19:00',Dates::local('2026-10-13 01:00:00'));
        self::assertSame('Sin datos',Dates::local(null));
        self::assertSame('TCK-000007',Dates::code(7));
        self::assertSame('TCK-1000000',Dates::code(1000000));
    }
}
