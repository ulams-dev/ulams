<?php

namespace EscolaLms\Consultations\Dto;

use EscolaLms\Consultations\Dto\Contracts\ModelDtoContract;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Models\ConsultationUserTerm;
use EscolaLms\Consultations\Repositories\Criteria\UserExistsCriterion;
use EscolaLms\Consultations\Repositories\Criteria\UserTermConsultationCriterion;
use EscolaLms\Consultations\Repositories\Criteria\UserTermUserExistsCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\NotNullCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\WhereCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\WhereNotInOrIsNullCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\DateCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\InCriterion;

class FilterConsultationTermsListDto extends BaseDto implements ModelDtoContract
{
    private array $status;
    private array $reminderStatus;
    private string $dateTo;
    private string $dateFrom;
    private string $dateTimeTo;
    private string $dateTimeFrom;
    private int $consultationId;

    private array $criteria = [];

    public static function prepareFilters(array $search): self
    {
        $dto = new self($search);
        $dto->addToCriteria(new NotNullCriterion($dto->model()->getTable() . '.executed_at'));
        if ($dto->getStatus()) {
            $dto->addToCriteria(new InCriterion($dto->model()->getTable() . '.executed_status', $dto->getStatus()));
        }
        if ($dto->getDateFrom()) {
            $dto->addToCriteria(new DateCriterion($dto->model()->getTable() . '.executed_at', $dto->getDateFrom(), '>='));
        }
        if ($dto->getDateTo()) {
            $dto->addToCriteria(new DateCriterion($dto->model()->getTable() . '.executed_at', $dto->getDateTo(), '<='));
        }
        if ($dto->getDateTimeFrom()) {
            $dto->addToCriteria(new WhereCriterion($dto->model()->getTable() . '.executed_at', $dto->getDateTimeFrom(), '>='));
        }
        if ($dto->getDateTimeTo()) {
            $dto->addToCriteria(new WhereCriterion($dto->model()->getTable() . '.executed_at', $dto->getDateTimeTo(), '<='));
        }
        if ($dto->getConsultationId()) {
            $dto->addToCriteria(new UserTermConsultationCriterion($dto->getConsultationId()));
        }
        if ($dto->getReminderStatus()) {
            $dto->addToCriteria(new WhereNotInOrIsNullCriterion($dto->model()->getTable() . '.reminder_status', $dto->getReminderStatus()));
        }
        $dto->addToCriteria(new UserTermUserExistsCriterion());
        return $dto;
    }

    public function model(): ConsultationUserTerm
    {
        // @phpstan-ignore-next-line
        return ConsultationUserTerm::newModelInstance();
    }

    public function toArray($filters = false): array
    {
        return [];
    }

    public function getCriteria(): ?array
    {
        return $this->criteria ?? null;
    }

    public function getStatus(): ?array
    {
        return $this->status ?? null;
    }

    public function getDateFrom(): ?string
    {
        return $this->dateFrom ?? null;
    }

    public function getDateTo(): ?string
    {
        return $this->dateTo ?? null;
    }

    public function getDateTimeFrom(): ?string
    {
        return $this->dateTimeFrom ?? null;
    }

    public function getDateTimeTo(): ?string
    {
        return $this->dateTimeTo ?? null;
    }

    public function getConsultationId(): ?int
    {
        return $this->consultationId ?? null;
    }

    public function getReminderStatus(): ?array
    {
        return $this->reminderStatus ?? null;
    }

    protected function setReminderStatus(array $reminderStatus): void
    {
        $this->reminderStatus = $reminderStatus;
    }

    protected function setStatus(array $status): void
    {
        $this->status = $status;
    }

    protected function setDateFrom(string $dateFrom): void
    {
        $this->dateFrom = $dateFrom;
    }

    protected function setDateTo(string $dateTo): void
    {
        $this->dateTo = $dateTo;
    }

    protected function setDateTimeFrom(string $dateTimeFrom): void
    {
        $this->dateTimeFrom = $dateTimeFrom;
    }

    protected function setDateTimeTo(string $dateTimeTo): void
    {
        $this->dateTimeTo = $dateTimeTo;
    }

    protected function setConsultationId(int $consultationId): void
    {
        $this->consultationId = $consultationId;
    }

    private function addToCriteria($value): void
    {
        $this->criteria[] = $value;
    }
}
