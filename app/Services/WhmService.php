<?php

namespace App\Services;

use App\Models\WhmServer;
use App\Models\HostingAccount;
use Illuminate\Support\Facades\Http;

/**
 * WhmService — ALL WHM/cPanel API interactions go through this service.
 * Single wrapper for account listing, server info, and remote command execution.
 * NO other file should make WHM API calls.
 */
class WhmService
{
    protected GenericService $generic;

    public function __construct(GenericService $generic)
    {
        $this->generic = $generic;
    }

    /**
     * Make an authenticated WHM API call.
     *
     * @param WhmServer $server  The server to call
     * @param string    $function WHM API function name
     * @param array     $params  Query parameters
     * @return array{success: bool, data?: array, error?: string}
     */
    protected function apiCall(WhmServer $server, string $function, array $params = []): array
    {
        $url = "https://{$server->hostname}:{$server->port}/json-api/{$function}";
        $headers = $this->buildAuthHeaders($server);

        try {
            $response = Http::withHeaders($headers)
                ->withoutVerifying() // WHM uses self-signed certs by default
                ->timeout(30)
                ->get($url, array_merge($params, ['api.version' => 1]));

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error'   => 'HTTP ' . $response->status() . ': ' . $response->body(),
                ];
            }

            $json = $response->json();

            // WHM API v1 wraps results in 'result' or 'data'
            if (isset($json['metadata']['result']) && $json['metadata']['result'] === 0) {
                return [
                    'success' => false,
                    'error'   => $json['metadata']['reason'] ?? 'API returned failure',
                ];
            }

            return ['success' => true, 'data' => $json];
        } catch (\Exception $e) {
            $this->generic->log('error', 'WHM API call failed', [
                'server'   => $server->name,
                'function' => $function,
                'error'    => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build authentication headers based on server auth type.
     *
     * @param WhmServer $server
     * @return array HTTP headers
     */
    protected function buildAuthHeaders(WhmServer $server): array
    {
        $credentials = $server->credentials;
        $username = $server->username;

        return match ($server->auth_type) {
            'api_token'    => ['Authorization' => "whm {$username}:{$credentials}"],
            'access_hash'  => ['Authorization' => "WHM {$username}:" . preg_replace('/\s+/', '', $credentials)],
            'root_password' => ['Authorization' => 'Basic ' . base64_encode("{$username}:{$credentials}")],
            default         => ['Authorization' => "whm {$username}:{$credentials}"],
        };
    }

    /**
     * Test connection to a WHM server.
     *
     * @param WhmServer $server
     * @return array{success: bool, message: string, version?: string}
     */
    public function testConnection(WhmServer $server): array
    {
        $result = $this->apiCall($server, 'version');

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['error']];
        }

        $version = $result['data']['version'] ?? ($result['data']['data']['version'] ?? 'Unknown');

        return [
            'success' => true,
            'message' => 'Connected. WHM version: ' . $version,
            'version' => $version,
        ];
    }

    /**
     * List all accounts on a WHM server.
     *
     * @param WhmServer $server
     * @return array{success: bool, accounts?: array, error?: string}
     */
    public function listAccounts(WhmServer $server): array
    {
        $result = $this->apiCall($server, 'listaccts');

        if (!$result['success']) {
            return $result;
        }

        // WHM returns accounts in 'data' -> 'acct' or directly in 'acct'
        $accounts = $result['data']['data']['acct']
            ?? $result['data']['acct']
            ?? [];

        $parsed = [];
        foreach ($accounts as $acct) {
            $suspended = ($acct['suspended'] ?? 0) == 1
                || ($acct['suspendtime'] ?? 0) > 0
                || strtolower($acct['suspended'] ?? '') === 'yes';

            $diskUsed = $this->parseDiskValue($acct['diskused'] ?? '0');
            $diskLimit = $this->parseDiskValue($acct['disklimit'] ?? '0');

            $parsed[] = [
                'username'       => $acct['user'] ?? '',
                'domain'         => $acct['domain'] ?? '',
                'owner'          => $acct['owner'] ?? 'root',
                'email'          => $acct['email'] ?? '',
                'package'        => $acct['plan'] ?? ($acct['package'] ?? ''),
                'status'         => $suspended ? 'suspended' : 'active',
                'suspend_reason' => $acct['suspendreason'] ?? null,
                'ip_address'     => $acct['ip'] ?? '',
                'disk_used_mb'   => $diskUsed,
                'disk_limit_mb'  => $diskLimit,
                'shell_access'   => $acct['shell'] ?? '',
                'theme'          => $acct['theme'] ?? '',
                'start_date'     => $acct['startdate'] ?? null,
            ];
        }

        return ['success' => true, 'accounts' => $parsed];
    }

    /**
     * Sync accounts from WHM into the hosting_accounts table.
     * Upserts based on (whm_server_id, username). New accounts created,
     * existing updated, accounts no longer on WHM flagged as 'removed'.
     *
     * @param WhmServer $server
     * @return array{success: bool, message: string, added: int, updated: int, removed: int, total: int}
     */
    public function syncAccounts(WhmServer $server): array
    {
        $result = $this->listAccounts($server);

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to list accounts: ' . ($result['error'] ?? 'Unknown'),
                'added' => 0, 'updated' => 0, 'removed' => 0, 'total' => 0,
            ];
        }

        $whmAccounts = $result['accounts'];
        $whmUsernames = collect($whmAccounts)->pluck('username')->toArray();
        $added = 0;
        $updated = 0;

        foreach ($whmAccounts as $acct) {
            $existing = HostingAccount::where('whm_server_id', $server->id)
                ->where('username', $acct['username'])
                ->first();

            $data = [
                'whm_server_id'     => $server->id,
                'username'          => $acct['username'],
                'domain'            => $acct['domain'],
                'owner'             => $acct['owner'],
                'email'             => $acct['email'],
                'package'           => $acct['package'],
                'status'            => $acct['status'],
                'suspend_reason'    => $acct['suspend_reason'],
                'ip_address'        => $acct['ip_address'],
                'disk_used_mb'      => $acct['disk_used_mb'],
                'disk_limit_mb'     => $acct['disk_limit_mb'],
                'shell_access'      => $acct['shell_access'],
                'theme'             => $acct['theme'],
            ];

            // Parse start date if available
            if (!empty($acct['start_date'])) {
                try {
                    $data['server_created_at'] = \Carbon\Carbon::parse($acct['start_date'])->toDateString();
                } catch (\Exception $e) {
                    // Skip bad dates
                }
            }

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                HostingAccount::create($data);
                $added++;
            }
        }

        // Flag accounts no longer on WHM
        $removed = HostingAccount::where('whm_server_id', $server->id)
            ->whereNotIn('username', $whmUsernames)
            ->where('status', '!=', 'removed')
            ->update(['status' => 'removed']);

        // Update server sync metadata
        $server->update([
            'last_synced_at' => now(),
            'account_count'  => count($whmAccounts),
        ]);

        $this->generic->log('info', 'WHM account sync completed', [
            'server' => $server->name,
            'added'  => $added,
            'updated' => $updated,
            'removed' => $removed,
            'total'  => count($whmAccounts),
        ]);

        return [
            'success' => true,
            'message' => "Sync complete: {$added} added, {$updated} updated, {$removed} removed.",
            'added'   => $added,
            'updated' => $updated,
            'removed' => $removed,
            'total'   => count($whmAccounts),
        ];
    }

    /**
     * Get detailed server information.
     *
     * @param WhmServer $server
     * @return array{success: bool, info?: array, error?: string}
     */
    public function getServerInfo(WhmServer $server): array
    {
        $info = [];

        // WHM version
        $version = $this->apiCall($server, 'version');
        if ($version['success']) {
            $info['whm_version'] = $version['data']['version']
                ?? ($version['data']['data']['version'] ?? 'Unknown');
        }

        // Server hostname
        $hostname = $this->apiCall($server, 'gethostname');
        if ($hostname['success']) {
            $info['hostname'] = $hostname['data']['data']['hostname']
                ?? ($hostname['data']['hostname'] ?? $server->hostname);
        }

        // System load averages
        $load = $this->apiCall($server, 'systemloadavg', ['api.version' => 1]);
        if ($load['success']) {
            $loadData = $load['data']['data'] ?? $load['data'] ?? [];
            $info['load_1']  = $loadData['one'] ?? ($loadData['load_1'] ?? '—');
            $info['load_5']  = $loadData['five'] ?? ($loadData['load_5'] ?? '—');
            $info['load_15'] = $loadData['fifteen'] ?? ($loadData['load_15'] ?? '—');
        }

        // Disk usage
        $disk = $this->apiCall($server, 'getdiskusage');
        if ($disk['success']) {
            $info['disk_partitions'] = $disk['data']['data']['partition']
                ?? ($disk['data']['partition'] ?? []);
        }

        return ['success' => true, 'info' => $info];
    }

    /**
     * Execute a shell command on a WHM server via the API.
     * Uses the 'run_command' or equivalent API function.
     *
     * @param WhmServer $server
     * @param string    $command Shell command to run
     * @return array{success: bool, output?: string, error?: string}
     */
    public function executeCommand(WhmServer $server, string $command): array
    {
        // WHM doesn't have a direct "run command" API — we use a wrapper approach.
        // The safest method is to use the cPanel UAPI via WHM or a custom script endpoint.
        // For root-level commands, we'll use a PHP script via WHM's API.

        // Approach: Use the 'php_ini_set_content' trick or a server-side script.
        // Safest: Run via SSH2 extension or direct HTTP to a server-side script.
        // For now, we'll POST the command to a lightweight endpoint.

        $url = "https://{$server->hostname}:{$server->port}/json-api/run_command";
        $headers = $this->buildAuthHeaders($server);

        try {
            // Try the standard approach first
            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(60)
                ->post($url, ['command' => $command]);

            if ($response->successful()) {
                $json = $response->json();
                return [
                    'success' => true,
                    'output'  => $json['data']['output'] ?? $json['output'] ?? $response->body(),
                ];
            }

            // Fallback: try exec_shell if run_command isn't available
            $url2 = "https://{$server->hostname}:{$server->port}/json-api/exec_shell";
            $response2 = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(60)
                ->asForm()
                ->post($url2, ['command' => $command, 'api.version' => 1]);

            if ($response2->successful()) {
                $json2 = $response2->json();
                return [
                    'success' => true,
                    'output'  => $json2['data']['output'] ?? $json2['output'] ?? $response2->body(),
                ];
            }

            return ['success' => false, 'error' => 'Command execution not available on this server. HTTP ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse WHM disk values which can be "250M", "1G", "unlimited", or numeric MB.
     *
     * @param string|int $value
     * @return int Megabytes
     */
    protected function parseDiskValue($value): int
    {
        if (is_numeric($value)) return (int) $value;

        $value = strtolower(trim((string) $value));
        if ($value === 'unlimited' || $value === '0' || empty($value)) return 0;

        // Remove 'M' suffix
        if (str_ends_with($value, 'm')) {
            return (int) rtrim($value, 'm');
        }
        // Handle 'G' suffix
        if (str_ends_with($value, 'g')) {
            return (int) (rtrim($value, 'g') * 1024);
        }

        return (int) $value;
    }
}
