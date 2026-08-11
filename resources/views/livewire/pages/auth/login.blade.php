<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-auth-heading title="Masuk ke Sistem" description="Gunakan akun perusahaan Anda untuk melanjutkan." />

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input
                wire:model="form.email"
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="username"
                class="mt-1"
                aria-describedby="login-email-error"
                aria-invalid="{{ $errors->has('form.email') ? 'true' : 'false' }}"
            />
            <x-input-error id="login-email-error" :messages="$errors->get('form.email')" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input
                wire:model="form.password"
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="mt-1"
                aria-describedby="login-password-error"
                aria-invalid="{{ $errors->has('form.password') ? 'true' : 'false' }}"
            />
            <x-input-error id="login-password-error" :messages="$errors->get('form.password')" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label for="remember" class="flex min-h-10 cursor-pointer items-center gap-2 text-sm text-ink-secondary">
                <input wire:model="form.remember" id="remember" type="checkbox" name="remember" class="size-4 rounded border-line-strong text-brand focus:ring-brand">
                Ingat saya
            </label>

            @if(Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate class="ui-text-action -mr-2">Lupa password?</a>
            @endif
        </div>

        <x-primary-button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="login"
            class="w-full"
        >
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memeriksa…</span>
        </x-primary-button>
    </form>
</div>
