<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <x-auth-heading title="Lupa Password" description="Masukkan email akun Anda. Kami akan mengirimkan tautan untuk membuat password baru." />

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-5">
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input
                wire:model="email"
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="email"
                class="mt-1"
                aria-describedby="forgot-email-error"
                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
            />
            <x-input-error id="forgot-email-error" :messages="$errors->get('email')" />
        </div>

        <x-primary-button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="sendPasswordResetLink"
            class="w-full"
        >
            <span wire:loading.remove wire:target="sendPasswordResetLink">Kirim tautan reset</span>
            <span wire:loading wire:target="sendPasswordResetLink">Mengirim…</span>
        </x-primary-button>
    </form>

    <div class="mt-5 text-center">
        <a href="{{ route('login') }}" wire:navigate class="ui-text-action">Kembali ke halaman masuk</a>
    </div>
</div>
