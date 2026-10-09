# Bundled fonts

All fonts are licensed under the SIL Open Font License 1.1 (licence texts in
[`licenses/`](licenses)). They are used unmodified; PDFs embed subsets, which
the OFL allows. Every font covers Polish diacritics (ą ć ę ł ń ó ś ź ż).

| Key (`fontName`) | File | Family | Licence | Used by |
|---|---|---|---|---|
| `NotoSans-Regular` (fallback) | NotoSans-Regular.ttf | Noto Sans | [OFL](licenses/OFL-notosans.txt) | default certificate, fallback, poland |
| `NotoSans-Bold` | NotoSans-Bold.ttf | Noto Sans | [OFL](licenses/OFL-notosans.txt) | |
| `PlusJakartaSans-Regular` | PlusJakartaSans-Regular.ttf | Plus Jakarta Sans | [OFL](licenses/OFL-plusjakartasans.txt) | coffee |
| `PlusJakartaSans-Bold` | PlusJakartaSans-Bold.ttf | Plus Jakarta Sans | [OFL](licenses/OFL-plusjakartasans.txt) | default certificate title |
| `PlayfairDisplay-Bold` | PlayfairDisplay-Bold.ttf | Playfair Display (Reserved Font Name) | [OFL](licenses/OFL-playfairdisplay.txt) | coffee, poland, ulam |
| `SpaceGrotesk-Bold` | SpaceGrotesk-Bold.ttf | Space Grotesk | [OFL](licenses/OFL-spacegrotesk.txt) | oncall, gravity |
| `JetBrainsMono-Regular` | JetBrainsMono-Regular.ttf | JetBrains Mono | [OFL](licenses/OFL-jetbrainsmono.txt) | oncall, gravity, ulam |
| `Baloo2-Bold` | Baloo2-Bold.ttf | Baloo 2 | [OFL](licenses/OFL-baloo2.txt) | nightsky |
| `Nunito-Regular` | Nunito-Regular.ttf | Nunito | [OFL](licenses/OFL-nunito.txt) | nightsky |

Sources: the static instances served by Google Fonts (`fonts.googleapis.com`),
except JetBrains Mono, taken from the upstream release
(`github.com/JetBrains/JetBrainsMono`, `fonts/ttf/`) because the Google Fonts
instance fails pdfme's font subsetting. Licence texts come from
`github.com/google/fonts` (`ofl/<family>/OFL.txt`).

To add a font: put the TTF and its licence here, add it to `FONT_FILES` in
`src/fonts.ts`, and it becomes available to the renderer and the admin designer.
