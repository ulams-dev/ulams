import { API } from '@ulams/sdk';

export type FormState =
  | { state: 'input' }
  | { state: 'loading' }
  | { state: 'disabled' }
  | { state: 'success'; message: string }
  | { state: 'error'; error?: API.DefaultResponseError };
