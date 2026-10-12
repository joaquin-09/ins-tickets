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
use InsTickets\Repositories\UserRepository;
use InsTickets\Services\UserService;

final class UserController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $list = (new UserRepository(Database::connection()))->page(Input::page($_GET['pagina'] ?? 1));
        View::render('users/index', ['title' => 'Usuarios', 'list' => $list]);
    }

    public function form(): void
    {
        Auth::requireAdmin();
        $id = isset($_GET['id']) ? Input::id($_GET['id']) : null;
        $old = ['nombre' => '', 'email' => '', 'rol' => 'usuario', 'activo' => '1'];
        if ($id !== null) {
            $user = (new UserRepository(Database::connection()))->find($id) ?? throw new HttpException(404, 'No se encontró la cuenta.');
            $old = ['nombre' => $user->name, 'email' => $user->email, 'rol' => $user->role, 'activo' => $user->active ? '1' : '0'];
        }
        View::render('users/form', ['title' => $id === null ? 'Crear usuario' : 'Editar usuario', 'id' => $id, 'old' => $old, 'errors' => []]);
    }

    public function save(): void
    {
        $actor = Auth::requireAdmin();
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? Input::id($_POST['id']) : null;
        try {
            (new UserService(Database::connection()))->save($actor->id, $id, $_POST);
            Session::flash($id === null ? 'Usuario creado.' : 'Usuario actualizado.');
            Auth::redirect('/usuarios');
        } catch (ValidationException $exception) {
            View::render('users/form', ['title' => $id === null ? 'Crear usuario' : 'Editar usuario', 'id' => $id, 'old' => Input::old($_POST, ['nombre', 'email', 'rol', 'activo']), 'errors' => $exception->errors], 422);
        }
    }

    public function deleteForm(): void
    {
        Auth::requireAdmin();
        $id = Input::id($_GET['id'] ?? null);
        $user = (new UserRepository(Database::connection()))->find($id) ?? throw new HttpException(404, 'No se encontró la cuenta.');
        View::render('admin/delete', ['title' => 'Eliminar usuario', 'id' => $id, 'name' => $user->name, 'action' => '/usuarios/eliminar', 'back' => '/usuarios', 'errors' => []]);
    }

    public function delete(): void
    {
        $actor = Auth::requireAdmin();
        $id = Input::id($_POST['id'] ?? null);
        try {
            if (Input::string($_POST, 'confirmar') !== '1') {
                throw new ValidationException(['general' => 'Confirmá explícitamente la eliminación.']);
            }
            (new UserService(Database::connection()))->delete($actor->id, $id);
            Session::flash('Usuario eliminado sin afectar registros históricos.');
            Auth::redirect('/usuarios');
        } catch (ValidationException $exception) {
            $user = (new UserRepository(Database::connection()))->find($id) ?? throw new HttpException(404, 'No se encontró la cuenta.');
            View::render('admin/delete', ['title' => 'Eliminar usuario', 'id' => $id, 'name' => $user->name, 'action' => '/usuarios/eliminar', 'back' => '/usuarios', 'errors' => $exception->errors], 422);
        }
    }
}
