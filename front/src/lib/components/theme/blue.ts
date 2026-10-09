import type { ThemeTokens } from "./types";
import chroma from "chroma-js";
import { sharedTheme } from "./shared";

export const blueTheme: ThemeTokens = {
  ...sharedTheme,
  font: "Mulish",
  primaryColor: "#56CCF2",
  dm__primaryColor: "#56CCF2",

  secondaryColor: chroma.blend("#56CCF2", "#BDBDBD", "multiply").hex(),
  dm__secondaryColor: chroma.blend("#56CCF2", "#BDBDBD", "multiply").hex(),

  headerColor: "#111111",

  background: "#F2F2F2",
  dm__background: "#232225",

  colorBackground: "#56CCF2",
  dm__colorBackground: "#56CCF2",

  cardBackgroundColor: sharedTheme.gray5,
  dm__cardBackgroundColor: sharedTheme.gray1,

  primaryButtonDisabled: `rgba(${chroma(sharedTheme.gray1)
    .rgb()
    .join(",")}, 0.2)`,
  dm__primaryButtonDisabled: `rgba(${chroma(sharedTheme.gray1)
    .rgb()
    .join(",")}, 0.2)`,

  textColor: "#000",
  dm__textColor: "#FFF",

  labelListValueColor: "#56CCF2",

  outlineButtonInvertColor: "#23298e",
  dm__outlineButtonInvertColor: "#ffcc00",
};

export default blueTheme;
