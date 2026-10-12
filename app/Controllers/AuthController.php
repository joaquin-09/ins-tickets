<?php

declare(strict_types=1);

namespace InsTickets\Controllers;

use InsTickets\Core\Auth;
use InsTickets\Core\Database;
use InsTickets\Core\Input;
use InsTickets\Core\Session;
use InsTickets\Core\ValidationException;
use InsTickets\Core\View;
use InsTickets\Services\AuthService;

final class AuthController
{
    public function form(): void
    {
        if (Auth::user() !== null) {
            Auth::redirect('/');
        }
        View::render('auth/login', ['title' => 'Iniciar sesión', 'errors' => [], 'old' => []]);
    }

    public function login(): void
    {
        try {
            $email = Input::string($_POST, 'email');
            $password = Input::string($_POST, 'password');
            $user = (new AuthService(Database::connection()))->login($email, $password, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            Session::login($user->id);
            Auth::redirect('/');
        } catch (ValidationException $exception) {
            View::render('auth/login', ['title' => 'Iniciar sesión', 'errors' => $exception->errors, 'old' => Input::old($_POST, ['email'])], 422);
        }
    }

    public function logout(): void
    {
        Auth::requireUser();
        Session::logout();
        Auth::redirect('/login');
    }

    public function passwordForm(): void
    {
        Auth::requireUser();
        View::render('auth/password', ['title' => 'Cambiar contraseña', 'errors' => []]);
    }

    public function password(): void
    {
        $user = Auth::requireUser();
        try {
            (new AuthService(Database::connection()))->changePassword($user->id, Input::string($_POST, 'actual'), Input::string($_POST, 'password'), Input::string($_POST, 'confirmacion'));
            Session::login($user->id);
            Session::flash('Tu contraseña se actualizó.');
            Auth::redirect('/');
        } catch (ValidationException $exception) {
            View::render('auth/password', ['title' => 'Cambiar contraseña', 'errors' => $exception->errors], 422);
        }
    }
}
