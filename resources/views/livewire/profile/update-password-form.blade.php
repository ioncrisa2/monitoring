<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section aria-labelledby="profile-password-heading">
    <header>
        <h2 id="profile-password-heading" class="ui-section-heading">Ubah password</h2>
        <p class="ui-section-description">Gunakan password yang kuat dan tidak dipakai pada layanan lain.</p>
    </header>

    <form wire:submit="updatePassword" class="mt-5 max-w-xl space-y-4">
        <div>
            <x-input-label for="update-password-current" value="Password saat ini" />
            <x-text-input wire:model="current_password" id="update-password-current" name="current_password" type="password" class="mt-1" autocomplete="current-password" aria-describedby="update-password-current-error" aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}" />
            <x-input-error id="update-password-current-error" :messages="$errors->get('current_password')" />
        </div>

        <div>
            <x-input-label for="update-password-new" value="Password baru" />
            <x-text-input wire:model="password" id="update-password-new" name="password" type="password" class="mt-1" autocomplete="new-password" aria-describedby="update-password-new-error" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" />
            <x-input-error id="update-password-new-error" :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="update-password-confirmation" value="Konfirmasi password baru" />
            <x-text-input wire:model="password_confirmation" id="update-password-confirmation" name="password_confirmation" type="password" class="mt-1" autocomplete="new-password" aria-describedby="update-password-confirmation-error" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" />
            <x-input-error id="update-password-confirmation-error" :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="flex flex-wrap items-center gap-4 pt-1">
            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="updatePassword">
                <span wire:loading.remove wire:target="updatePassword">Simpan password</span>
                <span wire:loading wire:target="updatePassword">Menyimpan…</span>
            </x-primary-button>

            <x-action-message on="password-updated">Password tersimpan.</x-action-message>
        </div>
    </form>
</section>
