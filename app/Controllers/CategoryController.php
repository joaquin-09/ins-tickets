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
use InsTickets\Repositories\CategoryRepository;
use InsTickets\Services\CategoryService;

final class CategoryController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $list = (new CategoryRepository(Database::connection()))->page(Input::page($_GET['pagina'] ?? 1));
        View::render('categories/index', ['title' => 'Categorías', 'list' => $list]);
    }

    public function form(): void
    {
        Auth::requireAdmin();
        $id = isset($_GET['id']) ? Input::id($_GET['id']) : null;
        $old = $id === null ? ['nombre' => '', 'descripcion' => '', 'activa' => '1'] : ((new CategoryRepository(Database::connection()))->find($id) ?? throw new HttpException(404, 'No se encontró la categoría.'));
        View::render('categories/form', ['title' => $id === null ? 'Crear categoría' : 'Editar categoría', 'id' => $id, 'old' => $old, 'errors' => []]);
    }

    public function save(): void
    {
        $actor = Auth::requireAdmin();
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? Input::id($_POST['id']) : null;
        try {
            (new CategoryService(Database::connection()))->save($actor->id, $id, $_POST);
            Session::flash($id === null ? 'Categoría creada.' : 'Categoría actualizada.');
            Auth::redirect('/categorias');
        } catch (ValidationException $exception) {
            View::render('categories/form', ['title' => $id === null ? 'Crear categoría' : 'Editar categoría', 'id' => $id, 'old' => Input::old($_POST, ['nombre', 'descripcion', 'activa']), 'errors' => $exception->errors], 422);
        }
    }

    public function deleteForm(): void
    {
        Auth::requireAdmin();
        $id = Input::id($_GET['id'] ?? null);
        $category = (new CategoryRepository(Database::connection()))->find($id) ?? throw new HttpException(404, 'No se encontró la categoría.');
        View::render('admin/delete', ['title' => 'Eliminar categoría', 'id' => $id, 'name' => $category['nombre'], 'action' => '/categorias/eliminar', 'back' => '/categorias', 'errors' => []]);
    }

    public function delete(): void
    {
        $actor = Auth::requireAdmin();
        $id = Input::id($_POST['id'] ?? null);
        try {
            if (Input::string($_POST, 'confirmar') !== '1') {
                throw new ValidationException(['general' => 'Confirmá explícitamente la eliminación.']);
            }
            (new CategoryService(Database::connection()))->delete($actor->id, $id);
            Session::flash('Categoría eliminada sin afectar tickets históricos.');
            Auth::redirect('/categorias');
        } catch (ValidationException $exception) {
            $category = (new CategoryRepository(Database::connection()))->find($id) ?? throw new HttpException(404, 'No se encontró la categoría.');
            View::render('admin/delete', ['title' => 'Eliminar categoría', 'id' => $id, 'name' => $category['nombre'], 'action' => '/categorias/eliminar', 'back' => '/categorias', 'errors' => $exception->errors], 422);
        }
    }
}
