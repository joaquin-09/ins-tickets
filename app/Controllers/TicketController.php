<?php

declare(strict_types=1);

namespace InsTickets\Controllers;

use InsTickets\Core\Auth;
use InsTickets\Core\Database;
use InsTickets\Core\HttpException;
use InsTickets\Core\Input;
use InsTickets\Core\Session;
use InsTickets\Core\Submission;
use InsTickets\Core\ValidationException;
use InsTickets\Core\View;
use InsTickets\Repositories\TicketRepository;
use InsTickets\Services\TicketPolicy;
use InsTickets\Services\TicketService;

final class TicketController
{
    public function index(string $mode = 'mine'): void
    {
        $actor = Auth::requireUser();
        $repo = new TicketRepository(Database::connection());
        $title = $mode === 'all' ? 'Gestión de tickets' : ($mode === 'assigned' ? 'Asignados a mí' : 'Mis solicitudes');
        TicketRepository::scope($actor, $mode);
        $errors = [];
        try {
            $filters = TicketRepository::filters($actor, $_GET);
            $list = $repo->page($actor, $mode, $filters, Input::page($_GET['pagina'] ?? 1));
        } catch (ValidationException $exception) {
            $errors = $exception->errors;
            $filters = Input::old($_GET, ['q', 'estado', 'prioridad', 'categoria_id', 'agente_id', 'solicitante_id']);
            $list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }
        $basePath = $mode === 'all' ? '/tickets' : ($mode === 'assigned' ? '/tickets/asignados' : '/tickets/mis');
        View::render('tickets/index', ['title' => $title, 'list' => $list, 'filters' => $filters, 'errors' => $errors, 'basePath' => $basePath, 'categories' => $repo->categories(), 'users' => $actor->role === 'administrador' ? $repo->users() : []], $errors === [] ? 200 : 422);
    }

    public function createForm(): void
    {
        $actor = Auth::requireUser();
        $repo = new TicketRepository(Database::connection());
        View::render('tickets/form', ['title' => 'Crear ticket', 'ticket' => null, 'canText' => true, 'old' => ['prioridad' => 'media'], 'errors' => [], 'categories' => $repo->categories(true), 'submission' => Submission::issue($actor->id)]);
    }

    public function create(): void
    {
        $actor = Auth::requireUser();
        $key = Submission::verify($_POST['submission_key'] ?? null, $actor->id);
        try {
            $id = (new TicketService(Database::connection()))->create($actor->id, $_POST, $key);
            Submission::markUsed($key);
            Session::flash('Solicitud registrada. Reenviar este formulario no crea otro ticket.');
            Auth::redirect('/tickets/detalle?id=' . $id);
        } catch (ValidationException $exception) {
            $repo = new TicketRepository(Database::connection());
            View::render('tickets/form', ['title' => 'Crear ticket', 'ticket' => null, 'canText' => true, 'old' => Input::old($_POST, ['titulo', 'descripcion', 'categoria_id', 'prioridad']), 'errors' => $exception->errors, 'categories' => $repo->categories(true), 'submission' => $key], 422);
        }
    }

    public function detail(): void
    {
        $actor = Auth::requireUser();
        $repo = new TicketRepository(Database::connection());
        $id = Input::id($_GET['id'] ?? null);
        $ticket = $repo->find($actor, $id) ?? throw new HttpException(404, 'No se encontró el ticket.');
        View::render('tickets/detail', ['title' => 'Detalle de ticket', 'ticket' => $ticket, 'comments' => $repo->comments($id, Input::page($_GET['comentarios_pagina'] ?? 1)), 'history' => $repo->history($id, Input::page($_GET['historial_pagina'] ?? 1)), 'agents' => $actor->role === 'administrador' ? $repo->users() : []]);
    }

    public function editForm(): void
    {
        $actor = Auth::requireUser();
        $repo = new TicketRepository(Database::connection());
        $ticket = $repo->find($actor, Input::id($_GET['id'] ?? null)) ?? throw new HttpException(404, 'No se encontró el ticket.');
        if (!TicketPolicy::classification($actor, $ticket)) {
            throw new HttpException(403, 'No se puede editar este ticket con tu permiso y su estado actual.');
        }
        View::render('tickets/form', ['title' => 'Editar ticket', 'ticket' => $ticket, 'canText' => TicketPolicy::text($actor, $ticket), 'old' => $ticket, 'errors' => [], 'categories' => $repo->categories(true, (int) $ticket['categoria_id'])]);
    }

    public function edit(): void
    {
        $actor = Auth::requireUser();
        $id = Input::id($_POST['id'] ?? null);
        $version = filter_var($_POST['version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($version === false) {
            throw new HttpException(409, 'La versión del formulario no es válida. Recargá el ticket.');
        }
        try {
            $changed = (new TicketService(Database::connection()))->edit($actor->id, $id, $version, $_POST);
            Session::flash($changed ? 'Ticket actualizado con registro de historial.' : 'No se modificaron datos: los valores eran iguales.');
            Auth::redirect('/tickets/detalle?id=' . $id);
        } catch (ValidationException $exception) {
            $repo = new TicketRepository(Database::connection());
            $ticket = $repo->find($actor, $id) ?? throw new HttpException(404, 'No se encontró el ticket.');
            $old = Input::old($_POST, ['titulo', 'descripcion', 'categoria_id', 'prioridad']) + $ticket;
            if (!TicketPolicy::text($actor, $ticket)) {
                $old['titulo'] = $ticket['titulo'];
                $old['descripcion'] = $ticket['descripcion'];
            }
            // Conservar la versión enviada: una recarga visual no autoriza sobrescribir concurrencia.
            $ticket['version'] = $version;
            View::render('tickets/form', ['title' => 'Editar ticket', 'ticket' => $ticket, 'canText' => TicketPolicy::text($actor, $ticket), 'old' => $old, 'errors' => $exception->errors, 'categories' => $repo->categories(true, (int) $ticket['categoria_id'])], 422);
        } catch (HttpException $exception) {
            if ($exception->status !== 409) {
                throw $exception;
            }
            View::render('tickets/conflict', ['title' => 'Conflicto de edición', 'message' => $exception->getMessage(), 'id' => $id, 'old' => Input::old($_POST, ['titulo', 'descripcion', 'categoria_id', 'prioridad'])], 409);
        }
    }
}
