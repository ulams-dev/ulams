<?php

namespace EscolaLms\ConsultationAccess\Dtos;

use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Repositories\Criteria\Primitives\OrderCriterion;
use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use EscolaLms\Core\Dtos\CriteriaDto as BaseCriteriaDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\HasCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\InCriterion;
use EscolaLms\Core\Repositories\Criteria\Primitives\WhereCriterion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CriteriaDto extends BaseCriteriaDto implements DtoContract, InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->get('consultation_id')) {
            $criteria->push(new EqualCriterion('consultation_id', $request->get('consultation_id')));
        }
        if ($request->get('user_id')) {
            $criteria->push(new EqualCriterion('user_id', $request->get('user_id')));
        }
        if ($request->get('status')) {
            $criteria->push(new EqualCriterion('status', $request->get('status')));
        }
        if ($request->has('is_coming')) {
            $criteria->push(new HasCriterion('consultationUserTerm', function (Builder $query) use ($request) {
                $query->where('executed_status', ConsultationTermStatusEnum::APPROVED);
                $query->whereDate('executed_at', $request->boolean('is_coming') ? '>=' : '<=', Carbon::now());
            }));
        }
        if ($request->get('proposed_at_from')) {
            $criteria->push(new HasCriterion('consultationAccessEnquiryProposedTerms', function (Builder $query) use ($request) {
                $query->whereDate('proposed_at', '>=', Carbon::make($request->get('proposed_at_from')));
            }));
        }
        if ($request->get('proposed_at_to')) {
            $criteria->push(new HasCriterion('consultationAccessEnquiryProposedTerms', function (Builder $query) use ($request) {
                $query->whereDate('proposed_at', '<=', Carbon::make($request->get('proposed_at_to')));
            }));
        }
        if ($request->get('consultation_term_ids')) {
            $criteria->push(new InCriterion('consultation_user_id', $request->get('consultation_term_ids')));
        }

        return new self($criteria);
    }
}
