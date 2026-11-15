<?php
declare(strict_types=1);

if (!defined('NX_ADMIN')) {
    http_response_code(404);
    exit;
}

final class GitHubError extends RuntimeException
{
    public int $status;

    public function __construct(string $message, int $status)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

/** Minimal GitHub REST client for the repository the site deploys from. */
function gh_request(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    $token = $token ?? (nx_config()['github_token'] ?? '');
    // NX_GITHUB_API lets local tests point at a mock server instead of GitHub.
    $url = (getenv('NX_GITHUB_API') ?: 'https://api.github.com') . $path;
    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: nexbridge-admin',
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES);
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new GitHubError('Could not reach GitHub: ' . $err, 502);
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $payload ?? '',
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($raw === false) {
            throw new GitHubError('Could not reach GitHub.', 502);
        }
    }

    $data = json_decode((string) $raw, true);
    if ($status >= 400) {
        $msg = is_array($data) && isset($data['message']) ? $data['message'] : 'GitHub returned an error';
        throw new GitHubError($msg, $status);
    }
    return is_array($data) ? $data : [];
}

function gh_repo_path(string $suffix = ''): string
{
    return '/repos/' . NX_REPO . $suffix;
}

/** Returns ['content' => string, 'sha' => string] for a file on the deploy branch. */
function gh_get_file(string $path): array
{
    $res = gh_request('GET', gh_repo_path('/contents/' . rawurlencode_path($path) . '?ref=' . NX_BRANCH));
    if (($res['encoding'] ?? '') === 'base64' && isset($res['content'])) {
        $content = base64_decode(str_replace("\n", '', $res['content']), true);
    } else {
        // Files over 1 MB come back without content; fetch the raw blob instead.
        $blob = gh_request('GET', gh_repo_path('/git/blobs/' . $res['sha']));
        $content = base64_decode(str_replace("\n", '', $blob['content'] ?? ''), true);
    }
    return ['content' => (string) $content, 'sha' => (string) ($res['sha'] ?? '')];
}

/** Creates or updates a file with one commit. Pass the previous sha to update, or null to create. */
function gh_put_file(string $path, string $content, string $message, ?string $sha): array
{
    $body = [
        'message' => $message,
        'content' => base64_encode($content),
        'branch' => NX_BRANCH,
    ];
    if ($sha) {
        $body['sha'] = $sha;
    }
    $res = gh_request('PUT', gh_repo_path('/contents/' . rawurlencode_path($path)), $body);
    return ['sha' => $res['content']['sha'] ?? '', 'commit' => $res['commit']['sha'] ?? ''];
}

function rawurlencode_path(string $path): string
{
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

/** Checks that a token can push to the site repository. Returns an error message, or '' if it can. */
function gh_check_token(string $token): string
{
    try {
        $repo = gh_request('GET', gh_repo_path(), null, $token);
    } catch (GitHubError $e) {
        return $e->status === 401 ? 'GitHub rejected this token. Check that you copied all of it and that it has not expired.'
            : 'GitHub could not find the repository with this token (' . $e->getMessage() . ').';
    }
    $perm = $repo['permissions'] ?? [];
    if (empty($perm['push']) && empty($perm['admin']) && empty($perm['maintain'])) {
        return 'This token cannot save to the ' . NX_REPO . ' repository. Give it "Contents: Read and write" access to that repository.';
    }
    return '';
}

/** Latest run of the site build workflow, for the "publishing" indicator. */
function gh_latest_build(): ?array
{
    $res = gh_request('GET', gh_repo_path('/actions/workflows/build-site.yml/runs?per_page=1&branch=' . NX_BRANCH));
    $run = $res['workflow_runs'][0] ?? null;
    if (!$run) {
        return null;
    }
    return [
        'status' => $run['status'] ?? '',
        'conclusion' => $run['conclusion'] ?? '',
        'created_at' => $run['created_at'] ?? '',
        'updated_at' => $run['updated_at'] ?? '',
        'url' => $run['html_url'] ?? '',
        'head_sha' => $run['head_sha'] ?? '',
    ];
}
