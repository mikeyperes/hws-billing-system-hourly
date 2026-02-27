<?php
/**
 * HWS Debug Page — comprehensive server diagnostics.
 * URL: https://billing.hexawebsystems.com/debug.php
 *
 * Checks: PHP version, extensions, database connection, .env config,
 * file permissions, Laravel log, server info, Composer packages,
 * external connectivity (Stripe, Google, Brevo).
 */
$start = microtime(true);
?>
<!DOCTYPE html>
<html>
<head>
    <title>HWS Debug</title>
    <style>
        body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; padding: 20px; font-size: 13px; }
        h1 { color: #00d4ff; border-bottom: 2px solid #00d4ff; padding-bottom: 10px; }
        h2 { color: #ffa500; margin-top: 30px; border-bottom: 1px solid #333; padding-bottom: 5px; }
        .pass { color: #00ff88; font-weight: bold; }
        .fail { color: #ff4444; font-weight: bold; }
        .warn { color: #ffaa00; font-weight: bold; }
        .box { background: #16213e; border: 1px solid #333; border-radius: 8px; padding: 15px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 6px 12px; border-bottom: 1px solid #333; text-align: left; }
        th { color: #00d4ff; }
        .section { margin-bottom: 30px; }
        a { color: #00d4ff; }
    </style>
</head>
<body>
<h1>HWS Server Debug — <?= date('Y-m-d H:i:s T') ?></h1>

<?php
// ============================================================
// 1. PHP ENVIRONMENT
// ============================================================
?>
<div class="section">
<h2>1. PHP Environment</h2>
<div class="box">
<table>
<tr><th>Setting</th><th>Value</th><th>Status</th></tr>
<tr>
    <td>PHP Version</td>
    <td><?= PHP_VERSION ?></td>
    <td class="<?= version_compare(PHP_VERSION, '8.2.0', '>=') ? 'pass' : 'fail' ?>"><?= version_compare(PHP_VERSION, '8.2.0', '>=') ? 'OK (≥8.2)' : 'NEEDS 8.2+' ?></td>
</tr>
<tr>
    <td>SAPI</td>
    <td><?= php_sapi_name() ?></td>
    <td>—</td>
</tr>
<tr>
    <td>PHP Binary</td>
    <td><?= PHP_BINARY ?></td>
    <td>—</td>
</tr>
<tr>
    <td>php.ini Location</td>
    <td><?= php_ini_loaded_file() ?: 'NONE' ?></td>
    <td class="<?= php_ini_loaded_file() ? 'pass' : 'fail' ?>"><?= php_ini_loaded_file() ? 'OK' : 'MISSING' ?></td>
</tr>
<tr>
    <td>Additional INI Files</td>
    <td style="word-break:break-all;"><?= php_ini_scanned_files() ?: 'NONE' ?></td>
    <td>—</td>
</tr>
<tr>
    <td>Max Execution Time</td>
    <td><?= ini_get('max_execution_time') ?>s</td>
    <td>—</td>
</tr>
<tr>
    <td>Memory Limit</td>
    <td><?= ini_get('memory_limit') ?></td>
    <td>—</td>
</tr>
<tr>
    <td>Upload Max Filesize</td>
    <td><?= ini_get('upload_max_filesize') ?></td>
    <td>—</td>
</tr>
<tr>
    <td>Post Max Size</td>
    <td><?= ini_get('post_max_size') ?></td>
    <td>—</td>
</tr>
<tr>
    <td>Display Errors</td>
    <td><?= ini_get('display_errors') ? 'On' : 'Off' ?></td>
    <td>—</td>
</tr>
<tr>
    <td>Error Reporting</td>
    <td><?= ini_get('error_reporting') ?></td>
    <td>—</td>
</tr>
<tr>
    <td>Timezone</td>
    <td><?= date_default_timezone_get() ?></td>
    <td>—</td>
</tr>
</table>
</div>
</div>

<?php
// ============================================================
// 2. REQUIRED PHP EXTENSIONS
// ============================================================
$required_ext = [
    'pdo_mysql' => 'Database (MySQL via PDO) — CRITICAL',
    'mysqli' => 'Database (MySQLi) — recommended',
    'mysqlnd' => 'MySQL Native Driver — required by pdo_mysql',
    'PDO' => 'PHP Data Objects — base driver',
    'openssl' => 'SSL/TLS — Stripe API, SMTP',
    'curl' => 'HTTP client — Stripe API, Google API',
    'mbstring' => 'Multibyte strings — Laravel requirement',
    'json' => 'JSON — Laravel requirement',
    'xml' => 'XML — Laravel requirement',
    'dom' => 'DOM — Laravel requirement',
    'fileinfo' => 'File info — Laravel requirement',
    'tokenizer' => 'Tokenizer — Laravel requirement',
    'bcmath' => 'BC Math — Laravel requirement',
    'ctype' => 'Character type — Laravel requirement',
    'filter' => 'Filter — Laravel requirement',
    'hash' => 'Hashing — Laravel requirement',
    'session' => 'Sessions — Laravel requirement',
    'zip' => 'Zip — Composer',
    'gd' => 'GD — image processing',
    'intl' => 'Internationalization',
];
?>
<div class="section">
<h2>2. PHP Extensions</h2>
<div class="box">
<table>
<tr><th>Extension</th><th>Purpose</th><th>Status</th></tr>
<?php foreach ($required_ext as $ext => $purpose): ?>
<tr>
    <td><?= $ext ?></td>
    <td><?= $purpose ?></td>
    <td class="<?= extension_loaded($ext) ? 'pass' : 'fail' ?>"><?= extension_loaded($ext) ? 'LOADED' : 'MISSING' ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3 style="color:#00d4ff; margin-top:15px;">PDO Available Drivers</h3>
<p><?= class_exists('PDO') ? implode(', ', PDO::getAvailableDrivers()) ?: '<span class="fail">NONE — pdo_mysql not installed</span>' : '<span class="fail">PDO NOT LOADED</span>' ?></p>

<h3 style="color:#00d4ff; margin-top:15px;">All Loaded Extensions (<?= count(get_loaded_extensions()) ?>)</h3>
<p style="word-wrap:break-word;"><?= implode(', ', get_loaded_extensions()) ?></p>
</div>
</div>

<?php
// ============================================================
// 3. DATABASE CONNECTION
// ============================================================
?>
<div class="section">
<h2>3. Database Connection</h2>
<div class="box">
<?php
$envFile = dirname(__DIR__) . '/.env';
$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = '';
$dbUser = '';
$dbPass = '';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = array_map('trim', explode('=', $line, 2));
        $val = trim($val, '"\'');
        if ($key === 'DB_HOST') $dbHost = $val;
        if ($key === 'DB_PORT') $dbPort = $val;
        if ($key === 'DB_DATABASE') $dbName = $val;
        if ($key === 'DB_USERNAME') $dbUser = $val;
        if ($key === 'DB_PASSWORD') $dbPass = $val;
    }
}
?>
<table>
<tr><td>DB Host</td><td><?= $dbHost ?></td></tr>
<tr><td>DB Port</td><td><?= $dbPort ?></td></tr>
<tr><td>DB Name</td><td><?= $dbName ?></td></tr>
<tr><td>DB User</td><td><?= $dbUser ?></td></tr>
<tr><td>DB Pass</td><td><?= $dbPass ? str_repeat('*', strlen($dbPass)) . ' (' . strlen($dbPass) . ' chars)' : '<span class="fail">EMPTY</span>' ?></td></tr>
</table>

<?php if (extension_loaded('pdo_mysql')): ?>
    <?php
    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_TIMEOUT => 5]);
        echo '<p class="pass">✅ DATABASE CONNECTION: SUCCESS</p>';

        // Show tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo '<p>Tables (' . count($tables) . '): ' . implode(', ', $tables) . '</p>';

        // Show users
        $users = $pdo->query("SELECT id, name, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
        if ($users) {
            echo '<h3 style="color:#00d4ff;">Users</h3><table><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>';
            foreach ($users as $u) {
                echo "<tr><td>{$u['id']}</td><td>{$u['name']}</td><td>{$u['email']}</td><td>{$u['role']}</td></tr>";
            }
            echo '</table>';
        } else {
            echo '<p class="warn">⚠️ No users found — run: php artisan db:seed --class=HwsSeeder</p>';
        }

        // Row counts for all HWS tables
        $countTables = ['clients', 'employees', 'invoices', 'invoice_line_items', 'email_templates', 'settings', 'lists', 'scan_logs'];
        echo '<h3 style="color:#00d4ff;">Row Counts</h3><table><tr><th>Table</th><th>Rows</th></tr>';
        foreach ($countTables as $t) {
            if (in_array($t, $tables)) {
                $count = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
                echo "<tr><td>{$t}</td><td>{$count}</td></tr>";
            }
        }
        echo '</table>';

    } catch (Exception $e) {
        echo '<p class="fail">❌ DATABASE CONNECTION: FAILED</p>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    ?>
<?php else: ?>
    <p class="fail">❌ CANNOT TEST — pdo_mysql extension is NOT loaded</p>
    <p>The web PHP binary is: <code><?= PHP_BINARY ?></code></p>
    <p>php.ini: <code><?= php_ini_loaded_file() ?></code></p>
    <p>This server likely uses CloudLinux alt-php. Try:</p>
    <pre>yum install alt-php85-pdo_mysql -y
/usr/local/lsws/bin/lswsctrl restart</pre>
<?php endif; ?>
</div>
</div>

<?php
// ============================================================
// 4. ENVIRONMENT FILE
// ============================================================
?>
<div class="section">
<h2>4. Environment File (.env)</h2>
<div class="box">
<?php
if (file_exists($envFile)) {
    echo '<p class="pass">✅ .env file exists (' . filesize($envFile) . ' bytes)</p>';
    $envContent = file_get_contents($envFile);
    // Mask sensitive values
    $masked = preg_replace('/(PASSWORD|KEY|SECRET)=(.+)/i', '$1=********', $envContent);
    echo '<pre style="max-height:300px; overflow-y:auto; font-size:11px;">' . htmlspecialchars($masked) . '</pre>';
} else {
    echo '<p class="fail">❌ .env file NOT FOUND at ' . $envFile . '</p>';
    echo '<p>Run: <code>cp .env.example .env && php artisan key:generate</code></p>';
}
?>
</div>
</div>

<?php
// ============================================================
// 5. FILE PERMISSIONS
// ============================================================
$baseDir = dirname(__DIR__);
$checkPaths = [
    'storage' => $baseDir . '/storage',
    'storage/logs' => $baseDir . '/storage/logs',
    'storage/framework' => $baseDir . '/storage/framework',
    'storage/framework/sessions' => $baseDir . '/storage/framework/sessions',
    'storage/framework/views' => $baseDir . '/storage/framework/views',
    'storage/framework/cache' => $baseDir . '/storage/framework/cache',
    'storage/app' => $baseDir . '/storage/app',
    'bootstrap/cache' => $baseDir . '/bootstrap/cache',
    'public' => $baseDir . '/public',
    '.env' => $envFile,
    'vendor' => $baseDir . '/vendor',
    'artisan' => $baseDir . '/artisan',
];
?>
<div class="section">
<h2>5. File Permissions & Paths</h2>
<div class="box">
<table>
<tr><th>Path</th><th>Exists</th><th>Writable</th><th>Owner</th><th>Perms</th></tr>
<?php foreach ($checkPaths as $label => $path): ?>
<tr>
    <td><?= $label ?></td>
    <td class="<?= file_exists($path) ? 'pass' : 'fail' ?>"><?= file_exists($path) ? 'YES' : 'NO' ?></td>
    <td class="<?= is_writable($path) ? 'pass' : (file_exists($path) ? 'fail' : '') ?>"><?= file_exists($path) ? (is_writable($path) ? 'YES' : 'NO') : '—' ?></td>
    <td><?php if (file_exists($path) && function_exists('posix_getpwuid')) { $s = stat($path); $pw = posix_getpwuid($s['uid']); echo $pw['name'] ?? $s['uid']; } else echo '—'; ?></td>
    <td><?= file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : '—' ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3 style="color:#00d4ff; margin-top:15px;">Process User</h3>
<p><?php if (function_exists('posix_geteuid')) { $pu = posix_getpwuid(posix_geteuid()); echo $pu['name'] . ' (uid: ' . posix_geteuid() . ', gid: ' . posix_getegid() . ')'; } else { echo get_current_user(); } ?></p>

<h3 style="color:#00d4ff; margin-top:15px;">Google Credentials File</h3>
<?php
$googleCred = $dbHost; // reset
// Re-parse env for google creds path
$googleCredPath = '';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, 'GOOGLE_CREDENTIALS_PATH=') === 0) {
            $googleCredPath = trim(explode('=', $line, 2)[1], '"\'');
        }
    }
}
if ($googleCredPath) {
    echo '<p>Path: <code>' . $googleCredPath . '</code></p>';
    echo '<p class="' . (file_exists($googleCredPath) ? 'pass' : 'fail') . '">' . (file_exists($googleCredPath) ? '✅ File exists' : '❌ File NOT FOUND') . '</p>';
} else {
    echo '<p class="warn">⚠️ GOOGLE_CREDENTIALS_PATH not set in .env</p>';
}
?>
</div>
</div>

<?php
// ============================================================
// 6. LARAVEL LOG (last 40 lines)
// ============================================================
?>
<div class="section">
<h2>6. Laravel Log (last 40 lines)</h2>
<div class="box">
<?php
$logFile = $baseDir . '/storage/logs/laravel.log';
if (file_exists($logFile)) {
    $size = filesize($logFile);
    echo '<p>Log size: ' . round($size / 1024, 1) . ' KB | <a href="?clear_log=1" style="color:#ff4444;">Clear Log</a></p>';
    if (isset($_GET['clear_log'])) {
        file_put_contents($logFile, '');
        echo '<p class="pass">Log cleared.</p>';
    }
    $lines = file($logFile);
    $last = array_slice($lines, -40);
    echo '<pre style="max-height:400px; overflow-y:auto; font-size:11px;">' . htmlspecialchars(implode('', $last)) . '</pre>';
} else {
    echo '<p class="warn">No log file at ' . $logFile . '</p>';
}
?>
</div>
</div>

<?php
// ============================================================
// 7. SERVER INFO
// ============================================================
?>
<div class="section">
<h2>7. Server Info</h2>
<div class="box">
<table>
<tr><td>Hostname</td><td><?= gethostname() ?></td></tr>
<tr><td>OS</td><td><?= php_uname() ?></td></tr>
<tr><td>Server Software</td><td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td></tr>
<tr><td>Document Root</td><td><?= $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' ?></td></tr>
<tr><td>Script Filename</td><td><?= $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown' ?></td></tr>
<tr><td>Server Protocol</td><td><?= $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown' ?></td></tr>
<tr><td>HTTPS</td><td><?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? '<span class="pass">YES</span>' : '<span class="warn">NO</span>' ?></td></tr>
<tr><td>Disk Free</td><td><?= round(disk_free_space($baseDir) / 1073741824, 2) ?> GB</td></tr>
<tr><td>Disk Total</td><td><?= round(disk_total_space($baseDir) / 1073741824, 2) ?> GB</td></tr>
<tr><td>Server Time</td><td><?= date('Y-m-d H:i:s T') ?></td></tr>
</table>
</div>
</div>

<?php
// ============================================================
// 8. COMPOSER PACKAGES
// ============================================================
?>
<div class="section">
<h2>8. Composer Packages</h2>
<div class="box">
<?php
$composerLock = $baseDir . '/composer.lock';
if (file_exists($composerLock)) {
    $lock = json_decode(file_get_contents($composerLock), true);
    $packages = $lock['packages'] ?? [];
    $check = ['laravel/framework', 'stripe/stripe-php', 'google/apiclient', 'phpmailer/phpmailer', 'laravel/breeze'];
    echo '<table><tr><th>Package</th><th>Version</th><th>Status</th></tr>';
    foreach ($check as $pkg) {
        $found = null;
        foreach ($packages as $p) {
            if ($p['name'] === $pkg) { $found = $p['version']; break; }
        }
        echo '<tr><td>' . $pkg . '</td>';
        echo '<td>' . ($found ?? '—') . '</td>';
        echo '<td class="' . ($found ? 'pass' : 'fail') . '">' . ($found ? 'INSTALLED' : 'MISSING') . '</td></tr>';
    }
    echo '</table>';
    echo '<p style="margin-top:10px;">Total packages: ' . count($packages) . '</p>';
} else {
    echo '<p class="fail">composer.lock not found — run: <code>composer install</code></p>';
}
?>
</div>
</div>

<?php
// ============================================================
// 9. EXTERNAL CONNECTIVITY
// ============================================================
?>
<div class="section">
<h2>9. External Connectivity</h2>
<div class="box">
<?php
$endpoints = [
    'Stripe API' => 'https://api.stripe.com/v1',
    'Google Sheets API' => 'https://sheets.googleapis.com/',
    'Brevo SMTP' => 'smtp-relay.brevo.com:587',
];
echo '<table><tr><th>Service</th><th>Endpoint</th><th>Status</th></tr>';
foreach ($endpoints as $name => $url) {
    if (strpos($url, 'https://') === 0) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $ok = $code > 0;
        echo "<tr><td>{$name}</td><td>{$url}</td><td class='" . ($ok ? 'pass' : 'fail') . "'>" . ($ok ? "HTTP {$code}" : "FAILED: {$err}") . "</td></tr>";
    } else {
        $parts = explode(':', $url);
        $fp = @fsockopen($parts[0], $parts[1], $errno, $errstr, 5);
        $ok = (bool)$fp;
        if ($fp) fclose($fp);
        echo "<tr><td>{$name}</td><td>{$url}</td><td class='" . ($ok ? 'pass' : 'fail') . "'>" . ($ok ? 'REACHABLE' : "FAILED: {$errstr}") . "</td></tr>";
    }
}
echo '</table>';
?>
</div>
</div>

<p style="color:#666; margin-top:30px;">Page generated in <?= round((microtime(true) - $start) * 1000, 1) ?>ms | <a href="?phpinfo=1">Full phpinfo()</a></p>
<?php if (isset($_GET['phpinfo'])) { phpinfo(); } ?>
</body>
</html>
