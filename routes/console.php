<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cambia el rol de un usuario desde la terminal (por ejemplo, el primer administrador)
Artisan::command('user:role {email} {role}', function (string $email, string $role) {
    $user = User::where('email', $email)->first();
    $newRole = UserRole::tryFrom($role);

    if (! $user || ! $newRole) {
        $this->error('Usuario o rol no válido. Roles: ' . implode(', ', array_column(UserRole::cases(), 'value')));

        return 1;
    }

    $user->update(['role' => $newRole]);
    $this->info("{$user->name} ahora es {$newRole->getLabel()}.");

    return 0;
})->purpose('Cambia el rol de un usuario');
