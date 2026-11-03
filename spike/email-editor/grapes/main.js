// Throwaway spike entry. The editor is a lazy chunk, like the plan's 4.2.
window.__ready = (async () => {
  const [{ default: grapesjs }, { default: mjmlPlugin }, pl, zh, mpl] = await Promise.all([
    import('grapesjs'),
    import('grapesjs-mjml'),
    import('grapesjs/locale/pl'),
    import('grapesjs/locale/zh'),
    import('grapesjs-mjml/locale/pl'),
    import('grapesjs/dist/css/grapes.min.css'),
  ]);
  window.__make = (mjml, locale = 'en') =>
    grapesjs.init({
      container: '#gjs',
      height: '700px',
      fromElement: false,
      storageManager: false,
      components: mjml,
      plugins: [mjmlPlugin],
      pluginsOpts: { [mjmlPlugin]: {} },
      i18n: { locale, detectLocale: false, localeFallback: 'en', messages: { pl: { ...pl.default, ...(mpl.default.default ?? mpl.default) }, zh: zh.default } },
    });
  window.__grapesjs = grapesjs;
})();
