import './fonts';
import { World } from './scene/world';
import { buildUI } from './ui/panel';
import { Tour, stepIndexFromHash } from './ui/tour';
import { initAbout } from './ui/about';
import { isEmbedded, startAdapter } from './ulams/adapter';

const canvas = document.getElementById('scene') as HTMLCanvasElement;
const embedded = isEmbedded();
const params = new URLSearchParams(location.search);
// ?ulams-poster renders a step without any chrome, for the poster images (scripts/ulams-package.mjs).
const poster = params.has('ulams-poster');
if (poster) document.body.classList.add('ulams-embed', 'ulams-chrome-none', 'ulams-poster');

let world: World | null = null;
let tour: Tour | null = null;
let motionScale = !embedded && matchMedia('(prefers-reduced-motion: reduce)').matches ? 0.12 : 1;
try {
  world = new World(canvas);
} catch (error) {
  // No WebGL (old device, blocked GPU): tell the lesson page, which shows the poster and the text.
  document.getElementById('preloader')?.remove();
  document.body.classList.add('no-webgl');
  console.warn('WebGL is not available', error);
}

if (world) {
  // A handle for poking at the scene from the console, and for the promo recorder to drive frames by
  // hand. Dev builds and ?promo=1 only.
  if (import.meta.env.DEV || location.search.includes('promo')) {
    (window as unknown as { world: World }).world = world;
  }
  // Build the panel first (it sets #app innerHTML); then the Tour appends its overlay into that DOM.
  // The tour mutates world state directly, so `sync` refreshes the panel controls when the tour exits.
  const sync = buildUI(world, () => tour!.restart());
  tour = new Tour(world, () => sync());
  initAbout();
}

if (embedded) {
  const bridge = startAdapter({
    getTour: () => tour,
    setMotionScale: (s) => { motionScale = s; },
    onStart: (step) => {
      // The lesson page decides where to begin: the topic's first step, never always step one.
      if (step && tour?.goTo(step)) return;
      tour?.goTo(tour.steps()[0].id);
    },
  });
  if (!world) bridge.error('webgl-unavailable', 'WebGL could not be started in this browser.');
} else if (tour) {
  if (poster) {
    const i = stepIndexFromHash();
    tour.goTo(tour.steps()[Math.max(0, i)].id);
  } else {
    // Begin in the guided walkthrough (honoring any #step deep link in the URL).
    tour.start();
  }
}

// Once the scene has rendered its first frame, drop the preloader.
let booted = false;
function boot(): void {
  if (booted) return;
  booted = true;
  const pre = document.getElementById('preloader');
  if (pre) {
    pre.classList.add('hidden');
    setTimeout(() => pre.remove(), 600);
  }
  document.body.classList.add('ulams-booted');
}

if (world) {
  let last = performance.now();
  const loop = (now: number): void => {
    const dt = Math.min((now - last) / 1000, 0.1); // clamp big gaps (tab switch)
    last = now;
    world!.update(dt * motionScale);
    if (!booted) boot();
    requestAnimationFrame(loop);
  };
  requestAnimationFrame(loop);
}
