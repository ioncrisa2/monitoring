<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup-sqlite';
    protected $description = 'Satu-klik backup file SQLite database ke direktori storage/app/backups';

    public function handle(): int
    {
        $dbPath = database_path('database.sqlite');

        if (!File::exists($dbPath)) {
            $this->error("Database SQLite tidak ditemukan di {$dbPath}.");
            return Command::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $backupFileName = 'database_backup_' . Carbon::now()->format('Ymd_His') . '.sqlite';
        $destination = $backupDir . '/' . $backupFileName;

        if (File::copy($dbPath, $destination)) {
            $this->info("Backup database berhasil dibuat: {$destination}");

            if (class_exists(AuditLogService::class)) {
                AuditLogService::record('BACKUP', "Membuat backup database SQLite: {$backupFileName}");
            }

            return Command::SUCCESS;
        }

        $this->error('Gagal menyalin file database SQLite.');
        return Command::FAILURE;
    }
}
