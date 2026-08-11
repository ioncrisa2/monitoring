<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section aria-labelledby="profile-information-heading">
    <header>
        <h2 id="profile-information-heading" class="ui-section-heading">Informasi profil</h2>
        <p class="ui-section-description">Perbarui nama dan alamat email yang terhubung dengan akun Anda.</p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-5 max-w-xl space-y-4">
        <div>
            <x-input-label for="profile-name" value="Nama lengkap" />
            <x-text-input
                wire:model="name"
                id="profile-name"
                name="name"
                type="text"
                class="mt-1"
                required
                autofocus
                autocomplete="name"
                aria-describedby="profile-name-error"
                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
            />
            <x-input-error id="profile-name-error" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="profile-email" value="Email" />
            <x-text-input
                wire:model="email"
                id="profile-email"
                name="email"
                type="email"
                class="mt-1"
                required
                autocomplete="username"
                aria-describedby="profile-email-error"
                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
            />
            <x-input-error id="profile-email-error" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="text-sm text-ink-secondary">
                        Email Anda belum diverifikasi.
                        <button wire:click.prevent="sendVerification" type="button" class="ui-text-action -mx-2">Kirim ulang email verifikasi</button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <x-flash-message :dismissible="false" class="mt-2">Tautan verifikasi baru telah dikirim ke email Anda.</x-flash-message>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-4 pt-1">
            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="updateProfileInformation">
                <span wire:loading.remove wire:target="updateProfileInformation">Simpan profil</span>
                <span wire:loading wire:target="updateProfileInformation">Menyimpan…</span>
            </x-primary-button>

            <x-action-message on="profile-updated">Profil tersimpan.</x-action-message>
        </div>
    </form>
</section>
