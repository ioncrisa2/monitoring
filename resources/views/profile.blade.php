<x-app-layout>
    <div class="ui-page max-w-4xl space-y-8">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Profil</h1>
                <p class="ui-page-description">Kelola identitas akun, password, dan pengaturan akun pribadi Anda.</p>
            </div>
        </header>

        <livewire:profile.update-profile-information-form />

        <div class="border-t border-line pt-8">
            <livewire:profile.update-password-form />
        </div>

        <div class="border-t border-line pt-8">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</x-app-layout>
