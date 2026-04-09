export interface RecommenderTerm {
  id: number;
  term: string;
  model_id: number;
  model_name: string;
  model_type: 'consultation' | 'webinar';
  avg_attention: string;
  avg_emotions_angry: string;
  avg_emotions_disgusted: string;
  avg_emotions_fearful: string;
  avg_emotions_happy: string;
  avg_emotions_neutral: string;
  avg_emotions_sad: string;
  avg_emotions_surprised: string;
  max_emotion: string;
  max_emotion_value: string;
  duration?: string;
  rating?: number;
  url?: string;
  urlExpirationTimeMillis?: number;
}

export interface RecommenderParams {
  name?: string;
  'categories[]'?: number | string;
  per_page?: number;
  page?: number;
  date_from?: string;
  date_to?: string;
}
