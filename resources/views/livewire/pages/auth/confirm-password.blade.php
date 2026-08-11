<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-auth-heading title="Konfirmasi Password" description="Area ini memerlukan verifikasi ulang sebelum Anda dapat melanjutkan." />

    <form wire:submit="confirmPassword" class="space-y-5">
        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input
                wire:model="password"
                id="password"
                type="password"
                name="password"
                required
                autofocus
                autocomplete="current-password"
                class="mt-1"
                aria-describedby="confirm-password-error"
                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
            />
            <x-input-error id="confirm-password-error" :messages="$errors->get('password')" />
        </div>

        <x-primary-button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="confirmPassword"
            class="w-full"
        >
            <span wire:loading.remove wire:target="confirmPassword">Konfirmasi</span>
            <span wire:loading wire:target="confirmPassword">Memeriksa…</span>
        </x-primary-button>
    </form>
</div>
