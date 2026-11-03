/**
 * The interface language of a browser element: the closest `lang` attribute above it (a card in another
 * language marks itself), else the page's `<html lang>`. The strings tables turn it into `en`, `pl` or `zh`.
 */
export const elementLang = (element: Element): string => element.closest("[lang]")?.getAttribute("lang") || document.documentElement.lang || "en";
