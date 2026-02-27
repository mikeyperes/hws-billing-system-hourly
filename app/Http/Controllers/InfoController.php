<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * InfoController — displays system information, version, git details, and useful commands.
 * Central reference page for server administration.
 */
class InfoController extends Controller
{
    /**
     * Display the system info page.
     * Collects version, git info, server details, and useful commands.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // ── Git Information ──
        $gitInfo = $this->getGitInfo();

        // ── Server Information ──
        $serverInfo = [
            'php_version'    => PHP_VERSION,                        // Current PHP version
            'php_sapi'       => php_sapi_name(),                    // PHP SAPI (litespeed, cli, etc.)
            'laravel_version' => app()->version(),                  // Laravel framework version
            'hws_version'    => config('hws.version', 'unknown'),   // HWS app version from config
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown', // Web server
            'os'             => php_uname('s') . ' ' . php_uname('r'),    // OS name + version
            'hostname'       => gethostname(),                      // Server hostname
            'document_root'  => base_path(),                        // Laravel base path
            'timezone'       => config('app.timezone', date_default_timezone_get()), // Active timezone
            'debug_mode'     => config('app.debug') ? 'ON' : 'OFF', // Debug mode status
            'environment'    => config('app.env', 'production'),     // App environment
        ];

        // ── Useful Commands ──
        $commands = $this->getCommands();

        // Render the info view
        return view('info.index', [
            'gitInfo'    => $gitInfo,    // Git details
            'serverInfo' => $serverInfo, // Server details
            'commands'   => $commands,   // Command reference
        ]);
    }

    /**
     * Collect git repository information.
     * Runs git commands via shell_exec and parses the output.
     *
     * @return array Git details (branch, commit, remote, status, log)
     */
    private function getGitInfo(): array
    {
        // Base path of the Laravel project
        $basePath = base_path();

        // Check if .git directory exists
        if (!is_dir($basePath . '/.git')) {
            return ['error' => 'Not a git repository — .git directory not found.'];
        }

        return [
            // Current branch name
            'branch' => trim(shell_exec("cd {$basePath} && git rev-parse --abbrev-ref HEAD 2>&1") ?? ''),

            // Latest commit hash (short)
            'commit_short' => trim(shell_exec("cd {$basePath} && git rev-parse --short HEAD 2>&1") ?? ''),

            // Latest commit hash (full)
            'commit_full' => trim(shell_exec("cd {$basePath} && git rev-parse HEAD 2>&1") ?? ''),

            // Latest commit message
            'commit_message' => trim(shell_exec("cd {$basePath} && git log -1 --pretty=%B 2>&1") ?? ''),

            // Latest commit date
            'commit_date' => trim(shell_exec("cd {$basePath} && git log -1 --pretty=%ci 2>&1") ?? ''),

            // Latest commit author
            'commit_author' => trim(shell_exec("cd {$basePath} && git log -1 --pretty=%an 2>&1") ?? ''),

            // Remote URL (origin)
            'remote_url' => trim(shell_exec("cd {$basePath} && git remote get-url origin 2>&1") ?? ''),

            // Total commit count
            'commit_count' => trim(shell_exec("cd {$basePath} && git rev-list --count HEAD 2>&1") ?? ''),

            // Working tree status (clean or modified files)
            'status' => trim(shell_exec("cd {$basePath} && git status --short 2>&1") ?? ''),

            // Last 10 commits (one-line format)
            'recent_commits' => trim(shell_exec("cd {$basePath} && git log --oneline -10 2>&1") ?? ''),

            // Tags
            'tags' => trim(shell_exec("cd {$basePath} && git tag --sort=-v:refname 2>&1") ?? ''),

            // Last fetch time
            'last_fetch' => is_file($basePath . '/.git/FETCH_HEAD')
                ? date('Y-m-d H:i:s', filemtime($basePath . '/.git/FETCH_HEAD'))
                : 'Never',
        ];
    }

    /**
     * Return a structured list of useful server/git commands.
     *
     * @return array Command groups with name, command, and description
     */
    private function getCommands(): array
    {
        return [
            'Git — Deployment' => [
                [
                    'name'    => 'Quick Update (alias)',
                    'command' => 'update-hws-billing',
                    'desc'    => 'Fetches latest from origin and force-resets to origin/main. Set up as bash alias.',
                ],
                [
                    'name'    => 'Pull Latest',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && git pull origin main',
                    'desc'    => 'Standard pull — may fail if local changes conflict.',
                ],
                [
                    'name'    => 'Force Update (reset to remote)',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && git fetch origin && git reset --hard origin/main',
                    'desc'    => 'Overwrites all local changes. Use when pull fails.',
                ],
                [
                    'name'    => 'Check Status',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && git status',
                    'desc'    => 'Shows modified/untracked files.',
                ],
                [
                    'name'    => 'View Recent Commits',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && git log --oneline -20',
                    'desc'    => 'Shows the last 20 commits.',
                ],
            ],
            'Laravel — Maintenance' => [
                [
                    'name'    => 'Clear All Caches',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear',
                    'desc'    => 'Clears config, application, view, and route caches.',
                ],
                [
                    'name'    => 'Run Migrations',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && php artisan migrate --force',
                    'desc'    => 'Runs any new database migrations.',
                ],
                [
                    'name'    => 'Re-seed Database',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && php artisan db:seed --class=HwsSeeder --force',
                    'desc'    => 'Re-runs the HWS seeder (admin user, lists, templates, settings).',
                ],
                [
                    'name'    => 'Generate App Key',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && php artisan key:generate',
                    'desc'    => 'Generates a new APP_KEY in .env.',
                ],
                [
                    'name'    => 'Maintenance Mode ON',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && php artisan down',
                    'desc'    => 'Puts the app in maintenance mode (503 page).',
                ],
                [
                    'name'    => 'Maintenance Mode OFF',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && php artisan up',
                    'desc'    => 'Takes the app out of maintenance mode.',
                ],
            ],
            'Server — LiteSpeed' => [
                [
                    'name'    => 'Restart LiteSpeed',
                    'command' => '/usr/local/lsws/bin/lswsctrl restart',
                    'desc'    => 'Restarts the LiteSpeed web server.',
                ],
                [
                    'name'    => 'LiteSpeed Status',
                    'command' => 'systemctl status lshttpd',
                    'desc'    => 'Shows LiteSpeed service status.',
                ],
                [
                    'name'    => 'Fix Permissions',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && chmod -R 775 storage bootstrap/cache && chown -R hexawebsystems:hexawebsystems storage bootstrap/cache',
                    'desc'    => 'Resets storage/cache permissions to web-writable.',
                ],
            ],
            'Composer — Dependencies' => [
                [
                    'name'    => 'Install Dependencies',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && composer install --no-dev --optimize-autoloader',
                    'desc'    => 'Installs production dependencies from composer.lock.',
                ],
                [
                    'name'    => 'Update Dependencies',
                    'command' => 'cd /home/hexawebsystems/public_html/billing.hexawebsystems.com && composer update',
                    'desc'    => 'Updates all packages to latest allowed versions.',
                ],
            ],
            'Debug' => [
                [
                    'name'    => 'View Laravel Log (last 50 lines)',
                    'command' => 'tail -50 /home/hexawebsystems/public_html/billing.hexawebsystems.com/storage/logs/laravel.log',
                    'desc'    => 'Shows recent Laravel error log entries.',
                ],
                [
                    'name'    => 'View HWS Log',
                    'command' => 'tail -50 /home/hexawebsystems/public_html/billing.hexawebsystems.com/storage/logs/hws.log',
                    'desc'    => 'Shows recent HWS application log entries.',
                ],
                [
                    'name'    => 'Clear Laravel Log',
                    'command' => '> /home/hexawebsystems/public_html/billing.hexawebsystems.com/storage/logs/laravel.log',
                    'desc'    => 'Empties the Laravel log file.',
                ],
                [
                    'name'    => 'Debug Page',
                    'command' => 'https://billing.hexawebsystems.com/debug.php',
                    'desc'    => 'PHP extensions, DB connection, permissions, connectivity checks.',
                ],
            ],
        ];
    }
}
