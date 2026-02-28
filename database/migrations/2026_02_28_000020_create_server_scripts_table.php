<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Create server_scripts table for predefined maintenance commands.
 * Scripts can be run against any WHM server.
 * Danger levels: safe, caution, destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_scripts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->text('command');
            $table->string('category')->default('general');
            $table->string('danger_level')->default('safe');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('server_scripts')->insert([
            [
                'name' => 'Disk Usage Summary',
                'description' => 'Show disk usage breakdown by partition.',
                'command' => 'df -h',
                'category' => 'diagnostics',
                'danger_level' => 'safe',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Memory Usage',
                'description' => 'Show RAM usage (total, used, free, cached).',
                'command' => 'free -m',
                'category' => 'diagnostics',
                'danger_level' => 'safe',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Server Uptime & Load',
                'description' => 'Show uptime and load averages.',
                'command' => 'uptime && echo "---" && cat /proc/loadavg',
                'category' => 'diagnostics',
                'danger_level' => 'safe',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'OS & Kernel Info',
                'description' => 'Show operating system, kernel version, and release info.',
                'command' => 'cat /etc/redhat-release 2>/dev/null || cat /etc/os-release 2>/dev/null; echo "---"; uname -r; echo "---"; uname -a',
                'category' => 'diagnostics',
                'danger_level' => 'safe',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'PHP Versions Installed',
                'description' => 'List all installed PHP versions.',
                'command' => 'ls /opt/cpanel/ea-php*/root/usr/bin/php 2>/dev/null | while read p; do echo "$p: $($p -v 2>/dev/null | head -1)"; done; echo "---"; php -v 2>/dev/null | head -1',
                'category' => 'diagnostics',
                'danger_level' => 'safe',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'MySQL/MariaDB Version',
                'description' => 'Show database server version.',
                'command' => 'mysql --version 2>/dev/null || mariadb --version 2>/dev/null',
                'category' => 'diagnostics',
                'danger_level' => 'safe',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Web Server Version',
                'description' => 'Show Apache/LiteSpeed version.',
                'command' => 'httpd -v 2>/dev/null || /usr/local/lsws/bin/lshttpd -v 2>/dev/null || echo "Web server not detected via standard paths"',
                'category' => 'diagnostics',
                'danger_level' => 'safe',
                'sort_order' => 7,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'CloudLinux Status',
                'description' => 'Check CloudLinux installation and cage FS status.',
                'command' => 'cldetect --check-license 2>/dev/null || echo "CloudLinux not installed"; echo "---"; cagefsctl --list-enabled 2>/dev/null | head -5 || echo "CageFS not available"',
                'category' => 'diagnostics',
                'danger_level' => 'safe',
                'sort_order' => 8,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Find Large Log Files',
                'description' => 'Find log files over 100MB across the server.',
                'command' => 'find /var/log /home/*/logs /usr/local/apache/logs -name "*.log" -o -name "*.log.*" 2>/dev/null | xargs ls -lhS 2>/dev/null | head -20',
                'category' => 'cleanup',
                'danger_level' => 'safe',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Find Backup Files',
                'description' => 'Find backup archives (tar, gz, zip) in home directories.',
                'command' => 'find /home /backup 2>/dev/null -maxdepth 3 \( -name "*.tar.gz" -o -name "*.zip" -o -name "*.sql.gz" -o -name "backup-*" \) -ls 2>/dev/null | sort -k7 -rn | head -20',
                'category' => 'cleanup',
                'danger_level' => 'safe',
                'sort_order' => 11,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Kill Large Log Files',
                'description' => 'Truncate all log files over 100MB. Files are emptied, not deleted.',
                'command' => 'find /var/log /home/*/logs /usr/local/apache/logs -name "*.log" -size +100M 2>/dev/null -exec truncate -s 0 {} \; -print',
                'category' => 'cleanup',
                'danger_level' => 'caution',
                'sort_order' => 12,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Delete Old Backups (30+ days)',
                'description' => 'Delete backup archives older than 30 days. DESTRUCTIVE — cannot be undone.',
                'command' => 'find /backup -name "*.tar.gz" -mtime +30 2>/dev/null -exec rm -v {} \;',
                'category' => 'cleanup',
                'danger_level' => 'destructive',
                'sort_order' => 13,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Top Disk Users',
                'description' => 'Show the 15 largest home directories.',
                'command' => 'du -sh /home/*/ 2>/dev/null | sort -rh | head -15',
                'category' => 'cleanup',
                'danger_level' => 'safe',
                'sort_order' => 14,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Running Services',
                'description' => 'List key running services.',
                'command' => 'systemctl list-units --type=service --state=running 2>/dev/null | grep -E "httpd|lsws|mysql|maria|named|exim|dovecot|cpanel|cpsrvd|sshd|crond|lfd|csf" || service --status-all 2>/dev/null | grep "+"',
                'category' => 'diagnostics',
                'danger_level' => 'safe',
                'sort_order' => 15,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('server_scripts');
    }
};
