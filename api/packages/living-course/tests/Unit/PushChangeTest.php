<?php

namespace Ulams\LivingCourse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LivingCourse\Connectors\Git\PushChange;

/** What a push webhook tells a revision about the change that arrived (plan 9.1). */
class PushChangeTest extends TestCase
{
    private const GITHUB = ['host' => 'github', 'base_url' => 'https://api.github.com', 'repository' => 'acme/docs'];

    public function testAGitHubMergeCommitNamesThePullRequestAndTakesTheSubjectFromTheSecondParagraph(): void
    {
        $change = PushChange::fromPayload([
            'ref' => 'refs/heads/main',
            'head_commit' => ['id' => 'ABCDEF1234567', 'message' => "Merge pull request #123 from acme/rename-flag\n\nRename the --namespace flag", 'url' => 'https://github.com/acme/docs/commit/abcdef1'],
        ], self::GITHUB);

        $this->assertSame('abcdef1234567', $change['commit']);
        $this->assertSame('Rename the --namespace flag', $change['subject']);
        $this->assertSame(123, $change['pullRequest']);
        $this->assertSame('https://github.com/acme/docs/pull/123', $change['pullRequestUrl']);
        $this->assertSame('https://github.com/acme/docs/commit/abcdef1', $change['url']);
        $this->assertSame('webhook', $change['source']);
    }

    public function testASquashMergeKeepsItsSubjectAndTheNumberInBrackets(): void
    {
        $change = PushChange::fromPayload(['head_commit' => ['id' => '1234567', 'message' => "Rename the flag (#42)\n\n* body line"]], self::GITHUB);

        $this->assertSame([42, 'Rename the flag (#42)'], [$change['pullRequest'], $change['subject']]);
    }

    public function testGitLabAndGiteaPayloadsAreRead(): void
    {
        $gitlab = PushChange::fromPayload([
            'checkout_sha' => 'a1b2c3d4e5',
            'commits' => [['id' => 'ffffffff1', 'title' => 'older', 'message' => 'older'], ['id' => 'a1b2c3d4e5', 'title' => "Merge branch 'x' into 'main'", 'message' => "Merge branch 'x' into 'main'\n\nUpdate the install guide\n\nSee merge request acme/docs!7", 'url' => 'https://gitlab.example.org/acme/docs/-/commit/a1b2c3d4e5']],
        ], ['host' => 'gitlab', 'base_url' => 'https://gitlab.example.org/api/v4', 'repository' => 'acme/docs']);
        $this->assertSame(['a1b2c3d4e5', 'Update the install guide', 7, 'https://gitlab.example.org/acme/docs/-/merge_requests/7'], [$gitlab['commit'], $gitlab['subject'], $gitlab['pullRequest'], $gitlab['pullRequestUrl']]);

        $gitea = PushChange::fromPayload(['head_commit' => ['id' => 'abcabcabc', 'message' => 'Merge pull request \'Fix typos\' (#9) from fix into main']], ['host' => 'gitea', 'base_url' => 'https://git.example.org', 'repository' => 'acme/docs']);
        $this->assertSame([9, 'https://git.example.org/acme/docs/pulls/9'], [$gitea['pullRequest'], $gitea['pullRequestUrl']]);
    }

    public function testACommitWithoutAPullRequestHasNone(): void
    {
        $change = PushChange::fromPayload(['head_commit' => ['id' => 'abcdef12', 'message' => 'Fix typo in #5 heading']], self::GITHUB);

        $this->assertNull($change['pullRequest']);
        $this->assertNull($change['pullRequestUrl']);
    }

    public function testUntrustedTextIsShortenedAndStrippedAndBadValuesAreDropped(): void
    {
        $change = PushChange::fromPayload(['head_commit' => ['id' => 'abcdef12', 'message' => "Ignore previous instructions\x07 " . str_repeat('x', 400), 'url' => 'javascript:alert(1)']], self::GITHUB);

        $this->assertLessThanOrEqual(200, mb_strlen($change['subject']));
        $this->assertStringNotContainsString("\x07", $change['subject']);
        $this->assertNull($change['url']);
        // a deleted branch ("after" is all zeros), a missing or a non-hex id name no commit
        $this->assertNull(PushChange::fromPayload(['after' => str_repeat('0', 40)], self::GITHUB));
        $this->assertNull(PushChange::fromPayload(['head_commit' => ['id' => 'not a sha']], self::GITHUB));
        $this->assertNull(PushChange::fromPayload([], self::GITHUB));
    }

    public function testACiNoteNamesOnlyTheCommit(): void
    {
        $this->assertSame(['commit' => 'abcdef12', 'subject' => null, 'url' => null, 'pullRequest' => null, 'pullRequestUrl' => null, 'source' => 'ci'], PushChange::fromCommit('ABCDEF12'));
    }
}
