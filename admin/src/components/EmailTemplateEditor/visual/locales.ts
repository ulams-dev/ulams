import frPlugin from 'grapesjs-mjml/locale/fr';
import plPlugin from 'grapesjs-mjml/locale/pl';
import frCore from 'grapesjs/locale/fr';
import plCore from 'grapesjs/locale/pl';
import zhCore from 'grapesjs/locale/zh';

const withDefault = (module: unknown): Record<string, unknown> =>
  ((module as { default?: Record<string, unknown> }).default ??
    (module as Record<string, unknown>)) as Record<string, unknown>;

/**
 * Strings of the plugin for Chinese: grapesjs-mjml 1.0.8 has no `zh` locale (the core has one), so
 * the editor would show its blocks, undo, redo and import labels in English.
 */
export const zhPlugin = {
  'grapesjs-mjml': {
    category: '',
    panels: {
      buttons: {
        undo: '撤销',
        redo: '重做',
        desktop: '桌面',
        tablet: '平板',
        mobile: '手机',
        import: '导入 MJML',
      },
      import: { title: '导入 MJML', button: '导入', label: '' },
      export: { title: '导出 MJML' },
    },
    components: {
      names: {
        body: '正文',
        button: '按钮',
        column: '列',
        oneColumn: '1 列',
        twoColumn: '2 列',
        threeColumn: '3 列',
        divider: '分隔线',
        group: '分组',
        hero: '主视觉',
        image: '图片',
        navBar: '导航栏',
        navLink: '导航链接',
        section: '区块',
        socialGroup: '社交媒体组',
        socialElement: '社交媒体元素',
        spacer: '间距',
        text: '文本',
        wrapper: '容器',
        raw: '原始代码',
      },
    },
  },
};

/** Locales of the editor UI. `detectLocale` stays off, the admin decides the language. */
export const editorMessages: Record<string, Record<string, unknown>> = {
  pl: { ...withDefault(plCore), ...withDefault(plPlugin) },
  zh: { ...withDefault(zhCore), ...zhPlugin },
  fr: { ...withDefault(frCore), ...withDefault(frPlugin) },
};

/** Maps an admin locale (`pl-PL`) to a locale of the editor, English when there is none. */
export const editorLocale = (adminLocale: string | undefined): string => {
  const short = (adminLocale ?? 'en').toLowerCase().split(/[-_]/)[0];
  return short in editorMessages ? short : 'en';
};
