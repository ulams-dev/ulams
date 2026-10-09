<?php

namespace Ulams\LivingCourse\Connectors\Git;

use Ulams\LivingCourse\Connectors\ConnectorException;

/** GitHub REST API v3: `GET /repos/{owner}/{repo}/commits|git/trees|git/blobs`. */
final class GitHubClient extends GitHostClient
{
    protected function headers(): array
    {
        return parent::headers() + array_filter([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'Authorization' => $this->token !== null ? 'Bearer ' . $this->token : null,
        ]);
    }

    private function repo(): string
    {
        return rtrim($this->baseUrl, '/') . '/repos/' . implode('/', array_map('rawurlencode', explode('/', $this->repository)));
    }

    public function head(): string
    {
        // the cheap check: only the SHA of the head commit
        $response = $this->get($this->repo() . '/commits/' . rawurlencode($this->branch), [], ['Accept' => 'application/vnd.github.sha']);
        $this->assertOk($response, 'head commit');
        $sha = trim((string) $response->getBody());
        if (!preg_match('/^[0-9a-f]{40,64}$/', $sha)) {
            throw new ConnectorException('The Git host sent an unexpected head commit.');
        }

        return $sha;
    }

    public function tree(string $commit): array
    {
        $data = $this->json($this->get($this->repo() . '/git/trees/' . rawurlencode($commit), ['recursive' => 1]), 'file tree');
        if (!empty($data['truncated'])) {
            throw new ConnectorException('The repository has too many files for one request. Narrow the paths (for example docs/**/*.md).');
        }
        $files = [];
        foreach ($data['tree'] ?? [] as $entry) {
            if (($entry['type'] ?? '') === 'blob') {
                $files[] = ['path' => (string) $entry['path'], 'blob' => (string) $entry['sha'], 'size' => isset($entry['size']) ? (int) $entry['size'] : null];
            }
        }
        usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    public function blob(string $blob): string
    {
        return $this->content($this->json($this->get($this->repo() . '/git/blobs/' . rawurlencode($blob)), 'file'));
    }
}
