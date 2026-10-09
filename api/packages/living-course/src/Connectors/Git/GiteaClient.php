<?php

namespace Ulams\LivingCourse\Connectors\Git;

use Ulams\LivingCourse\Connectors\ConnectorException;

/** Gitea and Forgejo REST API (`/api/v1`): branches, git trees and blobs of a repository. */
final class GiteaClient extends GitHostClient
{
    protected function headers(): array
    {
        return parent::headers() + array_filter(['Authorization' => $this->token !== null ? 'token ' . $this->token : null]);
    }

    private function repo(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/v1/repos/' . implode('/', array_map('rawurlencode', explode('/', $this->repository)));
    }

    public function head(): string
    {
        $data = $this->json($this->get($this->repo() . '/branches/' . rawurlencode($this->branch)), 'head commit');
        $sha = (string) ($data['commit']['id'] ?? '');
        if (!preg_match('/^[0-9a-f]{40,64}$/', $sha)) {
            throw new ConnectorException('The Git host sent an unexpected head commit.');
        }

        return $sha;
    }

    public function tree(string $commit): array
    {
        $files = [];
        for ($page = 1; $page <= 100; $page++) {
            $data = $this->json($this->get($this->repo() . '/git/trees/' . rawurlencode($commit), ['recursive' => 'true', 'per_page' => 1000, 'page' => $page]), 'file tree');
            foreach ($data['tree'] ?? [] as $entry) {
                if (($entry['type'] ?? '') === 'blob') {
                    $files[] = ['path' => (string) $entry['path'], 'blob' => (string) $entry['sha'], 'size' => isset($entry['size']) ? (int) $entry['size'] : null];
                }
            }
            if (empty($data['truncated'])) {
                break;
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
