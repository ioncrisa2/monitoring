<?php
/**
 * Screenshot automation v2 — HJAR Flows
 * Menggunakan Chrome headless + route /_dev/autologin untuk session nyata.
 */

$chrome = "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
$base   = "http://localhost:8000";
$outDir = realpath(__DIR__ . "\\..\\screenshoot");

if (!file_exists($chrome)) { die("Chrome tidak ditemukan.\n"); }
if (!$outDir) { mkdir(__DIR__ . "\\..\\screenshoot", 0755, true); $outDir = realpath(__DIR__ . "\\..\\screenshoot"); }

$ping = @file_get_contents($base . "/");
if ($ping === false) { die("Server tidak berjalan di {$base}\n"); }
echo "Server OK\n";

// Users: [userId, role, label]
$users = [
    [1, "sysadmin",  "admin@kjpp.com"],
    [2, "supervisor","supervisor@kjpp.com"],
    [3, "admin",     "operator.jkt@kjpp.com"],
];

$pages = [
    1 => [ // sysadmin
        ["28-sysadmin-dashboard.png",                "dashboard"],
        ["29-sysadmin-penawaran-list.png",           "offers"],
        ["30-sysadmin-penawaran-tambah.png",         "offers/create"],
        ["31-sysadmin-pekerjaan-list.png",           "work-orders"],
        ["32-sysadmin-laporan-produksi.png",         "reports/production"],
        ["33-sysadmin-impor-data.png",               "imports"],
        ["34-sysadmin-audit-logs.png",               "audit-logs"],
        ["35-sysadmin-master-cabang.png",            "master/branches"],
        ["36-sysadmin-master-pengguna.png",          "master/users"],
        ["37-sysadmin-master-role.png",              "master/roles-permissions"],
        ["38-sysadmin-master-klien.png",             "master/organizations"],
        ["39-sysadmin-master-debitur.png",           "master/debtors"],
        ["40-sysadmin-master-dokumen-penawaran.png", "master/offer-documents"],
        ["41-sysadmin-profil-akun.png",              "profile"],
    ],
    2 => [ // supervisor
        ["42-supervisor-dashboard.png",                "dashboard"],
        ["43-supervisor-penawaran-list.png",           "offers"],
        ["44-supervisor-pekerjaan-list.png",           "work-orders"],
        ["45-supervisor-laporan-produksi.png",         "reports/production"],
        ["46-supervisor-master-dokumen-penawaran.png", "master/offer-documents"],
        ["47-supervisor-profil-akun.png",              "profile"],
    ],
    3 => [ // admin
        ["48-admin-dashboard.png",        "dashboard"],
        ["49-admin-penawaran-list.png",   "offers"],
        ["50-admin-penawaran-tambah.png", "offers/create"],
        ["51-admin-pekerjaan-list.png",   "work-orders"],
        ["52-admin-profil-akun.png",      "profile"],
    ],
];

// Test autologin route
$testUrl = $base . "/_dev/autologin/1/" . base64_encode("dashboard");
$ch = curl_init($testUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 5]);
$r = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code !== 302 && $code !== 200) {
    die("Autologin route tidak berfungsi (HTTP {$code}). Pastikan server sedang berjalan.\n");
}
echo "Autologin route OK (HTTP {$code})\n\n";

function screenshot(string $chrome, string $url, string $outFile, bool $wait = true): bool
{
    $profileDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("hjarchromeprofile_");
    @mkdir($profileDir, 0755, true);

    $cmd = sprintf(
        '"%s" --headless=new --disable-gpu --no-sandbox --disable-dev-shm-usage '
        . '--window-size=1440,900 --screenshot="%s" '
        . '--virtual-time-budget=8000 '
        . '--user-data-dir="%s" '
        . '"%s" 2>&1',
        $chrome, $outFile, $profileDir, $url
    );

    exec($cmd, $out, $code);
    return file_exists($outFile) && filesize($outFile) > 3000;
}

// Halaman publik
echo "[Publik]\n";
$ok = screenshot($chrome, $base . "/", "{$outDir}\\27-publik-welcome.png");
echo ($ok ? "  ✓  27-publik-welcome.png\n" : "  ✗  27-publik-welcome.png GAGAL\n");

// Per role: Chrome mengikuti autologin URL yang set session cookie nyata
foreach ($users as [$userId, $role, $email]) {
    echo "\n[{$role}: {$email}]\n";

    foreach ($pages[$userId] as [$file, $path]) {
        $outFile   = "{$outDir}\\{$file}";
        $encoded   = base64_encode($path);
        $loginUrl  = $base . "/_dev/autologin/{$userId}/" . $encoded;

        echo "  → {$path} ... ";
        flush();

        $ok = screenshot($chrome, $loginUrl, $outFile);
        if ($ok) {
            echo "✓ (" . round(filesize($outFile)/1024) . " KB)\n";
        } else {
            echo "✗ GAGAL\n";
        }
    }
}

echo "\n✅ Selesai! File tersimpan di: {$outDir}\n";
