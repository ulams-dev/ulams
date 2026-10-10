// The ulams bridge adapter (ulams-ix v1, ADR 0087). When the simulator runs inside a lesson page
// (an iframe), the page owns the steps, the language and the chrome:
//   - `goToStep` opens a step, and every step change is reported with `stepChanged`;
//   - `init` carries the locale, the chrome level, the first step, the step range and reduced motion;
//   - the package announces itself with `ready` (its step ids) once `init` has arrived.
// Without a parent window every bridge call is a no-op, so the same build also runs on its own.
import { connect, type Bridge } from '@ulams/interactive-bridge/package';
import type { InitPayload } from '@ulams/interactive-bridge/protocol';
import type { Tour, Lang } from '../ui/tour';
import { STEPS } from '../ui/tour';

export const isEmbedded = (): boolean => window.parent !== window;

/** The step ids, in tour order: the ids the manifest and the course use. */
export const stepIds = (): string[] => STEPS.map((s) => s.id);

export interface AdapterOptions {
  /** The tour exists once the scene could be created. */
  getTour: () => Tour | null;
  /** Reduced motion slows the whole clock (the world update uses this factor). */
  setMotionScale: (scale: number) => void;
  /** Called once `init` has arrived, with the first step to show. */
  onStart: (startStep: string | undefined) => void;
}

const LANGS: Lang[] = ['en', 'pl'];
const REDUCED_MOTION_SCALE = 0.12;

export function startAdapter(options: AdapterOptions): Bridge {
  const applyInit = (init: InitPayload) => {
    const tour = options.getTour();
    document.body.classList.add('ulams-embed', `ulams-chrome-${init.chrome}`);
    // The landing hero: the scene alone, drifting. No tour panel, no labels, no legends, nothing to focus.
    if (init.showcase) { document.body.classList.add('ulams-showcase'); document.body.setAttribute('inert', ''); }
    if (!tour) return; // the scene could not be created: the error below tells the lesson page
    const lang = init.locale.slice(0, 2) as Lang;
    if (LANGS.includes(lang)) tour.setLanguage(lang);
    if (init.reducedMotion) {
      tour.reducedMotion = true;
      options.setMotionScale(REDUCED_MOTION_SCALE);
    }
    tour.embed(init.range);
    options.onStart(init.startStep ?? init.range?.from);
  };

  const bridge = connect({
    steps: stepIds(),
    capabilities: { steps: true, reducedMotion: false, background: true, locales: LANGS },
    onInit: applyInit,
    onGoToStep: (step) => { options.getTour()?.goTo(step); },
    onLocale: (locale) => {
      const lang = locale.slice(0, 2) as Lang;
      if (LANGS.includes(lang)) options.getTour()?.setLanguage(lang);
    },
    onTheme: () => { /* the scene keeps its own dark look */ },
    onPause: () => options.setMotionScale(0),
    onResume: () => options.setMotionScale(options.getTour()?.reducedMotion ? REDUCED_MOTION_SCALE : 1),
  });

  window.addEventListener('gravity:step', (e) => {
    const { id, index } = (e as CustomEvent<{ id: string; index: number }>).detail;
    bridge.stepChanged(id);
    bridge.progress(index / Math.max(1, STEPS.length - 1));
  });
  return bridge;
}
