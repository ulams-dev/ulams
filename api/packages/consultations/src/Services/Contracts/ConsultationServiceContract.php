<?php

namespace EscolaLms\Consultations\Services\Contracts;

use Carbon\Carbon;
use EscolaLms\Consultations\Dto\ChangeTermConsultationDto;
use EscolaLms\Consultations\Dto\ConsultationUserTermDto;
use EscolaLms\Consultations\Dto\ConsultationDto;
use EscolaLms\Consultations\Dto\ConsultationSaveScreenDto;
use EscolaLms\Consultations\Dto\FilterScheduleForTutorDto;
use EscolaLms\Consultations\Dto\FinishTermDto;
use EscolaLms\Consultations\Dto\GenerateSignedScreenUrlsDto;
use EscolaLms\Consultations\Http\Requests\ListConsultationsRequest;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Models\ConsultationUserTerm;
use EscolaLms\Core\Dtos\OrderDto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

interface ConsultationServiceContract
{
    public function getConsultationsList(array $search = [], bool $onlyActive = false, OrderDto $orderDto = null): Builder;
    public function store(ConsultationDto $consultationDto): Consultation;
    public function update(int $id, ConsultationDto $consultationDto): Consultation;
    public function show(int $id): Consultation;
    public function delete(int $id): ?bool;
    public function attachToUser(array $data, ?array $termData): void;
    public function reportTerm(int $orderItemId, string $executedAt): bool;
    public function approveTerm(int $consultationTermId, ConsultationUserTermDto $dto): bool;
    public function rejectTerm(int $consultationTermId, ConsultationUserTermDto $dto): bool;
    public function setStatus(ConsultationUserPivot $consultationTerm, string $status, string $executedAt): ConsultationUserTerm;
    public function generateJitsi(int $consultationTermId, ConsultationUserTermDto $dto): array;
    public function canGenerateJitsi(?string $executedAt, ?string $status, ?string $duration, ?Consultation $consultation): bool;
    public function generateJitsiUrlForEmail(int $consultationTermId, int $userId, string $executedAt): ?string;
    public function proposedTerms(int $consultationTermId): ?array;
    public function setRelations(Consultation $consultation, array $relations = []): void;
    public function setFiles(Consultation $consultation, array $files = []): void;
    public function getConsultationTermsByConsultationId(int $consultationId, array $search = []): Collection;
    public function getConsultationsListForCurrentUser(array $search = []): Builder;

    /**
     * @OA\Schema(
     *      schema="ConsultationTermForUserCurrent",
     *      @OA\Property(
     *          property="name",
     *          description="name",
     *          type="string",
     *      ),
     *      @OA\Property(
     *          property="image_path",
     *          description="image_path",
     *          type="string",
     *      ),
     *      @OA\Property(
     *          property="image_url",
     *          description="image_url",
     *          type="string",
     *      ),
     *      @OA\Property(
     *          property="executed_status",
     *          description="executed_status",
     *          type="string",
     *      ),
     *      @OA\Property(
     *          property="executed_at",
     *          description="executed_at",
     *          type="datetime",
     *          example="2022-04-15T04:00:00.000Z",
     *      ),
     *      @OA\Property(
     *          property="is_ended",
     *          description="is_ended",
     *          type="boolean",
     *      ),
     *      @OA\Property(
     *          property="is_started",
     *          description="is_started",
     *          type="boolean",
     *      ),
     *      @OA\Property(
     *          property="in_coming",
     *          description="in_coming",
     *          type="boolean",
     *      ),
     *      @OA\Property(
     *          property="consultation_term_id",
     *          description="consultation_term_id",
     *          type="integer",
     *      ),
     * )
     *
     */
    public function forCurrentUserResponse(ListConsultationsRequest $listConsultationsRequest): AnonymousResourceCollection;
    public function generateDateTo(string $dateTo, string $duration): ?Carbon;
    public function isEnded(?string $executedAt, ?string $duration): bool;
    public function isStarted(?string $executedAt, ?string $status, ?string $duration): bool;
    public function inComing(?string $executedAt, ?string $status, ?string $duration): bool;
    public function reminderAboutConsultation(string $reminderStatus): void;
    public function setReminderStatus(ConsultationUserPivot $consultationTerm, string $status, ?ConsultationUserTerm $userTerm = null): void;
    public function changeTerm(int $consultationTermId, ChangeTermConsultationDto $dto): bool;
    public function getConsultationTermsForTutor(?FilterScheduleForTutorDto $filterDto = null): Collection;
    public function termIsBusy(int $consultationId, string $date): bool;
    public function termIsBusyForUser(int $consultationId, string $date, int $userId): bool;
    public function getBusyTermsFormatDate(int $consultationId): array;
    public function updateModelFieldsFromRequest(Consultation $consultation, FormRequest $request): void;
    public function saveScreen(ConsultationSaveScreenDto $dto): void;
    public function generateSignedScreenUrls(GenerateSignedScreenUrlsDto $dto): array;
    public function finishTerm(int $consultationTermId, FinishTermDto $dto): bool;
}
