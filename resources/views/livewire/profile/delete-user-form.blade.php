<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section aria-labelledby="delete-account-heading">
    <header>
        <h2 id="delete-account-heading" class="ui-section-heading text-rose-700 dark:text-rose-400">Hapus akun</h2>
        <p class="ui-section-description">Penghapusan bersifat permanen dan mungkin ditolak jika akun masih terkait dengan data operasional.</p>
    </header>

    <x-danger-button
        type="button"
        class="mt-5"
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Hapus akun</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" maxWidth="sm" labelledby="delete-account-modal-title" focusable>
        <div class="ui-modal-header">
            <div>
                <h2 id="delete-account-modal-title" class="ui-modal-title">Hapus akun secara permanen?</h2>
                <p class="mt-1 text-sm text-ink-muted">Masukkan password Anda untuk mengonfirmasi tindakan ini.</p>
            </div>
            <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup konfirmasi hapus akun">&times;</button>
        </div>

        <form wire:submit="deleteUser">
            <div class="ui-modal-body">
                <x-input-label for="delete-account-password" value="Password" />
                <x-text-input
                    wire:model="password"
                    id="delete-account-password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    class="mt-1"
                    aria-describedby="delete-account-password-error"
                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                />
                <x-input-error id="delete-account-password-error" :messages="$errors->get('password')" />
            </div>

            <div class="ui-modal-footer">
                <x-secondary-button type="button" x-on:click="$dispatch('close')" class="w-full sm:w-auto">Batal</x-secondary-button>
                <x-danger-button type="submit" wire:loading.attr="disabled" wire:target="deleteUser" class="w-full sm:w-auto">
                    <span wire:loading.remove wire:target="deleteUser">Hapus akun</span>
                    <span wire:loading wire:target="deleteUser">Menghapus…</span>
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
