<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use Opis\JsonSchema\Validator;
use Ulams\Compliance\Models\Assignment;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Courses\Models\Course;
use Ulams\PolicyLifecycle\Events\PolicyChangeApproved;
use Ulams\PolicyLifecycle\Events\PolicyRetrainingAssigned;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\PolicyLifecycle\Services\RolloutService;
use Ulams\PolicyLifecycle\Tests\NotificationsTestCase;
use Ulams\PolicyLifecycle\Tests\Support\TrainsPeople;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Services\CertificateVerificationService;
use Ulams\Webhooks\Support\EventCatalog;

/** The e-mails, in-app notices and webhook payloads of a policy change. */
class NoticesTest extends NotificationsTestCase
{
    use TrainsPeople;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplatesPdfSeeder::class);
    }

    /** @return array{0:\Ulams\Auth\Models\User,1:\Ulams\PolicyLifecycle\Models\Policy,2:array<int,\Ulams\Courses\Tests\Models\User>,3:PolicyVersion} */
    private function changed(string $class = 'major', ?string $reason = null): array
    {
        $owner = $this->admin();
        [$policy, $engineers] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);
        $this->submitChange($owner, $policy->id)->assertCreated();
        $version = PolicyVersion::query()->where('policy_id', $policy->id)->where('number', 2)->firstOrFail();
        $this->acceptAllProposals($owner, $version);
        $this->approveVersion($owner, $policy->id, 2, array_filter(['change_class' => $class, 'reason' => $reason]))->assertOk();

        return [$owner, $policy, $engineers, $version->refresh()];
    }

    public function testTheLearnerGetsOneNoticeWithTheDiffTheCourseAndTheDueDate(): void
    {
        [, $policy, $engineers] = $this->changed();
        $ada = $engineers[0];

        $subject = 'The policy "Remote work and data handling" changed: training required';
        $mine = array_values(array_filter($this->sentMails(), fn (array $m) => $m[0] === $ada->email && $m[1] === $subject));
        $this->assertCount(1, $mine);
        $body = $this->mailBody($ada->email, $subject);
        $this->assertStringContainsString('has a new version (2)', $body);
        $this->assertStringContainsString('Sections 2 changed', $body);
        $this->assertStringContainsString('1.1 Approval and working hours', $body);
        $this->assertStringContainsString('Your earlier certificate no longer applies', $body);
        $this->assertStringContainsString(now()->addDays(30)->format('Y-m-d'), $body);
        $this->assertStringContainsString('https://academy.example.test/account/training', $body);
        $this->assertSame(1, $this->inApp($ada->getKey())['PolicyRetrainingAssigned']);
        // the people who owe nothing are not told
        $this->assertSame([], array_values(array_filter($this->sentMails(), fn (array $m) => $m[0] === $engineers[1]->email && $m[1] === $subject)));
        $this->assertSame([], $this->inApp($engineers[1]->getKey()));
    }

    public function testASupersededCertificateGetsNoSeparateRevocationNotice(): void
    {
        [, , $engineers] = $this->changed();

        $revoked = array_values(array_filter($this->sentMails(), fn (array $m) => str_starts_with($m[1], 'Your certificate was revoked')));
        $this->assertSame([], $revoked);
        $this->assertArrayNotHasKey('CertificateRevokedNotice', $this->inApp($engineers[0]->getKey()));
    }

    public function testAnotherRevocationIsToldToTheHolderOnce(): void
    {
        $owner = $this->admin();
        [$policy, $engineers] = $this->trainedPolicy($owner);
        $this->finish($engineers[0], $policy->variants[0]->course_id);
        $certificate = FabricPDF::query()->where('user_id', $engineers[0]->getKey())->firstOrFail();

        app(CertificateVerificationService::class)->revoke($certificate, CoreUser::find($owner->getKey()), 'Issued in error');
        app(CertificateVerificationService::class)->revoke($certificate->refresh(), CoreUser::find($owner->getKey()), 'Issued in error');

        $mine = array_values(array_filter($this->sentMails(), fn (array $m) => $m[0] === $engineers[0]->email && str_starts_with($m[1], 'Your certificate was revoked')));
        $this->assertCount(1, $mine);
        $this->assertStringContainsString('Reason: Issued in error', $this->mailBody($engineers[0]->email, $mine[0][1]));
        $this->assertSame(1, $this->inApp($engineers[0]->getKey())['CertificateRevokedNotice']);
    }

    public function testTheOwnerGetsOneNoticeWithTheCountsPerDepartment(): void
    {
        [$owner] = $this->changed();

        $subject = 'Policy change approved: Remote work and data handling';
        $mine = array_values(array_filter($this->sentMails(), fn (array $m) => $m[0] === $owner->email && $m[1] === $subject));
        $this->assertCount(1, $mine);
        $body = $this->mailBody($owner->email, $subject);
        $this->assertStringContainsString('Version 2 of "Remote work and data handling" is approved as a major change', $body);
        $this->assertStringContainsString('Engineering: 1 revoked, 1 reassigned; Sales: 0 revoked, 0 reassigned', $body);
        $this->assertSame(1, $this->inApp($owner->getKey())['PolicyChangeApproved']);
    }

    public function testARerunSendsNothingAgain(): void
    {
        [, , $engineers, $version] = $this->changed();
        $mails = count($this->sentMails());
        $version->forceFill(['status' => PolicyVersion::APPLYING])->save();

        app(RolloutService::class)->run($version);

        $this->assertCount($mails, $this->sentMails());
        $this->assertSame(1, $this->inApp($engineers[0]->getKey())['PolicyRetrainingAssigned']);
    }

    public function testAMinorChangeNamesTheExamInTheNotice(): void
    {
        [, $policy, $engineers] = $this->changed('minor', 'Wording only.');

        $exam = Course::query()->findOrFail($policy->variants[0]->refresh()->exam_course_id);
        $body = $this->mailBody($engineers[0]->email, 'The policy "Remote work and data handling" changed: training required');
        $this->assertStringContainsString('Please complete "' . $exam->title . '"', $body);
        $this->assertStringContainsString(now()->addDays(14)->format('Y-m-d'), $body);
        $this->assertSame($exam->getKey(), Assignment::query()->where('user_id', $engineers[0]->getKey())->where('source', 'policy_change')->value('course_id'));
    }

    public function testTheWebhookPayloadsMatchThePublishedSchema(): void
    {
        $schema = json_decode((string) file_get_contents(__DIR__ . '/../../../webhooks/resources/schemas/webhook-event-2026-10-20.json'));
        $captured = [];
        \Illuminate\Support\Facades\Event::listen(PolicyRetrainingAssigned::class, function ($e) use (&$captured) { $captured['policy.retraining.assigned'] = $e; });
        \Illuminate\Support\Facades\Event::listen(PolicyChangeApproved::class, function ($e) use (&$captured) { $captured['policy.change.approved'] = $e; });
        $this->changed();

        $this->assertSame(['policy.change.approved', 'policy.retraining.assigned'], collect($captured)->keys()->sort()->values()->all());
        foreach ($captured as $type => $event) {
            $payload = EventCatalog::payloadFor($type, $event);
            $this->assertNotNull($payload, $type);
            $document = ['id' => 'evt_' . str_repeat('a', 26), 'object' => 'event', 'api_version' => '2026-10-20', 'created' => time(), 'type' => $type, 'data' => ['object' => $payload['object']]];
            $result = (new Validator())->validate(json_decode(json_encode($document)), $schema);
            $this->assertTrue($result->isValid(), $type . ' ' . json_encode($result->error()?->message()));
        }
        $retraining = EventCatalog::payloadFor('policy.retraining.assigned', $captured['policy.retraining.assigned']);
        $this->assertSame('course', $retraining['object']['kind']);
        $this->assertSame(2, $retraining['object']['version']);
        $this->assertStringStartsWith('policy.retraining.assigned:', $retraining['key']);
        $approved = EventCatalog::payloadFor('policy.change.approved', $captured['policy.change.approved']);
        $this->assertSame('major', $approved['object']['change_class']);
        $this->assertSame([['department' => 'Engineering', 'revoked' => 1, 'reassigned' => 1], ['department' => 'Sales', 'revoked' => 0, 'reassigned' => 0]], $approved['object']['departments']);
    }
}
