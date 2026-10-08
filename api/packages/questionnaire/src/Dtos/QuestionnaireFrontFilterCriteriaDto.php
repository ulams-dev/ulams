<?php

namespace Ulams\Questionnaire\Dtos;

use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Ulams\Core\Dtos\CriteriaDto;
use Ulams\Core\Repositories\Criteria\Primitives\HasCriterion;
use Ulams\Questionnaire\Repository\Criteria\ModelQuestionnareCriterion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class QuestionnaireFrontFilterCriteriaDto extends CriteriaDto implements InstantiateFromRequest
{
    public static function instantiateFromRequest(Request $request): self
    {
        $criteria = new Collection();

        if ($request->has('model_type_title') && $request->has('model_id')) {
            $criteria->push(new ModelQuestionnareCriterion($request->input('model_id'), $request->input('model_type_title')));
        }

        if ($request->has('public_answers')) {
            $criteria->push(
                new HasCriterion(
                    'questions',
                    fn (Builder $q) => $q->where('public_answers', '=', $request->input('public_answers'))
                )
            );
        }

        if ($request->has('question_type')) {
            $criteria->push(
                new HasCriterion(
                    'questions',
                    fn (Builder $q) => $q->where('type', '=', $request->input('question_type'))
                )
            );
        }

        return new self($criteria);
    }
}
