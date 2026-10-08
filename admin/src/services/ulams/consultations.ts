import type { RecommenderParams } from '@/pages/Consultations/consultations';
import type { AxiosRequestConfig } from '@umijs/max';
import { request } from 'umi';

/**  GET /api/admin/consultations */
export async function consultations(
  params?: API.ConsultationsParams,
  options?: AxiosRequestConfig,
) {
  return request<API.DefaultMetaResponse<API.Consultation>>(`/api/admin/consultations`, {
    method: 'GET',
    /* useCache: true */ useCache: false,
    params,
    ...(options || {}),
  });
}

/**  POST /api/consultations */
export async function createConsultation(body?: Record<string, any>, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.Consultation>>(`/api/admin/consultations`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    data: body,
    ...(options || {}),
  });
}

/**  GET /api/admin/consultations/:id */
export async function getConsultation(id: number, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.Consultation>>(`/api/admin/consultations/${id}`, {
    method: 'GET',
    /* useCache: true */ useCache: false,
    ...(options || {}),
  });
}

/**  PUT /api/admin/consultations/:id */
export async function updateConsultation(
  id: number,
  body?: Record<string, any>,
  options?: AxiosRequestConfig,
) {
  return request<API.DefaultResponse<API.Consultation>>(`/api/admin/consultations/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
    },
    data: body,
    ...(options || {}),
  });
}

/**  GET /api/admin/consultations/:id */
export async function deleteConsultation(id: number, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.Consultation>>(`/api/admin/consultations/${id}`, {
    method: 'DELETE',
    /* useCache: true */ useCache: false,
    ...(options || {}),
  });
}

/**  GET /api/admin/consultations/:id/schedule */
export async function getSchedule(id: number, options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<API.ConsultationAppointment[]>>(
    `/api/admin/consultations/${id}/schedule`,
    {
      method: 'GET',
      /* useCache: true */ useCache: false,
      ...(options || {}),
    },
  );
}

/**  POST /api/admin/consultations/change-term/{termId} */
export async function changeTermDate(
  termId: number,
  newDate: string,
  options?: AxiosRequestConfig,
) {
  return request<API.DefaultResponse<API.Consultation>>(
    `/api/admin/consultations/change-term/${termId}`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      data: {
        executed_at: newDate,
      },
      ...(options || {}),
    },
  );
}

/** GET /api/admin/recommender/terms/{modelType} */
export async function getRecommenderTerms(modelType: string, params?: RecommenderParams) {
  return request<API.DefaultMetaResponse<API.Consultation>>(
    `/api/admin/recommender/terms/${modelType}`,
    {
      method: 'GET',
      params,
    },
  );
}

export async function getModelAnalytics(
  modelType: string,
  modelId: string | number,
  term: string | number,
  options?: AxiosRequestConfig,
) {
  return request<API.DefaultResponse<any>>(
    `/api/admin/recommender/analytics/${modelType}/${modelId}/${term}`,
    {
      method: 'GET',
      ...(options || {}),
    },
  );
}

export async function getAnalyticsChartFrames(
  id: number,
  params: { interval: number },
  options?: AxiosRequestConfig,
) {
  return request<API.DefaultResponse<any[]>>(
    `/api/admin/recommender/analytics/aggregated-frames/${id}`,
    {
      method: 'GET',
      params,
      ...(options || {}),
    },
  );
}
