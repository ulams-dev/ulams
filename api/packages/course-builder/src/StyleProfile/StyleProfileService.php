<?php

namespace Ulams\CourseBuilder\StyleProfile;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\CourseBuilder\Exceptions\BuilderException;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * Creates, edits and approves style profiles (ADR 0196). Nothing here changes a course or a theme: an approved
 * profile is data that later steps read (the theme screen's diff, the generation prompt, the style check).
 */
final class StyleProfileService
{
    public function __construct(
        private readonly SourceIngestor $ingestor,
        private readonly StyleProfileExtractor $extractor,
        private readonly StyleProfileCatalogue $catalogue,
    ) {
    }

    /**
     * A profile from an uploaded style guide: the file is ingested into a holder session, the model proposes the
     * values (when AI is on), the author reviews. Without AI, or when the call fails, the profile starts empty with
     * a warning and the author fills it by hand.
     *
     * @throws UploadRejected
     */
    public function createFromUpload(Authenticatable $user, UploadedFile $file, ?string $name, string $scopeType = StyleProfile::TENANT, ?string $scopeId = null): StyleProfile
    {
        $title = $name !== null && trim($name) !== '' ? trim($name) : mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 120);
        $holder = Session::query()->create(['author_id' => $user->getAuthIdentifier(), 'title' => "Style guide: {$title}", 'status' => Session::STYLE_GUIDE]);
        try {
            $source = $this->ingestor->store($holder, $file);
            $this->ingestor->ingest($source);
        } catch (Throwable $e) {
            $holder->forceDelete();

            throw $e;
        }

        $profile = StyleProfile::query()->create([
            'scope_type' => $scopeType, 'scope_id' => $scopeId, 'name' => $title, 'source_id' => $source->id, 'session_id' => $holder->id,
            'profile' => StyleProfileSanitizer::empty(), 'warnings' => [], 'status' => StyleProfile::SUGGESTED, 'created_by' => $user->getAuthIdentifier(),
        ]);
        if (!$this->extractor->enabled()) {
            return $this->save($profile, StyleProfileSanitizer::empty(), ['AI is off on this installation: fill the profile in by hand.']);
        }
        try {
            $proposal = $this->extractor->extract($holder, $profile->id, (int) $user->getAuthIdentifier());
        } catch (LlmException $e) {
            return $this->save($profile, StyleProfileSanitizer::empty(), ['The style guide could not be read automatically (' . $e->reason . '): fill the profile in by hand.']);
        }

        return $this->save($profile, $proposal['profile'], $proposal['warnings']);
    }

    /**
     * A profile written by hand, or a revision of an existing one (`$from`): same style guide, new values, cited by
     * the author or not at all. A revision of an approved profile is how a rule changes; approving it supersedes the old.
     *
     * @param array<string,mixed> $values
     */
    public function createManual(Authenticatable $user, ?string $name, array $values, ?StyleProfile $from, string $scopeType = StyleProfile::TENANT, ?string $scopeId = null): StyleProfile
    {
        $title = $name !== null && trim($name) !== '' ? trim($name) : ($from?->name ?? 'Style profile');
        $profile = StyleProfile::query()->create([
            'scope_type' => $from?->scope_type ?? $scopeType, 'scope_id' => $from ? $from->scope_id : $scopeId, 'name' => mb_substr($title, 0, 255),
            'source_id' => $from?->source_id, 'session_id' => $from?->session_id,
            'profile' => StyleProfileSanitizer::empty(), 'warnings' => [], 'status' => StyleProfile::SUGGESTED, 'created_by' => $user->getAuthIdentifier(),
        ]);

        return $this->edit($profile, $values === [] && $from !== null ? $from->profile : $values, $name);
    }

    /**
     * The author's edit of a suggested profile. Values keep their citations when they cite a fragment of the style
     * guide; a value without one is the author's own.
     *
     * @param array<string,mixed> $values
     * @throws BuilderException when the profile is no longer a suggestion
     */
    public function edit(StyleProfile $profile, array $values, ?string $name = null): StyleProfile
    {
        if ($profile->status !== StyleProfile::SUGGESTED) {
            throw new BuilderException('An approved profile is not edited: create a revision (POST with `from`) and approve that.', 409);
        }
        $result = $this->sanitiser($profile, false)->sanitize($values);
        if ($name !== null && trim($name) !== '') {
            $profile->name = mb_substr(trim($name), 0, 255);
        }

        return $this->save($profile, $result['profile'], $result['warnings']);
    }

    /**
     * Approves a profile: the approved profile of its scope is superseded, in one transaction, so a scope never has
     * two approved profiles.
     *
     * @throws BuilderException when it is not a suggestion, or is empty
     */
    public function approve(StyleProfile $profile, Authenticatable $user): StyleProfile
    {
        if ($profile->status !== StyleProfile::SUGGESTED) {
            throw new BuilderException('Only a suggested profile can be approved.', 409);
        }
        if (count((array) $profile->profile) <= 1) {
            throw new BuilderException('The profile has no values yet: add at least one before approving it.', 422);
        }

        return DB::transaction(function () use ($profile, $user) {
            StyleProfile::query()->where('scope_type', $profile->scope_type)->where('scope_id', $profile->scope_id)
                ->where('status', StyleProfile::APPROVED)->where('id', '!=', $profile->id)->update(['status' => StyleProfile::SUPERSEDED]);
            $profile->forceFill(['status' => StyleProfile::APPROVED, 'approved_by' => $user->getAuthIdentifier(), 'approved_at' => now()])->save();

            return $profile;
        });
    }

    public function delete(StyleProfile $profile): void
    {
        if ($profile->status === StyleProfile::APPROVED) {
            throw new BuilderException('An approved profile cannot be deleted: approve a revision to replace it.', 409);
        }
        $profile->delete();
    }

    /** @param array<string,mixed> $values @param string[] $warnings */
    private function save(StyleProfile $profile, array $values, array $warnings): StyleProfile
    {
        $profile->forceFill(['profile' => $values, 'warnings' => $warnings])->save();

        return $profile;
    }

    private function sanitiser(StyleProfile $profile, bool $requireCitations): StyleProfileSanitizer
    {
        $known = $profile->session_id === null ? [] : array_fill_keys(Fragment::query()->whereIn('source_id', Session::query()->find($profile->session_id)?->sources()->pluck('id') ?? [])->pluck('id')->all(), true);

        return new StyleProfileSanitizer($known, $this->catalogue->components(), $requireCitations);
    }

    /**
     * The profile as the API shows it: values, warnings, the passages it cites (so a reviewer who did not upload the
     * guide can read them) and, for a suggestion, the changes against the approved profile of its scope.
     *
     * @return array<string,mixed>
     */
    public function present(StyleProfile $profile, bool $detail = false): array
    {
        $out = [
            'id' => $profile->id,
            'name' => $profile->name,
            'scope' => ['type' => $profile->scope_type, 'id' => $profile->scope_id],
            'status' => $profile->status,
            'sourceId' => $profile->source_id,
            'profile' => $profile->profile,
            'warnings' => (array) $profile->warnings,
            'createdBy' => $profile->created_by,
            'approvedBy' => $profile->approved_by,
            'approvedAt' => $profile->approved_at?->toIso8601String(),
            'createdAt' => $profile->created_at?->toIso8601String(),
        ];
        if (!$detail) {
            return $out;
        }
        $current = StyleProfile::current($profile->scope_type, $profile->scope_id);
        $against = $current !== null && $current->id !== $profile->id ? $current : null;
        $out['current'] = $against === null ? null : ['id' => $against->id, 'name' => $against->name, 'profile' => $against->profile];
        $out['diff'] = $profile->status === StyleProfile::SUGGESTED || $against !== null
            ? StyleProfileDiff::between($against?->profile ?? StyleProfileSanitizer::empty(), (array) $profile->profile)
            : [];
        $out['fragments'] = $this->cited($profile);

        return $out;
    }

    /** @return array<string,array{label:string,preview:string}> */
    private function cited(StyleProfile $profile): array
    {
        $ids = [];
        $values = (array) $profile->profile;
        array_walk_recursive($values, function ($value, $key) use (&$ids) {
            if (is_string($value) && preg_match('/^frg_[a-z2-7]{12}$/', $value)) {
                $ids[$value] = true;
            }
        });
        $out = [];
        foreach (Fragment::query()->whereIn('id', array_keys($ids))->get() as $f) {
            try {
                $out[$f->id] = ['label' => $f->label(), 'preview' => mb_substr($f->text, 0, 240)];
            } catch (Throwable) {
            }
        }

        return $out;
    }
}
