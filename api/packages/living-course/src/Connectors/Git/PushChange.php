<?php

namespace Ulams\LivingCourse\Connectors\Git;

/**
 * What a push webhook says about the change that arrived (plan 9.1): the head commit, its subject line and, when the commit is a
 * merge or a squash of a pull request, the number of that request. Read from the payload of GitHub, Gitea/Forgejo and GitLab;
 * kept on the revision so a proposal can say "from PR #123: Rename the --namespace flag". The text is untrusted: it is shortened,
 * stripped of control characters, shown to people as plain text and never sent to the model.
 */
final class PushChange
{
    private const SUBJECT_MAX = 200;

    /**
     * @param array<string,mixed> $payload decoded webhook body
     * @param array{host:string,base_url:string,repository:string} $config normalised connection config
     * @return array{commit:?string,subject:?string,url:?string,pullRequest:?int,pullRequestUrl:?string,source:string}|null null when the payload names no commit
     */
    public static function fromPayload(array $payload, array $config): ?array
    {
        $head = is_array($payload['head_commit'] ?? null) ? $payload['head_commit'] : null;
        if ($head === null) {
            $commits = array_values(array_filter((array) ($payload['commits'] ?? []), 'is_array'));
            $head = $commits === [] ? null : $commits[count($commits) - 1];
        }
        $sha = self::sha($head['id'] ?? $payload['checkout_sha'] ?? $payload['after'] ?? null);
        if ($sha === null) {
            return null;
        }
        $message = (string) ($head['message'] ?? $head['title'] ?? '');
        $number = self::pullRequestNumber($message);

        return [
            'commit' => $sha,
            'subject' => self::subject($message),
            'url' => self::url($head['url'] ?? null),
            'pullRequest' => $number,
            'pullRequestUrl' => $number !== null ? self::pullRequestUrl($config, $number) : null,
            'source' => 'webhook',
        ];
    }

    /**
     * The change a CI job reports with `living connections check --commit`.
     *
     * @return array{commit:string,subject:null,url:null,pullRequest:null,pullRequestUrl:null,source:string}
     */
    public static function fromCommit(string $sha): array
    {
        return ['commit' => strtolower($sha), 'subject' => null, 'url' => null, 'pullRequest' => null, 'pullRequestUrl' => null, 'source' => 'ci'];
    }

    /** `Merge pull request #123 from ...`, Gitea's `Merge pull request 'Title' (#123) from ...`, a squash `Title (#123)`, GitLab's `See merge request group/project!123`. */
    public static function pullRequestNumber(string $message): ?int
    {
        $lines = preg_split('/\R/', trim($message)) ?: [];
        $first = $lines[0] ?? '';
        if (preg_match('/^Merge pull request #(\d{1,9})\b/', $first, $m) || preg_match('/^Merge pull request .*\(#(\d{1,9})\)/', $first, $m) || preg_match('/\(#(\d{1,9})\)\s*$/', $first, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^See merge request [\w.\-\/]+!(\d{1,9})\s*$/m', $message, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private static function sha(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[0-9a-f]{7,64}$/i', $value) === 1 && !preg_match('/^0+$/', $value) ? strtolower($value) : null;
    }

    /** The subject of a merge commit is its second paragraph (the first line only names the branch). */
    private static function subject(string $message): ?string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($message)) ?: [];
        $line = (string) ($paragraphs[0] ?? '');
        if (preg_match('/^Merge (pull request|branch|remote-tracking branch)/', $line) && isset($paragraphs[1])) {
            $line = (string) $paragraphs[1];
        }
        $line = trim((string) preg_replace('/[\x00-\x1f\x7f]+/u', ' ', strtok($line, "\n") ?: ''));

        return $line === '' ? null : mb_substr($line, 0, self::SUBJECT_MAX);
    }

    private static function url(mixed $value): ?string
    {
        return is_string($value) && strlen($value) <= 500 && preg_match('#^https?://[^\s<>"\']+$#i', $value) === 1 ? $value : null;
    }

    /** @param array{host:string,base_url:string,repository:string} $config */
    private static function pullRequestUrl(array $config, int $number): ?string
    {
        $repo = $config['repository'];

        return match ($config['host']) {
            'github' => $config['base_url'] === 'https://api.github.com' ? "https://github.com/{$repo}/pull/{$number}" : null,
            'gitlab' => self::url(preg_replace('#/api/v4$#', '', $config['base_url']) . "/{$repo}/-/merge_requests/{$number}"),
            'gitea' => self::url($config['base_url'] . "/{$repo}/pulls/{$number}"),
            default => null,
        };
    }
}
