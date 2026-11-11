<?php

namespace App\Support;

use App\Models\Ambulance;
use App\Models\DispatchEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Reconoce lo que es de la demostración: usuarios con la contraseña de demo
 * o con los correos del DemoSeeder, y las unidades del simulador.
 */
class DemoData
{
    /** @return Collection<int, User> */
    public function users(): Collection
    {
        $emails = config('operations.demo.users', []);
        $password = config('operations.demo.password');

        return User::query()->get()->filter(
            fn (User $user) => in_array($user->email, $emails, true) || ($password && Hash::check($password, $user->password))
        )->values();
    }

    /** @return Collection<int, Ambulance> */
    public function simulatedUnits(): Collection
    {
        return Ambulance::where('is_simulated', true)->get();
    }

    /** ¿Alguien que no es de la demo ya registró movimientos en servicios? */
    public function hasRealActivity(): bool
    {
        return DispatchEvent::query()
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', $this->users()->pluck('id'))
            ->exists();
    }
}
