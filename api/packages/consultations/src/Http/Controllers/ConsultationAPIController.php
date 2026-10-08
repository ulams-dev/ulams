<?php

namespace EscolaLms\Consultations\Http\Controllers;

use EscolaLms\Consultations\Dto\ConsultationUserTermDto;
use EscolaLms\Consultations\Dto\ConsultationSaveScreenDto;
use EscolaLms\Consultations\Dto\FilterScheduleForTutorDto;
use EscolaLms\Consultations\Dto\FinishTermDto;
use EscolaLms\Consultations\Dto\GenerateSignedScreenUrlsDto;
use EscolaLms\Consultations\Enum\ConstantEnum;
use EscolaLms\Consultations\Http\Controllers\Swagger\ConsultationAPISwagger;
use EscolaLms\Consultations\Http\Requests\ConsultationUserTermRequest;
use EscolaLms\Consultations\Http\Requests\ConsultationScreenSaveRequest;
use EscolaLms\Consultations\Http\Requests\FinishTermRequest;
use EscolaLms\Consultations\Http\Requests\GenerateSignedScreenUrlsRequest;
use EscolaLms\Consultations\Http\Requests\ListAPIConsultationsRequest;
use EscolaLms\Consultations\Http\Requests\ListConsultationsRequest;
use EscolaLms\Consultations\Http\Requests\ReportTermConsultationRequest;
use EscolaLms\Consultations\Http\Requests\ScheduleConsultationAPIRequest;
use EscolaLms\Consultations\Http\Requests\ShowAPIConsultationRequest;
use EscolaLms\Consultations\Http\Resources\ConsultationProposedTermResource;
use EscolaLms\Consultations\Http\Resources\ConsultationSimpleResource;
use EscolaLms\Consultations\Http\Resources\ConsultationTermsResource;
use EscolaLms\Consultations\Services\Contracts\ConsultationServiceContract;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;

class ConsultationAPIController extends EscolaLmsBaseController implements ConsultationAPISwagger
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
                config('escolalms_consultations.perPage', ConstantEnum::PER_PAGE)
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
