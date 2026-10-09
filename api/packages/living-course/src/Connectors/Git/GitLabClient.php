<?php

namespace Ulams\LivingCourse\Connectors\Git;

use Ulams\LivingCourse\Connectors\ConnectorException;

/** GitLab REST API v4 (gitlab.com or self-hosted): branches, repository tree and raw blobs of a project. */
final class GitLabClient extends GitHostClient
{
    protected function headers(): array
    {
        return parent::headers() + array_filter(['PRIVATE-TOKEN' => $this->token]);
    }

    private function project(): string
    {
        return rtrim($this->baseUrl, '/') . '/projects/' . rawurlencode($this->repository);
    }

    public function head(): string
    {
        $data = $this->json($this->get($this->project() . '/repository/branches/' . rawurlencode($this->branch)), 'head commit');
        $sha = (string) ($data['commit']['id'] ?? '');
        if (!preg_match('/^[0-9a-f]{40,64}$/', $sha)) {
            throw new ConnectorException('The Git host sent an unexpected head commit.');
        }

        return $sha;
    }

    public function tree(string $commit): array
    {
        $files = [];
        for ($page = 1; $page <= 200; $page++) {
            $entries = $this->json($this->get($this->project() . '/repository/tree', ['ref' => $commit, 'recursive' => 'true', 'per_page' => 100, 'page' => $page]), 'file tree');
            foreach ($entries as $entry) {
                if (($entry['type'] ?? '') === 'blob') {
                    // the tree does not give sizes; the download is capped instead
                    $files[] = ['path' => (string) $entry['path'], 'blob' => (string) $entry['id'], 'size' => null];
                }
            }
            if (count($entries) < 100) {
                break;
            }
        }
        usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    public function blob(string $blob): string
    {
        $response = $this->get($this->project() . '/repository/blobs/' . rawurlencode($blob) . '/raw');
        $this->assertOk($response, 'file');

        return (string) $response->getBody();
    }
}
