<div>
    <div class="ui-page max-w-5xl">
        <nav aria-label="Breadcrumb" class="mb-3">
            <a href="{{ route('offers.index') }}" wire:navigate class="ui-text-action -ml-2">
                <span aria-hidden="true">←</span>
                Penawaran
            </a>
        </nav>

        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Buat Penawaran</h1>
                <p class="ui-page-description">Catat penawaran jasa penilaian, pihak terkait, nilai fee, dan posisi penawaran.</p>
            </div>
        </header>

        <form wire:submit="save">
            @include('livewire.offers.partials.form-fields', ['formId' => 'create-offer'])

            <div class="mt-8 flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('offers.index') }}" wire:navigate class="ui-btn ui-btn-secondary w-full sm:w-auto">Batal</a>
                <x-primary-button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="w-full sm:w-auto"
                >
                    <span wire:loading.remove wire:target="save">Simpan penawaran</span>
                    <span wire:loading wire:target="save">Menyimpan…</span>
                </x-primary-button>
            </div>
        </form>
    </div>
</div>
