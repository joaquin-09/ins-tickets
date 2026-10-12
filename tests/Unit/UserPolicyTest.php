<?php

declare(strict_types=1);

namespace InsTickets\Tests\Unit;

use InsTickets\Core\HttpException;
use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use InsTickets\Services\UserPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserPolicyTest extends TestCase
{
    private function user(int $id, string $role, bool $active = true): User
    {
        return new User($id, 'Demo', 'demo@nexoti.test', $role, $active, '');
    }

    public static function forbiddenActors(): array
    {
        return [['usuario', true], ['agente', true], ['administrador', false]];
    }

    #[DataProvider('forbiddenActors')]
    public function testOnlyActiveAdminCanManage(string $role, bool $active): void
    {
        $this->expectException(HttpException::class);
        UserPolicy::admin($this->user(1, $role, $active));
    }

    public static function forbiddenSelfChanges(): array
    {
        return [['usuario', true, false], ['administrador', false, false], ['administrador', true, true]];
    }

    #[DataProvider('forbiddenSelfChanges')]
    public function testSelfAdministrationRestrictions(string $role, bool $active, bool $deleting): void
    {
        $actor = $this->user(1, 'administrador');
        $this->expectException(ValidationException::class);
        UserPolicy::change($actor, $actor, $role, $active, 2, 0, $deleting);
    }

    public static function lossOfLastAdmin(): array
    {
        return [['agente', true, false], ['administrador', false, false], ['administrador', true, true]];
    }

    #[DataProvider('lossOfLastAdmin')]
    public function testLastAdminIsProtected(string $role, bool $active, bool $deleting): void
    {
        $this->expectException(ValidationException::class);
        UserPolicy::change($this->user(2, 'administrador'), $this->user(1, 'administrador'), $role, $active, 1, 0, $deleting);
    }

    public static function agentLossWithPending(): array
    {
        return [['usuario', true, false], ['agente', false, false], ['agente', true, true]];
    }

    #[DataProvider('agentLossWithPending')]
    public function testAgentPendingTicketsMustBeReassigned(string $role, bool $active, bool $deleting): void
    {
        $this->expectException(ValidationException::class);
        UserPolicy::change($this->user(1, 'administrador'), $this->user(2, 'agente'), $role, $active, 1, 1, $deleting);
    }

    public function testAgentCanChangeRoleAfterReassignment(): void
    {
        UserPolicy::change($this->user(1, 'administrador'), $this->user(2, 'agente'), 'usuario', true, 1, 0);
        $this->addToAssertionCount(1);
    }

    public function testSecondAdminAllowsChangeOfOtherAdmin(): void
    {
        UserPolicy::change($this->user(1, 'administrador'), $this->user(2, 'administrador'), 'usuario', true, 2, 0);
        $this->addToAssertionCount(1);
    }
}
