<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        // En modo demo, el formulario aparece con el usuario de prueba ya escrito
        if (config('app.demo')) {
            $this->form->fill([
                'email' => 'demo@despacho.cr',
                'password' => 'demo1234',
                'remember' => true,
            ]);
        }
    }
}
