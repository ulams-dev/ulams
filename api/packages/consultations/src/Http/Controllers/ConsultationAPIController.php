<?php

namespace Ulams\Consultations\Http\Controllers;

use Ulams\Consultations\Dto\ConsultationUserTermDto;
use Ulams\Consultations\Dto\ConsultationSaveScreenDto;
use Ulams\Consultations\Dto\FilterScheduleForTutorDto;
use Ulams\Consultations\Dto\FinishTermDto;
use Ulams\Consultations\Dto\GenerateSignedScreenUrlsDto;
use Ulams\Consultations\Enum\ConstantEnum;
use Ulams\Consultations\Http\Controllers\Swagger\ConsultationAPISwagger;
use Ulams\Consultations\Http\Requests\ConsultationUserTermRequest;
use Ulams\Consultations\Http\Requests\ConsultationScreenSaveRequest;
use Ulams\Consultations\Http\Requests\FinishTermRequest;
use Ulams\Consultations\Http\Requests\GenerateSignedScreenUrlsRequest;
use Ulams\Consultations\Http\Requests\ListAPIConsultationsRequest;
use Ulams\Consultations\Http\Requests\ListConsultationsRequest;
use Ulams\Consultations\Http\Requests\ReportTermConsultationRequest;
use Ulams\Consultations\Http\Requests\ScheduleConsultationAPIRequest;
use Ulams\Consultations\Http\Requests\ShowAPIConsultationRequest;
use Ulams\Consultations\Http\Resources\ConsultationProposedTermResource;
use Ulams\Consultations\Http\Resources\ConsultationSimpleResource;
use Ulams\Consultations\Http\Resources\ConsultationTermsResource;
use Ulams\Consultations\Services\Contracts\ConsultationServiceContract;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Http\JsonResponse;

class ConsultationAPIController extends UlamsBaseController implements ConsultationAPISwagger
{
    private ConsultationServiceContract $consultationServiceContract;

    public function __construct(
        ConsultationServiceContract $consultationServiceContract
    ) {
        $this->consultationServiceContract = $consultationServiceContract;
    }

    public function index(ListAPIConsultationsRequest $listConsultationsRequest): JsonResponse
    {
        $search = $listConsultationsRequest->except(['limit', 'skip']);
        $consultations = $this->consultationServiceContract
            ->getConsultationsList($search, true, OrderDto::instantiateFromRequest($listConsultationsRequest))
            ->paginate(
                $listConsultationsRequest->get('per_page') ??
                config('ulams_consultations.perPage', ConstantEnum::PER_PAGE)
            );

        return $this->sendResponseForResource(
            ConsultationSimpleResource::collection($consultations), __('Consultations retrieved successfully')
        );
    }

    public function show(ShowAPIConsultationRequest $showAPIConsultationRequest, int $id): JsonResponse
    {
        $consultation = $this->consultationServiceContract->show($id);
        return $this->sendResponseForResource(
            ConsultationSimpleResource::make($consultation),
            __('Consultation show successfully')
        );
    }

    public function forCurrentUser(ListConsultationsRequest $listConsultationsRequest): JsonResponse
    {
        return $this->sendResponseForResource(
            $this->consultationServiceContract->forCurrentUserResponse($listConsultationsRequest),
            __('Consultations retrieved successfully')
        );
    }

    public function reportTerm(int $consultationTermId, ReportTermConsultationRequest $request): JsonResponse
    {
        $this->consultationServiceContract->reportTerm($consultationTermId, $request->input('term'));
        return $this->sendSuccess(__('Consultation reserved term successfully'));
    }

    public function approveTerm(ConsultationUserTermRequest $request, int $consultationTermId): JsonResponse
    {
        $this->consultationServiceContract->approveTerm($consultationTermId, new ConsultationUserTermDto($request->all()));
        $consultationTerms = $this->consultationServiceContract->getConsultationTermsForTutor();
        return $this->sendResponse(
            ConsultationTermsResource::collection($consultationTerms),
            __('Consultation term approved successfully')
        );
    }

    public function rejectTerm(ConsultationUserTermRequest $request, int $consultationTermId): JsonResponse
    {
        $this->consultationServiceContract->rejectTerm($consultationTermId, new ConsultationUserTermDto($request->all()));
        $consultationTerms = $this->consultationServiceContract->getConsultationTermsForTutor();
        return $this->sendResponse(
            ConsultationTermsResource::collection($consultationTerms),
            __('Consultation term reject successfully')
        );
    }

    public function proposedTerms(int $consultationTermId): JsonResponse
    {
        $proposedTerms = $this->consultationServiceContract->proposedTerms($consultationTermId);
        return $this->sendResponseForResource(
            ConsultationProposedTermResource::collection($proposedTerms),
            __('Consultations proposed terms retrieved successfully')
        );
    }

    public function generateJitsi(ConsultationUserTermRequest $request, int $consultationTermId): JsonResponse
    {
        return $this->sendResponse(
            $this->consultationServiceContract->generateJitsi($consultationTermId, new ConsultationUserTermDto($request->all())),
            __('Consultation updated successfully')
        );
    }

    public function schedule(ScheduleConsultationAPIRequest $scheduleConsultationAPIRequest): JsonResponse
    {
        $consultationTerms = $this->consultationServiceContract
            ->getConsultationTermsForTutor(
                FilterScheduleForTutorDto::prepareFilters($scheduleConsultationAPIRequest->validated())
            );

        return $this->sendResponse(
            ConsultationTermsResource::collection($consultationTerms),
            __('Consultation updated successfully')
        );
    }

    public function screenSave(ConsultationScreenSaveRequest $request): JsonResponse
    {
        $this->consultationServiceContract->saveScreen(new ConsultationSaveScreenDto($request->all()));
        return $this->sendSuccess(__('Screen saved successfully'));
    }

    public function generateSignedScreenUrls(GenerateSignedScreenUrlsRequest $request): JsonResponse
    {
        $data = $this
            ->consultationServiceContract
            ->generateSignedScreenUrls(new GenerateSignedScreenUrlsDto($request->validated()));

        return $this->sendResponse($data, __('Urls generated successfully'));
    }

    public function finishTerm(FinishTermRequest $request, int $consultationTermId): JsonResponse
    {
        $this->consultationServiceContract->finishTerm($consultationTermId, new FinishTermDto($request->all()));
        $consultationTerms = $this->consultationServiceContract->getConsultationTermsForTutor();
        return $this->sendResponse(
            ConsultationTermsResource::collection($consultationTerms),
            __('Consultation term approved successfully')
        );
    }
}
