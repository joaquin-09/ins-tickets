<?php

declare(strict_types=1);

namespace InsTickets\Controllers;

use InsTickets\Core\Auth;
use InsTickets\Core\Database;
use InsTickets\Core\HttpException;
use InsTickets\Core\Input;
use InsTickets\Core\Session;
use InsTickets\Core\ValidationException;
use InsTickets\Core\View;
use InsTickets\Repositories\TicketRepository;
use InsTickets\Services\AttentionService;
use InsTickets\Services\AttentionValidation;

final class AttentionController
{
    private function handle(callable $operation, string $message, bool $comment = false): void
    {
        $actor = Auth::requireUser();
        $id = Input::id($_POST['id'] ?? null);
        $old = array_intersect_key(Input::old($_POST, ['agente_id', 'estado', 'resolution_summary', 'contenido']), $_POST);
        try {
            $version = filter_var($_POST['version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($version === false) {
                throw new HttpException(409, 'La versión enviada no es válida. Recargá el detalle.');
            }
            $operation(new AttentionService(Database::connection()), $actor->id, $id, $version);
            Session::flash($message);
            $repo = new TicketRepository(Database::connection());
            $page = $comment ? $repo->comments($id, PHP_INT_MAX)['pages'] : $repo->history($id, PHP_INT_MAX)['pages'];
            Auth::redirect('/tickets/detalle?id=' . $id . '&' . ($comment ? 'comentarios_pagina=' : 'historial_pagina=') . $page . ($comment ? '#conversacion' : '#historial'));
        } catch (ValidationException $exception) {
            $repo = new TicketRepository(Database::connection());
            $ticket = $repo->find($actor, $id) ?? throw new HttpException(404, 'No se encontró el ticket.');
            $ticket['version'] = $version;
            View::render('tickets/detail', ['title' => 'Atención del ticket', 'ticket' => $ticket, 'comments' => $repo->comments($id), 'history' => $repo->history($id), 'agents' => $actor->role === 'administrador' ? $repo->users() : [], 'attentionErrors' => $exception->errors, 'attentionOld' => $old], 422);
        } catch (HttpException $exception) {
            if ($exception->status !== 409) {
                throw $exception;
            }
            View::render('attention/conflict', ['title' => 'Conflicto de atención', 'message' => $exception->getMessage(), 'id' => $id, 'old' => $old], 409);
        }
    }

    public function assign(): void
    {
        Auth::requireAdmin();
        $this->handle(static fn (AttentionService $service, int $actor, int $id, int $version) => $service->assign($actor, $id, $version, AttentionValidation::agent($_POST['agente_id'] ?? null)), 'Asignación actualizada con historial.');
    }

    public function transition(): void
    {
        $this->handle(static fn (AttentionService $service, int $actor, int $id, int $version) => $service->transition($actor, $id, $version, Input::string($_POST, 'estado'), Input::string($_POST, 'resolution_summary')), 'Estado actualizado con historial y fechas de servidor.');
    }

    public function comment(): void
    {
        $this->handle(static fn (AttentionService $service, int $actor, int $id, int $version) => $service->comment($actor, $id, $version, Input::string($_POST, 'contenido')), 'Comentario registrado.', true);
    }
}
