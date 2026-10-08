<?php

namespace EscolaLms\Consultations\Dto;

use EscolaLms\Consultations\Dto\Contracts\ModelDtoContract;
use EscolaLms\Consultations\Models\ConsultationUserTerm;
use EscolaLms\Consultations\Repositories\Criteria\UserTermUserExistsCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\HasCriterion;
use Illuminate\Database\Eloquent\Builder;

class FilterScheduleForTutorDto extends BaseDto implements ModelDtoContract
{
    private array $ids = [];
    private array $criteria = [];

    public static function prepareFilters(array $search): self
    {
        $dto = new self($search);

        if(count($dto->getIds()) > 0) {
            $dto->addToCriteria(
                new HasCriterion(
                    'consultationUser',
                    fn (Builder $q) => $q->whereIn('consultation_id', $dto->getIds())
                )
            );
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

    private function addToCriteria($value): void
    {
        $this->criteria[] = $value;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function setIds(array $ids): void
    {
        $this->ids = $ids;
    }
}
