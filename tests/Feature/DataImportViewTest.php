<?php

namespace Tests\Feature;

use App\Livewire\Imports\DataImport;
use App\Models\Branch;
use App\Models\ImportStaging;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DataImportViewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create([
            'name' => 'Import Operator',
            'role' => 'sysadmin',
            'active' => true,
        ]);
        $this->user->syncRoles(['sysadmin']);
    }

    public function test_authorized_user_can_render_the_import_route_with_active_branch_options_and_expected_actions(): void
    {
        $central = $this->createBranch('PST', 'Pusat');
        $jakarta = $this->createBranch('JKT', 'Jakarta');
        $inactive = $this->createBranch('SBY', 'Surabaya', false);

        $this->actingAs($this->user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('Impor Data')
            ->assertSee('Unggah berkas')
            ->assertSeeHtml('wire:click="downloadTemplate"')
            ->assertSeeHtml('wire:submit="uploadFile"')
            ->assertSeeHtml('wire:model="default_branch_code"')
            ->assertSeeHtml('wire:model="upload_file"');

        Livewire::actingAs($this->user)
            ->test(DataImport::class)
            ->assertSet('default_branch_code', 'PST')
            ->assertSet('currentBatchId', null)
            ->assertViewHas('branches', fn ($branches): bool => $branches->contains(fn (Branch $branch): bool => $branch->is($central))
                && $branches->contains(fn (Branch $branch): bool => $branch->is($jakarta))
                && ! $branches->contains(fn (Branch $branch): bool => $branch->is($inactive))
                && $branches->every(fn (Branch $branch): bool => $branch->active))
            ->assertSee('Pusat (PST)')
            ->assertSee('Jakarta (JKT)')
            ->assertDontSee('Surabaya (SBY)');
    }

    public function test_upload_requires_a_csv_or_text_file_and_a_default_branch_code(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->user)
            ->test(DataImport::class)
            ->set('default_branch_code', '')
            ->call('uploadFile')
            ->assertHasErrors([
                'upload_file' => 'required',
                'default_branch_code' => 'required',
            ]);

        Livewire::actingAs($this->user)
            ->test(DataImport::class)
            ->set('upload_file', UploadedFile::fake()->createWithContent('production.pdf', 'not a csv'))
            ->call('uploadFile')
            ->assertHasErrors(['upload_file' => 'mimes']);
    }

    public function test_a_valid_csv_upload_is_parsed_into_an_isolated_staging_batch(): void
    {
        Storage::fake('local');
        $this->createBranch('JKT', 'Jakarta');

        $csv = implode("\n", [
            'offer_no,contract_date,branch_code,debtor_name,client_name,report_user_name,fee,ta,status,report_no,report_date,resume_value,report_value,sent_date,courier,tracking_no,received_date,recipient_name',
            'PNW/2026/1001,2026-08-01,,PT Alpha,PT Bank Satu,Divisi Kredit,1250000,250000,SELESAI,LAP/2026/1001,2026-08-05,5000000,4750000,2026-08-06,JNE,RESI-1001,2026-08-07,Budi',
            'PNW/2026/1002,2026-08-02,SBY,PT Beta,PT Bank Dua,,2000000,0,PENGERJAAN,,,,3000000,,,,,',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(DataImport::class)
            ->set('default_branch_code', 'JKT')
            ->set('upload_file', UploadedFile::fake()->createWithContent('production.csv', $csv))
            ->call('uploadFile')
            ->assertHasNoErrors()
            ->assertSet('upload_file', null)
            ->assertSee('Berkas CSV berhasil diparse dan disimpan ke staging table.');

        $batchId = $component->get('currentBatchId');

        $this->assertIsString($batchId);
        $this->assertNotSame('', $batchId);
        $this->assertDatabaseCount('import_stagings', 2);
        $this->assertDatabaseHas('import_stagings', [
            'batch_id' => $batchId,
            'offer_no' => 'PNW/2026/1001',
            'branch_code' => 'JKT',
            'debtor_name' => 'PT Alpha',
            'client_name' => 'PT Bank Satu',
            'fee' => 1_250_000,
            'ta' => 250_000,
            'status' => 'SELESAI',
            'report_no' => 'LAP/2026/1001',
            'is_processed' => false,
        ]);
        $this->assertDatabaseHas('import_stagings', [
            'batch_id' => $batchId,
            'offer_no' => 'PNW/2026/1002',
            'branch_code' => 'SBY',
            'status' => 'PENGERJAAN',
            'is_processed' => false,
        ]);
    }

    public function test_staging_preview_exposes_counts_actions_statuses_and_semantic_table_markup(): void
    {
        $batchId = 'batch-preview';
        $ready = $this->createStaging([
            'batch_id' => $batchId,
            'offer_no' => 'PNW/2026/2001',
            'debtor_name' => 'PT Gamma',
            'client_name' => 'PT Bank Tiga',
            'is_processed' => false,
        ]);
        $processed = $this->createStaging([
            'batch_id' => $batchId,
            'offer_no' => 'PNW/2026/2002',
            'debtor_name' => 'PT Delta',
            'client_name' => 'PT Bank Empat',
            'is_processed' => true,
            'error_message' => 'Catatan hasil proses',
        ]);
        $this->createStaging([
            'batch_id' => 'batch-lain',
            'offer_no' => 'PNW/2026/9999',
        ]);

        Livewire::actingAs($this->user)
            ->test(DataImport::class)
            ->set('currentBatchId', $batchId)
            ->assertViewHas('totalStaging', 2)
            ->assertViewHas('unprocessedCount', 1)
            ->assertViewHas('stagingItems', fn ($items): bool => $items->total() === 2
                && $items->contains(fn (ImportStaging $item): bool => $item->is($ready))
                && $items->contains(fn (ImportStaging $item): bool => $item->is($processed)))
            ->assertSee('Tinjau data staging')
            ->assertSee('Siap diproses')
            ->assertSee('Gagal')
            ->assertSee('Catatan hasil proses')
            ->assertSeeHtml('wire:click="clearStaging"')
            ->assertSeeHtml('wire:click="processBatch"')
            ->assertSeeHtml('<table')
            ->assertSeeHtml('<caption')
            ->assertSeeHtml('<th scope="col">Nomor penawaran</th>')
            ->assertSeeHtml('<th scope="col">Cabang</th>')
            ->assertSeeHtml('<th scope="col">Debitur dan klien</th>')
            ->assertSeeHtml('<th scope="col" class="text-right">Fee</th>')
            ->assertSeeHtml('<th scope="col">Status proses</th>')
            ->assertSeeHtml('wire:key="staging-row-'.$ready->id.'"')
            ->assertSeeHtml('wire:key="staging-row-'.$processed->id.'"');
    }

    public function test_clear_staging_deletes_only_the_selected_batch_and_resets_the_preview(): void
    {
        $selected = $this->createStaging([
            'batch_id' => 'batch-selected',
            'offer_no' => 'PNW/2026/3001',
        ]);
        $other = $this->createStaging([
            'batch_id' => 'batch-preserved',
            'offer_no' => 'PNW/2026/3002',
        ]);

        Livewire::actingAs($this->user)
            ->test(DataImport::class)
            ->set('currentBatchId', 'batch-selected')
            ->call('clearStaging')
            ->assertHasNoErrors()
            ->assertSet('currentBatchId', null)
            ->assertSee('Data staging berhasil dibersihkan.');

        $this->assertDatabaseMissing('import_stagings', ['id' => $selected->id]);
        $this->assertDatabaseHas('import_stagings', ['id' => $other->id]);
    }

    public function test_processing_without_a_selected_batch_returns_an_error_without_writing_production_data(): void
    {
        Livewire::actingAs($this->user)
            ->test(DataImport::class)
            ->call('processBatch')
            ->assertHasNoErrors()
            ->assertSet('currentBatchId', null)
            ->assertSee('Tidak ada batch data staging yang dipilih.');

        $this->assertDatabaseCount('offers', 0);
        $this->assertDatabaseCount('work_orders', 0);
    }

    private function createBranch(string $code, string $name, bool $active = true): Branch
    {
        return Branch::create([
            'code' => $code,
            'name' => $name,
            'active' => $active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createStaging(array $attributes = []): ImportStaging
    {
        return ImportStaging::create(array_merge([
            'batch_id' => 'batch-default',
            'branch_code' => 'PST',
            'offer_no' => 'PNW/2026/0001',
            'contract_date' => '2026-08-01',
            'debtor_name' => 'PT Debitur Contoh',
            'client_name' => 'PT Klien Contoh',
            'fee' => 1_000_000,
            'ta' => 100_000,
            'status' => 'SELESAI',
            'report_no' => 'LAP/2026/0001',
            'report_date' => '2026-08-05',
            'resume_value' => 5_000_000,
            'report_value' => 4_900_000,
            'is_processed' => false,
        ], $attributes));
    }
}
