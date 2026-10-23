<?php

namespace Ulams\PolicyLifecycle\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ulams\Auth\Models\Group;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * Creates policies: the owner, the stored policy file as version 1 (a draft) and one variant row per department
 * with its audience group. Nothing is generated here; `VariantGenerator` does that when the owner asks.
 */
final class PolicyService
{
    public const MAX_DEPARTMENTS = 12;

    public function __construct(private readonly SourceIngestor $ingestor, private readonly PolicyAudit $audit)
    {
    }

    /**
     * @param array{title:string,owner_user_id?:?int,minor_due_days?:?int,major_due_days?:?int,brief?:array<string,mixed>,departments:array<int,array<string,mixed>>} $data
     *
     * @throws \Ulams\Uploads\Exceptions\UploadRejected the file is not a Markdown, PDF or DOCX document
     * @throws PolicyException
     */
    public function create(Authenticatable $actor, array $data, UploadedFile $file): Policy
    {
        $departments = self::departments($data['departments'] ?? []);
        $info = $this->ingestor->inspect($file);

        return DB::transaction(function () use ($actor, $data, $file, $info, $departments) {
            $policy = Policy::query()->create([
                'title' => mb_substr(trim($data['title']), 0, 255),
                'owner_user_id' => (int) ($data['owner_user_id'] ?? $actor->getAuthIdentifier()),
                'minor_due_days' => (int) ($data['minor_due_days'] ?? 14),
                'major_due_days' => (int) ($data['major_due_days'] ?? 30),
                'brief' => self::brief((array) ($data['brief'] ?? [])),
                'status' => Policy::ACTIVE,
            ]);
            $version = $this->storeVersion($policy, 1, $file, $info, PolicyVersion::INITIAL, $actor);
            foreach ($departments as $position => $department) {
                PolicyVariant::query()->create([
                    'policy_id' => $policy->id,
                    'department_label' => $department['label'],
                    'group_id' => $this->audience($policy, $department),
                    'brief' => array_filter([
                        'totalMinutes' => $department['totalMinutes'],
                        'seniority' => $department['seniority'],
                        'priorExperience' => $department['priorExperience'],
                    ], fn ($v) => $v !== null),
                    'position' => $position,
                ]);
            }
            $this->audit->record('policy.created', $policy, $version, ['departments' => array_column($departments, 'label'), 'sha256' => $version->file_sha256]);

            return $policy->load(['versions', 'variants']);
        });
    }

    /**
     * Stores the uploaded policy on the Course Builder's private disk (never served) and records it as a version.
     *
     * @param array{name:string,sha256:string,size:int} $info
     */
    public function storeVersion(Policy $policy, int $number, UploadedFile $file, array $info, string $class, ?Authenticatable $actor): PolicyVersion
    {
        $path = "policies/{$policy->id}/v{$number}-{$info['sha256']}";
        Storage::disk(SourceIngestor::disk())->put($path, (string) file_get_contents((string) $file->getRealPath()));

        return PolicyVersion::query()->create([
            'policy_id' => $policy->id,
            'number' => $number,
            'status' => $number === 1 ? PolicyVersion::DRAFT : PolicyVersion::IN_REVIEW,
            'change_class' => $class,
            'file_name' => $info['name'],
            'file_path' => $path,
            'file_sha256' => $info['sha256'],
            'file_size' => $info['size'],
            'submitted_by' => $actor?->getAuthIdentifier(),
        ]);
    }

    /** The stored file of a version, as an upload the builder's ingestor accepts. */
    public function fileOf(PolicyVersion $version): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'policy') ?: throw new PolicyException('The server cannot write temporary files.', 500);
        $named = $temp . '-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $version->file_name);
        rename($temp, $named);
        file_put_contents($named, Storage::disk(SourceIngestor::disk())->get($version->file_path));

        return new UploadedFile($named, $version->file_name, null, null, true);
    }

    /**
     * What generating the variants will cost about, before it runs (plan section 8): the lessons the duration asks for
     * times the builder's per-lesson figure, plus the outline call. An estimate, not a quote.
     *
     * @return array{perVariant:array<int,array{id:string,department:string,lessons:int,estimateMicroUsd:int}>,totalMicroUsd:int}
     */
    public function estimate(Policy $policy): array
    {
        $perLesson = (int) round(((float) config('course_builder.variants.lesson_estimate_usd', 0.06)) * 1000000);
        $rows = [];
        foreach ($policy->variants as $variant) {
            $total = (int) ($variant->brief['totalMinutes'] ?? $policy->brief['totalMinutes'] ?? 30);
            $lessons = max(1, (int) round($total / max(1, (int) ($policy->brief['lessonMinutes'] ?? 10))));
            $rows[] = ['id' => $variant->id, 'department' => $variant->department_label, 'lessons' => $lessons, 'estimateMicroUsd' => $lessons * $perLesson + (int) round($perLesson / 4)];
        }

        return ['perVariant' => $rows, 'totalMicroUsd' => array_sum(array_column($rows, 'estimateMicroUsd'))];
    }

    /**
     * @param array<int,array<string,mixed>> $input
     *
     * @return array<int,array{label:string,group_id:?int,seniority:string,priorExperience:string,totalMinutes:?int}>
     *
     * @throws PolicyException
     */
    public static function departments(array $input): array
    {
        $out = [];
        $seen = [];
        foreach ($input as $row) {
            $label = mb_substr(trim((string) ($row['label'] ?? '')), 0, 120);
            if ($label === '') {
                throw new PolicyException('Every department needs a name.');
            }
            $key = mb_strtolower($label);
            if (isset($seen[$key])) {
                throw new PolicyException("The department \"{$label}\" is listed twice.");
            }
            $seen[$key] = true;
            $seniority = (string) ($row['seniority'] ?? 'mixed');
            $experience = (string) ($row['priorExperience'] ?? 'some');
            if (!in_array($seniority, PolicyVariant::SENIORITY, true) || !in_array($experience, PolicyVariant::EXPERIENCE, true)) {
                throw new PolicyException('Seniority is junior, mid, senior or mixed; prior experience is none, some or expert.');
            }
            $out[] = [
                'label' => $label,
                'group_id' => isset($row['group_id']) && $row['group_id'] !== '' ? (int) $row['group_id'] : null,
                'seniority' => $seniority,
                'priorExperience' => $experience,
                'totalMinutes' => isset($row['totalMinutes']) && $row['totalMinutes'] !== '' ? max(5, min(2400, (int) $row['totalMinutes'])) : null,
            ];
        }
        if ($out === [] || count($out) > self::MAX_DEPARTMENTS) {
            throw new PolicyException(sprintf('A policy needs between 1 and %d departments.', self::MAX_DEPARTMENTS));
        }

        return $out;
    }

    /** @return array{totalMinutes:int,lessonMinutes:int,language:string,tone:string} */
    public static function brief(array $input): array
    {
        $tone = (string) ($input['tone'] ?? 'professional');
        $language = strtolower(substr((string) ($input['language'] ?? 'en'), 0, 2));

        return [
            'totalMinutes' => max(5, min(2400, (int) ($input['totalMinutes'] ?? 30))),
            'lessonMinutes' => max(2, min(120, (int) ($input['lessonMinutes'] ?? 10))),
            'language' => preg_match('/^[a-z]{2}$/', $language) ? $language : 'en',
            'tone' => in_array($tone, ['friendly', 'professional', 'playful', 'academic'], true) ? $tone : 'professional',
        ];
    }

    /** The department's group: the one given (it must exist) or a new one named after the policy and the department. */
    private function audience(Policy $policy, array $department): int
    {
        if ($department['group_id'] !== null) {
            return (int) Group::query()->findOrFail($department['group_id'])->getKey();
        }

        return (int) Group::query()->create(['name' => mb_substr($policy->title . ' / ' . $department['label'], 0, 255), 'registerable' => false])->getKey();
    }
}
