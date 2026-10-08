<?php

namespace App\Services;

use App\Services\Contracts\ConsultationServiceContract;
use Ulams\Cart\Enums\ProductType;
use Ulams\Cart\Models\Product;
use Ulams\Consultations\Enum\ConsultationTermStatusEnum;
use Ulams\Consultations\Models\ConsultationUserPivot;

class ConsultationService implements ConsultationServiceContract
{
    public function updateReportTerm(ConsultationUserPivot $consultationTerm): void
    {
        $product = Product::find($consultationTerm->product_id);
        if (isset($product) && $product->type === ProductType::BUNDLE) {
            \DB::transaction(function () use($consultationTerm) {
                $consultationTerm->executed_status = ConsultationTermStatusEnum::APPROVED;
                $consultationTerm->save();
            });
        }
    }
}
