<?php

namespace App\Actions\Auth;

use App\Models\User;

/**
 * Legt einen internen Benutzer an.
 *
 * Benutzer werden ausschließlich manuell durch einen bestehenden Benutzer
 * angelegt — es gibt keine öffentliche Registrierung.
 */
class CreateUser
{
    public function __invoke(string $name, string $email, string $password): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }
}
