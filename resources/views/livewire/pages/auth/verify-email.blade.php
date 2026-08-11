<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <x-auth-heading title="Verifikasi Email" description="Buka tautan verifikasi yang telah dikirim ke email Anda sebelum melanjutkan." />

    @if(session('status') == 'verification-link-sent')
        <x-flash-message :dismissible="false" class="mb-5">Tautan verifikasi baru telah dikirim.</x-flash-message>
    @endif

    <div class="space-y-3">
        <x-primary-button
            type="button"
            wire:click="sendVerification"
            wire:loading.attr="disabled"
            wire:target="sendVerification"
            class="w-full"
        >
            <span wire:loading.remove wire:target="sendVerification">Kirim ulang tautan</span>
            <span wire:loading wire:target="sendVerification">Mengirim…</span>
        </x-primary-button>

        <button wire:click="logout" type="button" class="ui-btn ui-btn-ghost w-full">Keluar</button>
    </div>
</div>
