<?php

namespace Ulams\Consultations\Strategies\Relations;

use Ulams\Consultations\Models\Consultation;
use Ulams\Consultations\Strategies\Contracts\RelationStrategyContract;

class ConsultationWithCategoriesStrategy implements RelationStrategyContract
{
    private Consultation $consultation;
    private array $data;

    public function __construct(array $params) {
        $this->consultation = $params[0];
        $this->data = $params[1] ?? [];
    }

    public function setRelation(): void
    {
        $this->consultation->categories()->sync($this->data['categories']);
    }
}
