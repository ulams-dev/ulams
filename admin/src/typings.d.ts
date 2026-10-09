declare module 'slash2';
declare module '*.css';
declare module '*.less';
declare module '*.scss';
declare module '*.sass';
declare module '*.svg';
declare module '*.png';
declare module '*.jpg';
declare module '*.jpeg';
declare module '*.gif';
declare module '*.bmp';
declare module '*.tiff';
declare module 'omit.js';
declare module 'numeral';
declare module '@antv/data-set';
declare module 'mockjs';
declare module 'react-fittext';
declare module 'bizcharts-plugin-slider';

declare const REACT_APP_ENV: 'test' | 'dev' | 'pre' | false;

// This file is a global script (no imports/exports), so Window is augmented directly
interface Window {
  REACT_APP_API_URL?: string;
  REACT_APP_TENANT_API_HOST_PATTERN?: string;
  /** Reference web app (AI Course Builder), e.g. https://coffee.app.example */
  REACT_APP_STUDIO_URL?: string;
}
