<?php
$chrome = "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
$base   = "http://localhost:8000";
$outDir = realpath(__DIR__ . "\\..\\screenshoot");

function screenshot(string $chrome, string $url, string $outFile, int $timeout = 6000): bool
{
    $profileDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("hjarchromeprofile_");
    @mkdir($profileDir, 0755, true);
    $cmd = sprintf(
        '"%s" --headless=new --disable-gpu --no-sandbox --disable-dev-shm-usage --window-size=1440,900 --screenshot="%s" --virtual-time-budget=%d --user-data-dir="%s" "%s" 2>&1',
        $chrome, $outFile, $timeout, $profileDir, $url
    );
    exec($cmd, $out, $code);
    return file_exists($outFile) && filesize($outFile) > 3000;
}

echo "[Publik]\n";
$publicPages = [
    ["00-welcome.png", "/"],
    ["01-login.png", "/login"],
    ["02-register.png", "/login"], // Use login for register since no register route
    ["03-forgot-password.png", "/forgot-password"],
];
foreach ($publicPages as [$file, $path]) {
    echo "  → {$path} ... ";
    $ok = screenshot($chrome, $base . $path, "{$outDir}\\{$file}");
    echo ($ok ? "✓\n" : "✗\n");
}

$sysadminPages = [
    ["04-dashboard.png", "dashboard"],
    ["05-penawaran.png", "offers"],
    ["06-pekerjaan-index.png", "work-orders"],
    ["07-pekerjaan-detail.png", "work-orders/1"], // assuming order 1 exists
    ["08-laporan-produksi.png", "reports/production"],
    ["09-impor-data.png", "imports"],
    ["10-master-cabang.png", "master/branches"],
    ["11-master-pengguna-sistem.png", "master/users"],
    ["12-master-role-hak-akses.png", "master/roles-permissions"],
    ["13-master-pemberi-tugas-klien.png", "master/organizations"],
    ["14-master-debitur.png", "master/debtors"],
    ["15-audit-trail-logs.png", "audit-logs"],
    ["16-profil-akun.png", "profile"],
    ["17-penawaran-tambah.png", "offers/create"],
    ["18-master-cabang-tambah.png", "master/branches"],
    ["19-master-pengguna-tambah.png", "master/users"],
    ["20-master-role-tambah.png", "master/roles-permissions"],
    ["21-master-klien-tambah.png", "master/organizations"],
    ["22-master-debitur-tambah.png", "master/debtors"],
    ["23-pekerjaan-assign-pic.png", "work-orders/1"],
    ["24-pekerjaan-tambah-aset.png", "work-orders/1"],
    ["25-pekerjaan-tambah-laporan.png", "work-orders/1"],
    ["26-pekerjaan-tambah-dokumen.png", "work-orders/1"],
];

echo "\n[sysadmin: admin@kjpp.com]\n";
$userId = 1;
foreach ($sysadminPages as [$file, $path]) {
    $outFile   = "{$outDir}\\{$file}";
    $loginUrl  = $base . "/_dev/autologin/{$userId}/" . base64_encode($path);
    echo "  → {$path} ({$file}) ... ";
    $ok = screenshot($chrome, $loginUrl, $outFile);
    echo ($ok ? "✓ (" . round(filesize($outFile)/1024) . " KB)\n" : "✗ GAGAL\n");
}
echo "\n✅ Selesai!\n";
