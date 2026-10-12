<?php

declare(strict_types=1);

namespace InsTickets\Tests\Unit;

use InsTickets\Core\ValidationException;
use InsTickets\Models\User;
use InsTickets\Services\AttentionPolicy;
use InsTickets\Services\AttentionValidation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttentionTest extends TestCase
{
    public static function policies(): array
    {
        $cases = [];
        foreach (['usuario','agente','administrador'] as $role) {
            foreach (['abierto','en_progreso','resuelto','cerrado'] as $state) {
                foreach (['own','assigned','other'] as $relation) {
                    $visible = $role === 'administrador' || $relation === 'own' || ($role === 'agente' && $relation === 'assigned');
                    $support = $visible && $relation !== 'own' && ($role === 'administrador' || ($role === 'agente' && $relation === 'assigned'));
                    $cases["$role-$state-$relation"] = [$role,$state,$relation,
                        $role === 'administrador' && in_array($state,['abierto','en_progreso'],true),
                        $support && $state === 'abierto', $support && $state === 'en_progreso',
                        $visible && $state === 'resuelto' && ($role === 'administrador' || $relation === 'own'),
                        $visible && $state !== 'cerrado', $support && in_array($state,['abierto','en_progreso'],true)];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('policies')]
    public function testAttentionMatrix(string $role,string $state,string $relation,bool $assign,bool $start,bool $resolve,bool $close,bool $comment,bool $response): void
    {
        $actor = new User(1,'Demo','demo@nexoti.test',$role,true,'');
        $ticket = ['solicitante_id' => $relation === 'own' ? 1 : 2,'agente_id' => $relation === 'assigned' ? 1 : 3,'estado' => $state];
        self::assertSame($assign,AttentionPolicy::assign($actor,$ticket));
        self::assertSame($start,AttentionPolicy::start($actor,$ticket));
        self::assertSame($resolve,AttentionPolicy::resolve($actor,$ticket));
        self::assertSame($close,AttentionPolicy::close($actor,$ticket));
        self::assertSame($comment,AttentionPolicy::comment($actor,$ticket));
        self::assertSame($response,AttentionPolicy::firstResponse($actor,$ticket));
    }

    public function testAdminCannotStartWithoutAssignment(): void
    {
        $actor = new User(1,'Demo','demo@nexoti.test','administrador',true,'');
        $ticket = ['solicitante_id'=>2,'agente_id'=>null,'estado'=>'abierto'];
        self::assertFalse(AttentionPolicy::start($actor,$ticket));
        self::assertTrue(AttentionPolicy::firstResponse($actor,$ticket));
    }

    public function testInactiveAdminAndSelfAssignedAgentCannotAttend(): void
    {
        $inactive = new User(1,'Demo','demo@nexoti.test','administrador',false,'');
        $self = new User(1,'Demo','demo@nexoti.test','agente',true,'');
        $ticket = ['solicitante_id'=>1,'agente_id'=>1,'estado'=>'en_progreso'];
        self::assertFalse(AttentionPolicy::resolve($inactive,$ticket));
        self::assertFalse(AttentionPolicy::resolve($self,$ticket));
        self::assertFalse(AttentionPolicy::firstResponse($self,$ticket));
    }

    public static function invalidText(): array
    {
        return [[' ',1,2000],['',1,2000],['123456789',10,2000],[str_repeat('x',2001),1,2000],["\xFF",1,2000]];
    }

    #[DataProvider('invalidText')]
    public function testInvalidCommentOrSummaryIsRejected(string $value,int $min,int $max): void
    {
        $this->expectException(ValidationException::class);
        AttentionValidation::text($value,'contenido',$min,$max);
    }

    public function testTextBoundaries(): void
    {
        self::assertSame('á',AttentionValidation::text(' á ','contenido',1,2000));
        self::assertSame(2000,mb_strlen(AttentionValidation::text(str_repeat('ñ',2000),'contenido',1,2000)));
        self::assertSame('1234567890',AttentionValidation::text('1234567890','resolution_summary',10,2000));
    }

    public static function invalidAgents(): array
    {
        return [[null],[['1']],['0'],['-1'],['agente'],['1 OR 1=1']];
    }

    #[DataProvider('invalidAgents')]
    public function testInvalidAgentInput(mixed $value): void
    {
        $this->expectException(ValidationException::class);
        AttentionValidation::agent($value);
    }

    public function testAgentSelectionTypes(): void
    {
        self::assertNull(AttentionValidation::agent(''));
        self::assertSame(2,AttentionValidation::agent('2'));
    }
}
