<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-auth-heading title="Buat Akun" description="Lengkapi identitas dan kredensial untuk membuat akun baru." />

    <form wire:submit="register" class="space-y-4">
        <div>
            <x-input-label for="name" value="Nama lengkap" />
            <x-text-input
                wire:model="name"
                id="name"
                type="text"
                name="name"
                required
                autofocus
                autocomplete="name"
                class="mt-1"
                aria-describedby="register-name-error"
                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
            />
            <x-input-error id="register-name-error" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input
                wire:model="email"
                id="email"
                type="email"
                name="email"
                required
                autocomplete="username"
                class="mt-1"
                aria-describedby="register-email-error"
                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
            />
            <x-input-error id="register-email-error" :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input
                wire:model="password"
                id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                class="mt-1"
                aria-describedby="register-password-error"
                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
            />
            <x-input-error id="register-password-error" :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Konfirmasi password" />
            <x-text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                class="mt-1"
                aria-describedby="register-password-confirmation-error"
                aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}"
            />
            <x-input-error id="register-password-confirmation-error" :messages="$errors->get('password_confirmation')" />
        </div>

        <x-primary-button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="register"
            class="w-full"
        >
            <span wire:loading.remove wire:target="register">Daftar</span>
            <span wire:loading wire:target="register">Membuat akun…</span>
        </x-primary-button>
    </form>

    <p class="mt-5 text-center text-sm text-ink-secondary">
        Sudah memiliki akun?
        <a href="{{ route('login') }}" wire:navigate class="ui-text-action">Masuk</a>
    </p>
</div>
