<?php

namespace Ulams\Consultations\Services;

use Auth;
use Carbon\Carbon;
use DateTime;
use Ulams\Consultations\Dto\ChangeTermConsultationDto;
use Ulams\Consultations\Dto\ConsultationUserTermDto;
use Ulams\Consultations\Dto\ConsultationDto;
use Ulams\Consultations\Dto\ConsultationSaveScreenDto;
use Ulams\Consultations\Dto\FilterConsultationTermsListDto;
use Ulams\Consultations\Dto\FilterListDto;
use Ulams\Consultations\Dto\FilterScheduleForTutorDto;
use Ulams\Consultations\Dto\FinishTermDto;
use Ulams\Consultations\Dto\GenerateSignedScreenUrlsDto;
use Ulams\Consultations\Enum\ConstantEnum;
use Ulams\Consultations\Enum\ConsultationStatusEnum;
use Ulams\Consultations\Enum\ConsultationTermStatusEnum;
use Ulams\Consultations\Events\ApprovedTerm;
use Ulams\Consultations\Events\ApprovedTermWithTrainer;
use Ulams\Consultations\Events\ChangeTerm;
use Ulams\Consultations\Events\RejectTerm;
use Ulams\Consultations\Events\RejectTermWithTrainer;
use Ulams\Consultations\Events\ReminderAboutTerm;
use Ulams\Consultations\Events\ReminderTrainerAboutTerm;
use Ulams\Consultations\Events\ReportTerm;
use Ulams\Consultations\Exceptions\ChangeTermException;
use Ulams\Consultations\Exceptions\ConsultationNotFound;
use Ulams\Consultations\Helpers\StrategyHelper;
use Ulams\Consultations\Http\Requests\ListConsultationsRequest;
use Ulams\Consultations\Http\Resources\ConsultationSimpleResource;
use Ulams\Consultations\Models\Consultation;
use Ulams\Consultations\Models\ConsultationProposedTerm;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Ulams\Consultations\Models\ConsultationUserTerm;
use Ulams\Consultations\Models\User;
use Ulams\Consultations\Repositories\Contracts\ConsultationRepositoryContract;
use Ulams\Consultations\Repositories\Contracts\ConsultationUserRepositoryContract;
use Ulams\Consultations\Repositories\Contracts\ConsultationUserTermRepositoryContract;
use Ulams\Consultations\Services\Contracts\ConsultationServiceContract;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Files\Helpers\FileHelper;
use Ulams\Jitsi\Helpers\StringHelper;
use Ulams\Jitsi\Services\Contracts\JitsiServiceContract;
use Ulams\ModelFields\Facades\ModelFields;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ConsultationService implements ConsultationServiceContract
{
    private ConsultationRepositoryContract $consultationRepositoryContract;
    private ConsultationUserRepositoryContract $consultationUserRepositoryContract;
    private JitsiServiceContract $jitsiServiceContract;
    protected ConsultationUserTermRepositoryContract $consultationUserTermRepository;

    public function __construct(
        ConsultationRepositoryContract $consultationRepositoryContract,
        ConsultationUserRepositoryContract $consultationUserRepositoryContract,
        JitsiServiceContract $jitsiServiceContract,
        ConsultationUserTermRepositoryContract $consultationUserTermRepository,
    ) {
        $this->consultationRepositoryContract = $consultationRepositoryContract;
        $this->consultationUserRepositoryContract = $consultationUserRepositoryContract;
        $this->jitsiServiceContract = $jitsiServiceContract;
        $this->consultationUserTermRepository = $consultationUserTermRepository;
    }

    public function getConsultationsList(array $search = [], bool $onlyActive = false, OrderDto $orderDto = null): Builder
    {
        if ($onlyActive) {
            $now = now()->format('Y-m-d');
            $search['active_to'] = isset($search['active_to']) ? Carbon::make($search['active_to'])->format('Y-m-d') : $now;
            $search['active_from'] = isset($search['active_from']) ? Carbon::make($search['active_from'])->format('Y-m-d') : $now;
            $search['status'] = [ConsultationStatusEnum::PUBLISHED];
        }
        $criteria = FilterListDto::prepareFilters($search);
        return $this->consultationRepositoryContract->allQueryBuilder(
            $search,
            $criteria
        )->orderBy($orderDto->getOrderBy() ?? 'created_at', $orderDto->getOrder() ?? 'desc');
    }

    public function getConsultationsListForCurrentUser(array $search = []): Builder
    {
        $now = now();
        $search['active_to'] = isset($search['active_to']) ? Carbon::make($search['active_to']) : $now;
        $search['active_from'] = isset($search['active_from']) ? Carbon::make($search['active_from']) : $now;
        $criteria = FilterListDto::prepareFilters($search);
        return $this->consultationRepositoryContract->forCurrentUser(
            $search,
            $criteria
        );
    }

    public function store(ConsultationDto $consultationDto): Consultation
    {
        return DB::transaction(function () use($consultationDto) {
            /** @var Consultation $consultation */
            $consultation = $this->consultationRepositoryContract->create($consultationDto->toArray());
            $this->setRelations($consultation, $consultationDto->getRelations());
            $this->setFiles($consultation, $consultationDto->getFiles());
            $consultation->save();
            return $consultation;
        });
    }

    public function update(int $id, ConsultationDto $consultationDto): Consultation
    {
        $consultation = $this->show($id);
        return DB::transaction(function () use($consultation, $consultationDto) {
            $this->setFiles($consultation, $consultationDto->getFiles());
            $consultation = $this->consultationRepositoryContract->updateModel($consultation, $consultationDto->toArray());
            $this->setRelations($consultation, $consultationDto->getRelations());
            return $consultation;
        });
    }

    public function show(int $id): Consultation
    {
        /** @var Consultation|null $consultation */
        $consultation = $this->consultationRepositoryContract->find($id);
        if (!$consultation) {
            throw new ConsultationNotFound();
        }
        return $consultation;
    }

    public function delete(int $id): ?bool
    {
        return DB::transaction(function () use($id) {
            return $this->consultationRepositoryContract->delete($id);
        });
    }

    public function reportTerm(int $consultationTermId, string $executedAt): bool
    {
        return DB::transaction(function () use ($consultationTermId, $executedAt) {
            /** @var ConsultationUserPivot $consultationTerm */
            $consultationTerm = $this->consultationUserRepositoryContract->find($consultationTermId);
            if ($this->termIsBusy($consultationTerm->consultation_id, $executedAt)) {
                abort(400, __('Term is busy, change your term.'));
            }
            $data = [
                'executed_status' => ConsultationTermStatusEnum::REPORTED,
                'executed_at' => Carbon::make($executedAt)
            ];

            $userTerm = $this->consultationUserTermRepository->createUserTerm($consultationTerm, $data);

            $consultationTerm->consultation->teachers
                ->when(
                    $consultationTerm->consultation->author_id !== null,
                    fn ($teachers) => $teachers->push($consultationTerm->consultation->author)
                )
                ->filter()
                ->each(function ($author) use ($consultationTerm, $userTerm) {
                    event(new ReportTerm($author, $consultationTerm, $userTerm));
                });

            return true;
        });
    }

    public function approveTerm(int $consultationTermId, ConsultationUserTermDto $dto): bool
    {
        /** @var ConsultationUserPivot $consultationTerm */
        $consultationTerm = $this->consultationUserRepositoryContract->find($consultationTermId);

        /** @var User $authUser */
        $authUser = auth()->user();

        $userTerms = $dto->getUserId() ? collect([$this->consultationUserTermRepository->getUserTermByUserIdAndExecutedAt($dto->getUserId(), $dto->getTerm())])
            : $this->consultationUserTermRepository->getAllUserTermsByConsultationIdAndExecutedAt($consultationTerm->consultation_id, $dto->getTerm());

        DB::transaction(function () use ($userTerms, $authUser) {
            /** @var ConsultationUserTerm $userTerm */
            foreach ($userTerms as $userTerm) {
                /** @var ConsultationUserTerm $userTerm */
                $userTerm = $this->consultationUserTermRepository->update(['executed_status' => ConsultationTermStatusEnum::APPROVED], $userTerm->getKey());
                event(new ApprovedTerm($userTerm->consultationUser->user, $userTerm->consultationUser, $userTerm));
                event(new ApprovedTermWithTrainer($authUser, $userTerm->consultationUser, $userTerm));
            }
        });

        return true;
    }

    public function rejectTerm(int $consultationTermId, ConsultationUserTermDto $dto): bool
    {
        /** @var ConsultationUserPivot $consultationTerm */
        $consultationTerm = $this->consultationUserRepositoryContract->find($consultationTermId);

        /** @var User $authUser */
        $authUser = auth()->user();

        $userTerms = $dto->getUserId() ? collect([$this->consultationUserTermRepository->getUserTermByUserIdAndExecutedAt($dto->getUserId(), $dto->getTerm())])
            : $this->consultationUserTermRepository->getAllUserTermsByConsultationIdAndExecutedAt($consultationTerm->consultation_id, $dto->getTerm());

        DB::transaction(function () use ($userTerms, $authUser) {
            /** @var ConsultationUserTerm $userTerm */
            foreach ($userTerms as $userTerm) {
                /** @var ConsultationUserTerm $userTerm */
                $userTerm = $this->consultationUserTermRepository->update(['executed_status' => ConsultationTermStatusEnum::REJECT], $userTerm->getKey());
                event(new RejectTerm($userTerm->consultationUser->user, $userTerm->consultationUser, $userTerm));
                event(new RejectTermWithTrainer($authUser, $userTerm->consultationUser, $userTerm));
            }
        });

        return true;
    }

    public function setStatus(ConsultationUserPivot $consultationTerm, string $status, string $executedAt): ConsultationUserTerm
    {
        return DB::transaction(function () use ($status, $consultationTerm, $executedAt) {
            return $this->consultationUserTermRepository->updateUserTermByExecutedAt($consultationTerm, $executedAt, ['executed_status' => $status]);
        });
    }

    public function generateJitsi(int $consultationTermId, ConsultationUserTermDto $dto): array
    {
        /** @var ConsultationUserPivot $consultationTerm */
        $consultationTerm = $this->consultationUserRepositoryContract->find($consultationTermId);
        /** @var ConsultationUserTerm $term */
        $term = $consultationTerm->userTerms()->where('executed_at', '=', $dto->getTerm())->firstOrFail();
        if (!$this->canGenerateJitsi(
            $term->executed_at,
            $term->executed_status,
            $consultationTerm->consultation->getDuration(),
            $consultationTerm->consultation,
        )) {
            throw new NotFoundHttpException(__('Consultation term is not available'));
        }
        $isModerator = false;
        $configOverwrite = [];
        $configInterface = [];

        $authorIds = $consultationTerm->consultation->teachers
            ->when(
                $consultationTerm->consultation->author_id !== null,
                fn ($teachers) => $teachers->push($consultationTerm->consultation->author)
            )
            ->pluck('id')
            ->toArray();

        if (in_array(auth()->user()->getKey(), $authorIds)) {
            $configOverwrite = [
                "disableModeratorIndicator" => true,
                "startScreenSharing" => false,
                "enableEmailInStats" => false,
            ];
            $isModerator = true;
        }
        if ($consultationTerm->consultation->logotype_path) {
            $configInterface = [
                'BRAND_WATERMARK_LINK' => '',
                'DEFAULT_LOGO_URL' => $consultationTerm->consultation->logotype_url,
                'DEFAULT_WELCOME_PAGE_LOGO_URL' => $consultationTerm->consultation->logotype_url,
                'HIDE_INVITE_MORE_HEADER' => true
            ];
        }
        /** @var User $authUser */
        $authUser = auth()->user();
        return $this->jitsiServiceContract->getChannelData(
            $authUser,
            StringHelper::convertToJitsiSlug($consultationTerm->consultation->name, [], ConstantEnum::DIRECTORY, $consultationTerm->consultation_id, (string) Carbon::make($term->executed_at)->getTimestamp()),
            $isModerator,
            $configOverwrite,
            $configInterface
        );
    }

    public function canGenerateJitsi(?string $executedAt, ?string $status, ?string $duration, ?Consultation $consultation = null): bool
    {
        $now = now();
        if (isset($executedAt)) {
            $dateTo = Carbon::make($executedAt);
            if ($now->getTimestamp() >= $dateTo->getTimestamp() && !$this->isEnded($executedAt, $duration)) {
                if ($consultation && (Auth::user()->getKey() === $consultation->author_id || in_array(Auth::user()->getKey(), $consultation->teachers()->pluck('users.id')->toArray()))) {
                    $terms = $this->consultationUserTermRepository->getAllUserTermsByConsultationIdAndExecutedAt($consultation->getKey(), $executedAt);

                    foreach ($terms as $term) {
                        if ($term->executed_status === ConsultationTermStatusEnum::APPROVED) {
                            return true;
                        }
                    }
                } else {
                    return $status === ConsultationTermStatusEnum::APPROVED;
                }
            }
        }
        return false;
    }

    public function generateJitsiUrlForEmail(int $consultationTermId, int $userId, string $executedAt): ?string
    {
        /** @var ConsultationUserPivot $consultationTerm */
        $consultationTerm = $this->consultationUserRepositoryContract->find($consultationTermId);
        /** @var ConsultationUserTerm $term */
        $term = $consultationTerm->userTerms()->where('executed_at', '=', $executedAt)->firstOrFail();
        $isModerator = false;
        $configOverwrite = [];
        $configInterface = [];
        if ($consultationTerm->consultation->author->getKey() === $userId || in_array($userId, $consultationTerm->consultation->teachers()->pluck('users.id')->toArray())) {
            $configOverwrite = [
                "disableModeratorIndicator" => true,
                "startScreenSharing" => false,
                "enableEmailInStats" => false,
            ];
            $isModerator = true;
        }
        if ($consultationTerm->consultation->logotype_path) {
            $configInterface = [
                'BRAND_WATERMARK_LINK' => '',
                'DEFAULT_LOGO_URL' => $consultationTerm->consultation->logotype_url,
                'DEFAULT_WELCOME_PAGE_LOGO_URL' => $consultationTerm->consultation->logotype_url,
                'HIDE_INVITE_MORE_HEADER' => true
            ];
        }
        $user = User::find($userId);
        $result = $this->jitsiServiceContract->getChannelData(
            $user,
            StringHelper::convertToJitsiSlug($consultationTerm->consultation->name, [], ConstantEnum::DIRECTORY, $consultationTerm->consultation_id, (string) Carbon::make($term->executed_at)->getTimestamp()),
            $isModerator,
            $configOverwrite,
            $configInterface
        );
        return key_exists('url', $result) ? $result['url'] : null;
    }

    public function generateDateTo(string $dateTo, string $duration): ?Carbon
    {
        $modifyTimeStrings = [
            'seconds', 'second', 'minutes', 'minute', 'hours', 'hour', 'weeks', 'week', 'years', 'year'
        ];
        $explode = array_filter(explode(' ', $duration));
        $count = $explode[0] ?? 0;
        $string = $explode[1] ?? 'hours';
        $string = in_array($string, $modifyTimeStrings) ? $string : 'hours';
        return Carbon::make($dateTo)->modify('+' . ((int)$count) . ' ' . $string);
    }

    public function setRelations(Consultation $consultation, array $relations = []): void
    {
        foreach ($relations as $key => $value) {
            $className = 'ConsultationWith' . ucfirst($key) . 'Strategy';
            StrategyHelper::useStrategyPattern(
                $className,
                'RelationsStrategy',
                'setRelation',
                $consultation,
                $relations
            );
        }
    }

    public function proposedTerms(int $consultationTermId): ?array
    {
        /** @var ConsultationUserPivot $consultationUserPivot */
        $consultationUserPivot = $this->consultationUserRepositoryContract->find($consultationTermId);
        return $this->filterProposedTerms($consultationUserPivot->consultation_id, $consultationUserPivot->consultation->proposedTerms);
    }

    public function setFiles(Consultation $consultation, array $files = []): void
    {
        foreach ($files as $key => $file) {
            $consultation->$key = FileHelper::getFilePath($file, ConstantEnum::DIRECTORY . "/{$consultation->getKey()}/images");
        }
    }

    public function getConsultationTermsByConsultationId(int $consultationId, array $search = []): Collection
    {
        $filterConsultationTermsDto = FilterConsultationTermsListDto::prepareFilters(
            array_merge($search, ['consultation_id' => $consultationId])
        );

        return $this->consultationUserTermRepository
            ->allQueryBuilder($filterConsultationTermsDto);
    }

    public function forCurrentUserResponse(
        ListConsultationsRequest $listConsultationsRequest
    ): AnonymousResourceCollection {
        $search = $listConsultationsRequest->except(['limit', 'skip', 'order', 'order_by', 'paginate']);
        $consultations = $this->getConsultationsListForCurrentUser($search);
        if ($listConsultationsRequest->input('paginate', false)) {
            $consultationsCollection = ConsultationSimpleResource::collection($consultations->paginate(
                $listConsultationsRequest->get('per_page') ??
                config('ulams_consultations.perPage', ConstantEnum::PER_PAGE)
            ));
        } else {
            $consultationsCollection = ConsultationSimpleResource::collection($consultations->get());
        }

        ConsultationSimpleResource::extend(function (ConsultationSimpleResource $consultation) {
            return [
                'consultation_term_id' => $consultation->resource->consultation_user_id,
                'name' => $consultation->resource->name,
                'image_path' => $consultation->resource->image_path,
                'image_url' => $consultation->resource->image_url,
                'executed_status' => $consultation->resource->executed_status,
                'executed_at' => Carbon::make($consultation->resource->executed_at),
                'is_started' => $this->isStarted(
                    $consultation->resource->executed_at,
                    $consultation->resource->executed_status,
                    $consultation->resource->getDuration()
                ),
                'is_ended' => $this->isEnded($consultation->resource->executed_at, $consultation->resource->getDuration()),
                'in_coming' => $this->inComing(
                    $consultation->resource->executed_at,
                    $consultation->resource->executed_status,
                    $consultation->resource->getDuration()
                ),
            ];
        });
        return $consultationsCollection;
    }

    public function attachToUser(array $data, ?array $termData): void
    {
        /** @var ConsultationUserPivot $consultationUser */
        $consultationUser = $this->consultationUserRepositoryContract->create($data);
        if ($termData) {
            $consultationUser->userTerms()->create($termData);
        }
    }

    public function isEnded(?string $executedAt, ?string $duration): bool
    {
        if ($executedAt && $duration !== '') {
            $dateTo = $this->generateDateTo($executedAt, $duration);
            return $dateTo->getTimestamp() <= now()->getTimestamp();
        }
        return false;
    }

    public function isStarted(?string $executedAt, ?string $status, ?string $duration): bool
    {
        return $this->canGenerateJitsi($executedAt, $status, $duration);
    }

    public function inComing(?string $executedAt, ?string $status, ?string $duration): bool
    {
        return !$this->isStarted($executedAt, $status, $duration) && !$this->isEnded($executedAt, $duration);
    }

    public function reminderAboutConsultation(string $reminderStatus): void
    {
        /** @var ConsultationUserTerm $userTerm */
        foreach ($this->getReminderData($reminderStatus) as $userTerm) {
            event(new ReminderAboutTerm(
                $userTerm->consultationUser->user,
                $userTerm->consultationUser,
                $reminderStatus,
                $userTerm
            ));
            $consultation = $userTerm->consultationUser->consultation;
            if ($consultation->teachers->count() > 0) {
                $consultation->teachers->each(
                    fn (User $teacher) => event(new ReminderTrainerAboutTerm(
                        $teacher,
                        $userTerm->consultationUser,
                        $reminderStatus,
                        $userTerm
                    ))
                );
            } else {
                event(new ReminderTrainerAboutTerm(
                    $consultation->author,
                    $userTerm->consultationUser,
                    $reminderStatus,
                    $userTerm
                ));
            }
        }
    }

    public function setReminderStatus(ConsultationUserPivot $consultationTerm, string $status, ?ConsultationUserTerm $userTerm = null): void
    {
        if ($userTerm) {
            $this->consultationUserTermRepository->updateModel($userTerm, ['reminder_status' => $status]);
        } else {
            $this->consultationUserRepositoryContract->updateModel($consultationTerm, ['reminder_status' => $status]);
        }
    }

    public function changeTerm(int $consultationTermId, ChangeTermConsultationDto $dto): bool
    {
        DB::transaction(function () use ($consultationTermId, $dto) {
            try {
                /** @var ConsultationUserPivot $consultationTerm */
                $consultationTerm = $this->consultationUserRepositoryContract->find($consultationTermId);

                $userTerms = $dto->getUserId() ? collect([$this->consultationUserTermRepository->getUserTermByUserIdAndExecutedAt($dto->getUserId(), $dto->getTerm())])
                    : $this->consultationUserTermRepository->getAllUserTermsByConsultationIdAndExecutedAt($consultationTerm->consultation_id, $dto->getTerm());

                /** @var ConsultationUserTerm $userTerm */
                foreach ($userTerms as $userTerm) {
                    /** @var ConsultationUserTerm $consultationUserTerm */
                    $consultationUserTerm = $this->consultationUserTermRepository->update([
                        'executed_at' => $dto->getExecutedAt(),
                        'executed_status' => $dto->getAccept() ? ConsultationTermStatusEnum::APPROVED : ConsultationTermStatusEnum::REPORTED,
                    ], $userTerm->getKey());

                    if (!$consultationUserTerm->consultationUser->user) {
                        throw new ChangeTermException(__('Term is changed but not executed event because user or term is no exists'));
                    }
                    event(new ChangeTerm($consultationUserTerm->consultationUser->user, $consultationUserTerm->consultationUser, $consultationUserTerm));
                }
                return true;
            } catch (Exception $e) {
                throw new ChangeTermException(__('Term is not changed'));
            }
        });
        return false;
    }

    public function getConsultationTermsForTutor(?FilterScheduleForTutorDto $filterDto = null): Collection
    {
        return $this->consultationUserTermRepository->getByCurrentUserTutor($filterDto?->getCriteria() ?? []);
    }

    public function termIsBusy(int $consultationId, string $date): bool
    {
        /** @var Consultation $consultation */
        $consultation = $this->consultationRepositoryContract->find($consultationId);
        $terms = $this->consultationUserRepositoryContract->getBusyTerms($consultationId, $date);
        $userId = Auth::user()->getKey();
        if ($terms->first(fn (ConsultationUserPivot $userPivot) => $userPivot->user_id === $userId) !== null) {
            abort(400, __('You already reported this term.'));
        }

        return $terms->count() >= $consultation->max_session_students;
    }

    public function termIsBusyForUser(int $consultationId, string $date, int $userId): bool
    {
        /** @var Consultation $consultation */
        $consultation = $this->consultationRepositoryContract->find($consultationId);
        $terms = $this->consultationUserRepositoryContract->getBusyTerms($consultationId, $date);
        if ($terms->first(fn (ConsultationUserPivot $userPivot) => $userPivot->user_id === $userId) !== null) {
            abort(400, __('Term is busy for this user.'));
        }

        return $terms->count() >= $consultation->max_session_students;
    }

    public function getBusyTermsFormatDate(int $consultationId): array
    {
        return $this->consultationUserTermRepository->getBusyTerms($consultationId)->map(
            // @phpstan-ignore-next-line
            fn (ConsultationUserTerm $term) => Carbon::make($term->executed_at)
        )->unique()->toArray();
    }

    public function filterProposedTerms(int $consultationId, Collection $proposedTerms): array
    {
        $busyTerms = $this->getBusyTermsFormatDate($consultationId);
        return $proposedTerms->map(fn(ConsultationProposedTerm $proposedTerm) => Carbon::make($proposedTerm->proposed_at))->filter(fn (Carbon $proposedTerm) => !in_array($proposedTerm, $busyTerms))->toArray();
    }

    public function updateModelFieldsFromRequest(Consultation $consultation, FormRequest $request): void
    {
        $keys = ModelFields::getFieldsMetadata(Consultation::class)->pluck('name');
        $fields = $request->collect()->only($keys)->toArray();
        $this->consultationRepositoryContract->update($fields, $consultation->getKey());
    }

    private function getReminderData(string $reminderStatus): Collection
    {
        $dateTimeFrom = now()
            ->modify(config('ulams_consultations.modifier_date.' . $reminderStatus, '+1 hour'))
            ->subMinutes(30);
        $dateTimeTo = now()
            ->modify(config('ulams_consultations.modifier_date.' . $reminderStatus, '+1 hour'))
            ->addMinutes(30);
        $exclusionStatuses = config('ulams_consultations.exclusion_reminder_status.' . $reminderStatus, []);
        $data = [
            'date_time_to' => $dateTimeTo,
            'date_time_from' => $dateTimeFrom,
            'reminder_status' => $exclusionStatuses,
            'status' => [ConsultationTermStatusEnum::APPROVED]
        ];

        return $this->consultationUserTermRepository->allQueryBuilder(FilterConsultationTermsListDto::prepareFilters($data));
    }

    public function saveScreen(ConsultationSaveScreenDto $dto): void
    {
        $userId = $dto->getUserId()
            ?? User::query()
                ->select(['id'])
                ->where('email', '=', $dto->getUserEmail())
                ->firstOrFail()
                ->getKey();

        $term = Carbon::make($dto->getExecutedAt());
        // consultation_id/term_start_timestamp/user_id/timestamp.jpg
        $folder = ConstantEnum::DIRECTORY . "/{$dto->getConsultationId()}/{$term->getTimestamp()}/{$userId}";

        foreach ($dto->getFiles() as $file) {
            $screen = $file['file'];
            $extension = $screen instanceof UploadedFile ? $screen->getClientOriginalExtension() : Str::between($screen, 'data:image/', ';base64');
            Storage::putFileAs($folder, $screen, Carbon::make($file['timestamp'])->getTimestamp() . '.' . $extension);
        }
    }

    public function generateSignedScreenUrls(GenerateSignedScreenUrlsDto $dto): array
    {
        if (config('filesystems.default') !== 's3') {
            abort(400, 'The file driver does not support this method.');
        }

        $term = Carbon::make($dto->getExecutedAt());
        $directory = sprintf(
            '%s/%s/%s/%s/',
            ConstantEnum::DIRECTORY,
            $dto->getConsultationId(),
            $term->getTimestamp(),
            $dto->getUserId()
        );

        return array_map(function ($file) use ($directory) {
            $filename = $file['filename'];

            if (config('cache.default') === 'redis') {
                $key = 'signed_urls:' . md5($directory . $filename);
                Redis::command('SETEX', [$key, ConstantEnum::REDIS_IMAGES_TTL, $directory . $filename]);
                Redis::command('HSET', [ConstantEnum::REDIS_IMAGES_KEY, $key, 1]);
                Redis::command('EXPIRE', [ConstantEnum::REDIS_IMAGES_KEY, ConstantEnum::REDIS_IMAGES_TTL]);
            }

            return array_merge(
                ['filename' => $filename],
                Storage::temporaryUploadUrl($directory . $filename, now()->addMinutes(5))
            );
        }, $dto->getFiles());
    }

    public function finishTerm(int $consultationTermId, FinishTermDto $dto): bool
    {
        /** @var ConsultationUserPivot $consultationTerm */
        $consultationTerm = $this->consultationUserRepositoryContract->find($consultationTermId);

        $userTerms = $this->consultationUserTermRepository->getAllUserTermsByConsultationIdAndExecutedAt($consultationTerm->consultation_id, $dto->getTerm());

        $this->finishTerms($userTerms, $dto->getFinishedAt() ?? now());

        return true;
    }

    public function finishTerms(Collection $usersTerm, DateTime $finishedAt): void
    {
        DB::transaction(function () use ($usersTerm, $finishedAt) {
            $this->consultationUserTermRepository->updateModels($usersTerm, ['finished_at' => $finishedAt]);
        });
    }
}
