// Self-hosted fonts (Inter and Roboto Mono, SIL OFL 1.1 via @fontsource). The files ship inside the
// package, so the page never contacts Google Fonts. Only the latin and latin-ext subsets are bundled
// (English and Polish); the @font-face rules are written here instead of importing the packages' CSS,
// which would copy every subset (cyrillic, greek, vietnamese) into the build.
import interLatin from '@fontsource-variable/inter/files/inter-latin-wght-normal.woff2?url';
import interLatinExt from '@fontsource-variable/inter/files/inter-latin-ext-wght-normal.woff2?url';
import mono400 from '@fontsource/roboto-mono/files/roboto-mono-latin-400-normal.woff2?url';
import mono500 from '@fontsource/roboto-mono/files/roboto-mono-latin-500-normal.woff2?url';
import monoExt400 from '@fontsource/roboto-mono/files/roboto-mono-latin-ext-400-normal.woff2?url';
import monoExt500 from '@fontsource/roboto-mono/files/roboto-mono-latin-ext-500-normal.woff2?url';

const LATIN = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
const LATIN_EXT = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';

const face = (family: string, weight: string, url: string, range: string, format = 'woff2') =>
  `@font-face{font-family:'${family}';font-style:normal;font-display:swap;font-weight:${weight};src:url(${url}) format('${format}');unicode-range:${range}}`;

const style = document.createElement('style');
style.textContent = [
  face('Inter', '100 900', interLatin, LATIN, 'woff2-variations'),
  face('Inter', '100 900', interLatinExt, LATIN_EXT, 'woff2-variations'),
  face('Roboto Mono', '400', mono400, LATIN),
  face('Roboto Mono', '500', mono500, LATIN),
  face('Roboto Mono', '400', monoExt400, LATIN_EXT),
  face('Roboto Mono', '500', monoExt500, LATIN_EXT),
].join('\n');
document.head.appendChild(style);
