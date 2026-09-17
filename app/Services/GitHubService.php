<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http\HttpClient;
use App\Core\Logger;
use App\Core\Str;

/**
 * GitHub API client for the update system.
 *
 * The repository, branch and personal access token are entered once in
 * Admin → System → Updates. The token is encrypted at rest, never rendered
 * in full, never written to a log, and never sent anywhere except
 * api.github.com / codeload.github.com over TLS.
 */
final class GitHubService
{
    public function __construct(
        private readonly SettingsService $settings = new SettingsService()
    ) {
    }

    public static function instance(): self
    {
        return new self(SettingsService::instance());
    }

    // ------------------------------------------------------------------
    //  Configuration
    // ------------------------------------------------------------------

    public function repository(): string
    {
        return trim((string) $this->settings->get('github_repository', ''));
    }

    public function branch(): string
    {
        $branch = trim((string) $this->settings->get('github_branch', 'main'));
        return $branch === '' ? 'main' : $branch;
    }

    public function token(): string
    {
        return (string) $this->settings->get('github_token', '');
    }

    public function hasToken(): bool
    {
        return $this->token() !== '';
    }

    public function isConfigured(): bool
    {
        return $this->repository() !== '';
    }

    public function maskedToken(): string
    {
        return Str::maskSecret($this->token());
    }

    /** @return array{ok:bool,message:string} */
    public function saveConfiguration(string $repository, string $branch, ?string $token, ?int $userId = null): array
    {
        $repository = trim($repository);
        // Accept a full URL or the owner/repo form.
        if (preg_match('#github\.com[/:]([\w.\-]+/[\w.\-]+?)(?:\.git)?/?$#i', $repository, $m) === 1) {
            $repository = $m[1];
        }
        if (preg_match('#^[\w.\-]+/[\w.\-]+$#', $repository) !== 1) {
            return ['ok' => false, 'message' => 'Enter the repository as owner/name, for example acme/invitations.'];
        }

        $branch = trim($branch);
        if ($branch === '') {
            $branch = 'main';
        }
        if (preg_match('#^[\w.\-/]{1,120}$#', $branch) !== 1) {
            return ['ok' => false, 'message' => 'That branch name is not valid.'];
        }

        $this->settings->set('github_repository', $repository, 'string', 'updates', $userId);
        $this->settings->set('github_branch', $branch, 'string', 'updates', $userId);

        // An empty token field means "keep the stored token".
        if ($token !== null && trim($token) !== '') {
            $token = trim($token);
            if (preg_match('#^[A-Za-z0-9_\-]{20,255}$#', $token) !== 1) {
                return ['ok' => false, 'message' => 'That does not look like a GitHub personal access token.'];
            }
            $this->settings->set('github_token', $token, 'encrypted', 'updates', $userId);
        }

        AuditService::instance()->log(
            'update.configure',
            'settings',
            null,
            'GitHub repository set to ' . $repository . ' (' . $branch . ')'
        );

        return ['ok' => true, 'message' => 'Update source saved.'];
    }

    public function clearToken(?int $userId = null): void
    {
        $this->settings->set('github_token', '', 'encrypted', 'updates', $userId);
        AuditService::instance()->log('update.token_cleared', 'settings');
    }

    // ------------------------------------------------------------------
    //  API calls
    // ------------------------------------------------------------------

    /** @return array<string,string> */
    private function headers(): array
    {
        $headers = [
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
        $token = $this->token();
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return $headers;
    }

    private function client(): HttpClient
    {
        return new HttpClient(max(10, (int) Config::get('update.timeout', 60)));
    }

    private function apiBase(): string
    {
        return rtrim((string) Config::get('update.api_base', 'https://api.github.com'), '/');
    }

    /**
     * Verify the repository and token.
     *
     * @return array{ok:bool,message:string,repository:array<string,mixed>|null}
     */
    public function verify(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'No repository has been configured yet.', 'repository' => null];
        }

        $response = $this->request('/repos/' . $this->repository());
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message'], 'repository' => null];
        }

        $data = $response['data'];
        $branchResponse = $this->request('/repos/' . $this->repository() . '/branches/' . rawurlencode($this->branch()));
        if (!$branchResponse['ok']) {
            return [
                'ok'      => false,
                'message' => 'The repository is reachable but the branch "' . $this->branch() . '" was not found.',
                'repository' => null,
            ];
        }

        return [
            'ok'      => true,
            'message' => 'Connected to ' . (string) ($data['full_name'] ?? $this->repository())
                . ' (' . $this->branch() . ').',
            'repository' => [
                'full_name'  => (string) ($data['full_name'] ?? ''),
                'private'    => (bool) ($data['private'] ?? false),
                'default_branch' => (string) ($data['default_branch'] ?? ''),
                'updated_at' => (string) ($data['updated_at'] ?? ''),
                'html_url'   => (string) ($data['html_url'] ?? ''),
            ],
        ];
    }

    /**
     * The latest commit on the configured branch.
     *
     * @return array{ok:bool,message:string,commit:array<string,mixed>|null}
     */
    public function latestCommit(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'No repository has been configured yet.', 'commit' => null];
        }

        $response = $this->request(
            '/repos/' . $this->repository() . '/commits/' . rawurlencode($this->branch())
        );
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message'], 'commit' => null];
        }

        $data = $response['data'];
        $files = [];
        foreach ((array) ($data['files'] ?? []) as $file) {
            if (!is_array($file)) {
                continue;
            }
            $files[] = [
                'filename'  => (string) ($file['filename'] ?? ''),
                'status'    => (string) ($file['status'] ?? ''),
                'additions' => (int) ($file['additions'] ?? 0),
                'deletions' => (int) ($file['deletions'] ?? 0),
            ];
        }

        return [
            'ok'      => true,
            'message' => 'Commit information retrieved.',
            'commit'  => [
                'sha'        => (string) ($data['sha'] ?? ''),
                'short_sha'  => substr((string) ($data['sha'] ?? ''), 0, 7),
                'message'    => mb_substr((string) ($data['commit']['message'] ?? ''), 0, 500),
                'author'     => (string) ($data['commit']['author']['name'] ?? 'unknown'),
                'date'       => (string) ($data['commit']['author']['date'] ?? ''),
                'html_url'   => (string) ($data['html_url'] ?? ''),
                'files'      => $files,
                'added'      => count(array_filter($files, static fn ($f) => $f['status'] === 'added')),
                'modified'   => count(array_filter($files, static fn ($f) => $f['status'] === 'modified')),
                'removed'    => count(array_filter($files, static fn ($f) => $f['status'] === 'removed')),
            ],
        ];
    }

    /**
     * version.json on the remote branch, so an update can be compared by
     * semantic version rather than only by commit.
     *
     * @return array{ok:bool,version:string|null,notes:string,raw:array<string,mixed>|null}
     */
    public function remoteVersion(): array
    {
        $response = $this->request(
            '/repos/' . $this->repository() . '/contents/version.json?ref=' . rawurlencode($this->branch())
        );
        if (!$response['ok']) {
            return ['ok' => false, 'version' => null, 'notes' => $response['message'], 'raw' => null];
        }

        $content = (string) ($response['data']['content'] ?? '');
        $encoding = (string) ($response['data']['encoding'] ?? 'base64');
        $decoded = $encoding === 'base64' ? base64_decode($content, true) : $content;
        if ($decoded === false) {
            return ['ok' => false, 'version' => null, 'notes' => 'version.json could not be decoded.', 'raw' => null];
        }

        $json = json_decode($decoded, true);
        if (!is_array($json) || !isset($json['version'])) {
            return ['ok' => false, 'version' => null, 'notes' => 'version.json is not valid.', 'raw' => null];
        }

        return [
            'ok'      => true,
            'version' => (string) $json['version'],
            'notes'   => mb_substr((string) ($json['notes'] ?? ''), 0, 2000),
            'raw'     => $json,
        ];
    }

    /**
     * The latest published release, when the project uses releases/tags.
     *
     * @return array{ok:bool,release:array<string,mixed>|null,message:string}
     */
    public function latestRelease(): array
    {
        $response = $this->request('/repos/' . $this->repository() . '/releases/latest');
        if (!$response['ok']) {
            return ['ok' => false, 'release' => null, 'message' => $response['message']];
        }
        $data = $response['data'];
        return [
            'ok'      => true,
            'message' => 'Release found.',
            'release' => [
                'tag'          => (string) ($data['tag_name'] ?? ''),
                'name'         => (string) ($data['name'] ?? ''),
                'body'         => mb_substr((string) ($data['body'] ?? ''), 0, 4000),
                'published_at' => (string) ($data['published_at'] ?? ''),
                'html_url'     => (string) ($data['html_url'] ?? ''),
                'prerelease'   => (bool) ($data['prerelease'] ?? false),
            ],
        ];
    }

    /**
     * Compare the installed commit with the remote head, to list changed
     * files across several commits rather than only the newest one.
     *
     * @return array{ok:bool,files:array<int,array<string,mixed>>,ahead:int,message:string}
     */
    public function compare(string $fromSha): array
    {
        if ($fromSha === '') {
            return ['ok' => false, 'files' => [], 'ahead' => 0, 'message' => 'No previous commit is recorded.'];
        }
        $response = $this->request(
            '/repos/' . $this->repository() . '/compare/' . rawurlencode($fromSha) . '...' . rawurlencode($this->branch())
        );
        if (!$response['ok']) {
            return ['ok' => false, 'files' => [], 'ahead' => 0, 'message' => $response['message']];
        }
        $data = $response['data'];
        $files = [];
        foreach ((array) ($data['files'] ?? []) as $file) {
            if (!is_array($file)) {
                continue;
            }
            $files[] = [
                'filename' => (string) ($file['filename'] ?? ''),
                'status'   => (string) ($file['status'] ?? ''),
            ];
        }
        return [
            'ok'      => true,
            'files'   => $files,
            'ahead'   => (int) ($data['ahead_by'] ?? 0),
            'message' => (int) ($data['ahead_by'] ?? 0) . ' commit(s) ahead.',
        ];
    }

    /**
     * Download the branch as a ZIP.
     *
     * @return array{ok:bool,message:string,path:string|null,size:int}
     */
    public function downloadArchive(string $destination): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'No repository has been configured.', 'path' => null, 'size' => 0];
        }

        $url = $this->apiBase() . '/repos/' . $this->repository()
            . '/zipball/' . rawurlencode($this->branch());

        $directory = dirname($destination);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return ['ok' => false, 'message' => 'Cannot write to ' . $directory, 'path' => null, 'size' => 0];
        }
        if (is_file($destination)) {
            @unlink($destination);
        }

        try {
            $response = $this->client()->get($url, $this->headers(), $destination);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Download failed: ' . $e->getMessage(), 'path' => null, 'size' => 0];
        }

        if ($response['error'] !== null) {
            @unlink($destination);
            return ['ok' => false, 'message' => 'Download failed: ' . $response['error'], 'path' => null, 'size' => 0];
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            @unlink($destination);
            return [
                'ok'      => false,
                'message' => 'GitHub returned HTTP ' . $response['status'] . ' for the archive download.',
                'path'    => null,
                'size'    => 0,
            ];
        }

        $size = is_file($destination) ? (int) filesize($destination) : 0;
        if ($size < 1024) {
            @unlink($destination);
            return ['ok' => false, 'message' => 'The downloaded archive is empty or truncated.', 'path' => null, 'size' => 0];
        }

        // Validate that it really is a ZIP before anything touches it.
        $magic = (string) file_get_contents($destination, false, null, 0, 4);
        if (!str_starts_with($magic, "PK\x03\x04") && !str_starts_with($magic, "PK\x05\x06")) {
            @unlink($destination);
            Logger::security('Downloaded update archive was not a ZIP file');
            return ['ok' => false, 'message' => 'The downloaded file is not a ZIP archive.', 'path' => null, 'size' => 0];
        }

        Logger::info('Update archive downloaded', [
            'size'   => $size,
            'branch' => $this->branch(),
        ], Logger::UPDATE);

        return [
            'ok'      => true,
            'message' => 'Downloaded ' . Str::bytesToHuman((float) $size) . '.',
            'path'    => $destination,
            'size'    => $size,
        ];
    }

    /** Remaining API quota, useful when a check fails. */
    public function rateLimit(): array
    {
        $response = $this->request('/rate_limit');
        if (!$response['ok']) {
            return ['limit' => 0, 'remaining' => 0, 'reset' => 0];
        }
        $core = $response['data']['resources']['core'] ?? [];
        return [
            'limit'     => (int) ($core['limit'] ?? 0),
            'remaining' => (int) ($core['remaining'] ?? 0),
            'reset'     => (int) ($core['reset'] ?? 0),
        ];
    }

    // ------------------------------------------------------------------
    //  Transport
    // ------------------------------------------------------------------

    /** @return array{ok:bool,message:string,data:array<string,mixed>} */
    private function request(string $path): array
    {
        $url = $this->apiBase() . $path;

        try {
            $response = $this->client()->get($url, $this->headers());
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Request failed: ' . $e->getMessage(), 'data' => []];
        }

        if ($response['error'] !== null) {
            return ['ok' => false, 'message' => $this->transportMessage($response['error']), 'data' => []];
        }

        $status = $response['status'];
        if ($status === 401) {
            return ['ok' => false, 'message' => 'GitHub rejected the token. Check that it is valid and has repo access.', 'data' => []];
        }
        if ($status === 403) {
            $remaining = (int) ($response['headers']['x-ratelimit-remaining'] ?? 1);
            if ($remaining === 0) {
                $reset = (int) ($response['headers']['x-ratelimit-reset'] ?? 0);
                $minutes = $reset > 0 ? max(1, (int) ceil(($reset - time()) / 60)) : 60;
                return [
                    'ok'      => false,
                    'message' => 'GitHub API rate limit reached. Try again in about ' . $minutes . ' minute(s), '
                        . 'or add a personal access token to raise the limit.',
                    'data'    => [],
                ];
            }
            return ['ok' => false, 'message' => 'GitHub denied access to this repository (HTTP 403).', 'data' => []];
        }
        if ($status === 404) {
            return [
                'ok'      => false,
                'message' => 'Not found on GitHub. Check the repository name and branch, '
                    . 'and add a token if the repository is private.',
                'data'    => [],
            ];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'message' => 'GitHub returned HTTP ' . $status . '.', 'data' => []];
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'message' => 'GitHub returned an unreadable response.', 'data' => []];
        }

        return ['ok' => true, 'message' => 'OK', 'data' => $decoded];
    }

    private function transportMessage(string $error): string
    {
        $lower = strtolower($error);
        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return 'The connection to GitHub timed out.';
        }
        if (str_contains($lower, 'ssl') || str_contains($lower, 'certificate')) {
            return 'The TLS connection to GitHub could not be verified. Check the server CA bundle.';
        }
        if (str_contains($lower, 'resolve')) {
            return 'GitHub could not be resolved. Check outbound DNS and internet access.';
        }
        return 'Could not reach GitHub.';
    }
}
