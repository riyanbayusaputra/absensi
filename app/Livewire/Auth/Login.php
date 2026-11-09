<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.mobile')]
class Login extends Component
{
    public string $email = '';
    public string $password = '';

    public function login()
    {
        $this->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $ok = Auth::attempt([
            'email'     => $this->email,
            'password'  => $this->password,
            'is_active' => true,
        ]);

        if (! $ok) {
            $this->addError('email', 'Email atau password salah, atau akun tidak aktif.');
            return;
        }

        session()->regenerate();

        return redirect()->intended(route('app.home'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}