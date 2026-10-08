import type { ThemeTokens } from "../../../theme/types";
import { FONTS } from "../../../theme/cssVars";

/**
 * Theme overrides for H5P content, sent to the H5P service's player page over
 * postMessage. The content lives in a cross-origin iframe that cannot read the
 * parent's `--ulams-*` custom properties, so the stylesheet is built from the raw
 * theme tokens (`useThemeTokens()`), resolved for the current mode.
 */
export const buildH5PThemeCss = (
  theme: ThemeTokens,
  hideActionButtons?: boolean
): string => {
  const isDark = theme.mode === "dark";
  const byMode = (dark: string, light: string): string => (isDark ? dark : light);

  const fontFamily = (FONTS[theme.bodyFont ?? theme.font] ?? { fontFamily: "sans-serif" }).fontFamily;
  const primaryColor = isDark ? theme.dm__primaryColor ?? theme.primaryColor : theme.primaryColor;
  const secondaryColor = isDark
    ? theme.dm__secondaryColor ?? theme.primaryColor
    : theme.secondaryColor ?? theme.primaryColor;
  const fontColor = byMode(theme.white, theme.black);
  const backgroundColor = byMode(theme.dm__background ?? "", theme.background);
  const inputBackground = byMode("transparent", theme.gray5);
  const inputBorder = byMode(theme.white, theme.gray1);

  const css = `
    @import url("https://fonts.googleapis.com/css2?family=${
      theme.font
    }:wght@400;500;700&display=swap");

    *:not([class^="h5p-icon"]) {
      font-family: ${fontFamily}!important;
    }
    button {
      border-radius: ${theme.buttonRadius}px!important;
    }
    input, textarea {
      border-radius: ${theme.inputRadius}px!important;
    }
    .h5p-baq-intro-page {
      background: ${secondaryColor} !important;
      color: ${primaryColor} !important;
    }
    .h5p-joubelui-button.mq-control-button {
      background: ${primaryColor} !important;
      border-bottom: none!important;
      text-shadow: none!important;
      border-radius: ${theme.buttonRadius}px!important;
    }
    .h5p-joubelui-button {
      border-radius: ${theme.buttonRadius}px!important;
    }
    .h5p-baq-intro-page-title {
      text-shadow: none!important;
      color: ${theme.white} !important;
    }
    .h5p-baq {
      background: ${primaryColor} !important;
    }
    .h5p-baq-countdown-text {
      background: ${primaryColor} !important;
    }
    .h5p-baq-countdown-bg.fuel {
      background: ${primaryColor} !important;
      filter: brightness(0.8) !important;
    }
    .h5p-joubelui-progressbar-background {
      background: ${primaryColor} !important;
      filter: brightness(0.8) !important;
    }
    .h5p-baq-alternatives > .h5p-joubelui-button:active, .h5p-baq-alternatives > .h5p-joubelui-button:hover {
      background: ${primaryColor} !important;
      filter: brightness(0.8) !important;
    }
   .odometer-value {
    color: ${primaryColor} !important;
   }
   .h5p-question {
    background: ${backgroundColor} !important;
    color: ${fontColor}!important;
   }
   .h5peditor .ui-dialog .h5p-joubelui-button, .h5peditor .h5p-joubelui-button, .h5p-joubelui-button {
    background: ${primaryColor} !important;
   }
   .h5p-crossword-cell.h5p-crossword-cell-empty {
    background: ${byMode(theme.gray2, theme.gray3)} !important;
   }
   .h5p-crossword-input-fields-group-input {
    background:${inputBackground} !important;
    border: 1px solid ${inputBorder}!important;
    color: ${fontColor} !important;
   }
   .h5p-crossword .h5p-crossword-cell:not(.h5p-crossword-solution-correct):not(.h5p-crossword-solution-wrong):not(.h5p-crossword-solution-neutral).h5p-crossword-highlight-normal {
    background: ${primaryColor}!important;
   }
   .h5p-crossword .h5p-crossword-input-fields-group-wrapper-clue.h5p-crossword-input-fields-group-clue-highlight-focus .h5p-crossword-input-fields-group-clue-id {
    background: ${primaryColor}!important;
    color: ${theme.white}!important;
   }
   .h5p-crossword .h5p-crossword-cell.h5p-crossword-highlight-normal .h5p-crossword-cell-canvas {
    color: ${theme.white}!important;
   }
   .h5p-dialogcards {
      background: ${backgroundColor} !important;
      color: ${fontColor}!important;
   }
   .h5p-dialogcards-card-content {
    background: ${backgroundColor} !important;
   }
   .h5p-dialogcards .h5p-audio-minimal-button {
    background: ${primaryColor}!important;
   }
   .h5p-essay-input-field-textfield {
    background: ${inputBackground} !important;
    border: 1px solid ${inputBorder}!important;
    color: ${fontColor}!important;
   }
   .h5p-question-feedback-content-text {
    color: ${primaryColor}!important;
   }
   .h5p-question-explanation-container {
    background: ${backgroundColor} !important;
   }
   .h5p-question-explanation-item {
    background: ${backgroundColor} !important;
   }
   .h5p-accordion .h5p-panel-content {
    color: ${fontColor}!important;
   }
   .h5p-accordion .h5p-panel-title {
    color: ${fontColor}!important;
   }
   .h5p-panel-content h5p-advanced-text {
    color: ${fontColor}!important;
   }
   .h5p-status dt {
    color: ${fontColor}!important;  
   }
   .h5p-status dd {
    color: ${fontColor}!important;  
   }
   .h5p-accordion .h5p-panel-title:before {
    color: ${primaryColor}!important;
   }
   .h5p-accordion {
    background: ${backgroundColor} !important;
    color: ${fontColor}!important;
   }
   .h5p-panel-title {
    color: ${fontColor}!important;
   }
   .h5p-panel-expanded {
    color: ${primaryColor}!important;
   }
   .h5p-questionnaire-element.h5p-questionnaire-required .h5p-subcontent-question {
    background: ${primaryColor}!important;
    color: ${theme.white}!important;
   }
   .h5p-questionnaire-required-symbol {
    background: ${primaryColor}!important;
   }
   .h5p-questionnaire-progress-bar-current {
    background: ${primaryColor} !important;
    filter: brightness(0.8) !important;
   }
   .h5p-questionnaire-footer {
    background: ${byMode(theme.gray2, theme.gray4)} !important;
    border: none!important;
   }
   .h5p-questionnaire-button, .h5peditor .h5p-questionnaire-button {
    background: ${primaryColor} !important;
    border: none!important;
   }
   .h5p-open-ended-question-input {
    background: ${inputBackground} !important;
    border: 1px solid ${inputBorder}!important;
    color: ${fontColor}!important;
    width: 100%!important;
    max-width: 100%!important;
   }
   .h5p-open-ended-question-content {
    background: ${backgroundColor} !important;
   }
   .h5p-open-ended-question-question:before {
    background: ${backgroundColor} !important;
   }
   .h5p-questionnaire-progress-bar {
    background: ${theme.gray2} !important;
   }
   .h5p-open-ended-question-question, 
   .h5p-simple-multiple-choice-question, 
   h5p-subcontent-question {
    background: ${primaryColor} !important;
   }
   .h5p-true-false-answer {
    background: ${backgroundColor}!important;
   }
   .h5p-true-false-answer[aria-checked=true] {
    background: ${primaryColor}!important;
    color: ${theme.white}!important;
    border: 1px solid ${primaryColor}!important;
   }
   .h5p-true-false-answer:focus {
    box-shadow: 0 0 0 1px ${primaryColor}!important;
   }
   .h5p-multichoice .h5p-alternative-container {
    background: ${inputBackground} !important;
    border: 1px solid ${inputBorder}!important;
    box-shadow: none!important;
   }
   .h5p-multichoice .h5p-answer[aria-checked="true"] .h5p-alternative-container {
    color: ${primaryColor}!important;
   }
   .h5p-multichoice .h5p-answer .h5p-alternative-container:before {
    color: ${byMode(theme.gray4, theme.gray2)}!important;
    border-radius: ${theme.checkboxRadius}!important;
   }
   .h5p-question-feedback {
    color: ${primaryColor}!important;
   }
   ul.h5p-sc-alternatives li.h5p-sc-alternative {
    background: ${inputBackground} !important;
    border: 1px solid ${inputBorder}!important;
    box-shadow: none!important;
   }
   li.h5p-sc-alternative .h5p-sc-progressbar {
    background: ${primaryColor}!important;
   }
  
   ul.h5p-sc-alternatives.h5p-sc-selected li.h5p-sc-alternative.h5p-sc-reveal-correct, 
   ul.h5p-sc-alternatives.h5p-sc-selected li.h5p-sc-alternative.h5p-sc-reveal-correct:hover, 
   ul.h5p-sc-alternatives.h5p-sc-selected li.h5p-sc-alternative.h5p-sc-reveal-correct:active, 
   ul.h5p-sc-alternatives.h5p-sc-selected li.h5p-sc-alternative.h5p-sc-reveal-correct:focus {
    color: ${byMode("lime", "green")}!important;
   }
   .h5p-image-hotspot-popup {
    background: ${backgroundColor} !important;
    color: ${fontColor}!important;
   }
   .h5p-image-hotspots {
    background-color: ${backgroundColor} !important;
   }
   .h5p-image-hotspot-popup-pointer {
    border-left: 0.6em solid ${backgroundColor}!important;
   }
   .h5p-image-sequencing {
    background: ${backgroundColor} !important;
    color: ${fontColor}!important;
   }
   .h5p-task-description {
    color: ${fontColor}!important;
   }
   .draggabled .image-desc .text {
    color: ${fontColor}!important;
   }
   .h5p-guess-answer {
    background: ${backgroundColor} !important;
   }
   .h5p-content {
    background: ${backgroundColor} !important;
   }
   .h5p-guess-answer-title {
    color: ${fontColor}!important;
   }
   .show-solution-button {
    background: ${primaryColor} !important;
    color: ${theme.white}!important;
   }
   .solution-text {
    color: ${primaryColor}!important;
   }
   .h5p-image-slider-progress-element {
    background: transparent !important;
    border: 1px solid ${primaryColor}!important;
    border-radius: ${theme.buttonRadius}px!important;
    width: 9px!important;
    height: 9px!important;
   }
   .h5p-image-slider-current-progress-element {
    background: ${primaryColor} !important;
   }
   .h5p-memory-reset {
    background: ${primaryColor} !important;
    color: "#fff"!important;
   }
   .h5p-feedback h5p-show {
    color: ${primaryColor}!important;
   }
   .h5p-memory-game .h5p-memory-top {
    background: ${byMode(theme.gray2, theme.gray4)} !important;
   }
   .h5p-memory-game .h5p-memory-pop {
    background: ${byMode(theme.gray1, theme.gray3)} !important;
   }
   .h5p-memory-game .h5p-programatically-focusable {
    color: ${primaryColor}!important;
   }
   .h5p-content ul.h5p-actions {
    display: ${hideActionButtons ? "none" : "block"};
  }
  `;
  return css;
};
