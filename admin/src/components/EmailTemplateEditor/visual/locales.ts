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

/** Strings of the Ulams additions (chips, shared parts, link and image fields) of the editor. */
export const ulamsMessages: Record<string, { ulams: Record<string, string> }> = {
  en: {
    ulams: {
      partial: 'Shared part {token}. Edit it in Settings; it cannot be changed here.',
      href_variable: 'Link from a variable',
      variable_none: 'Choose a variable',
      choose_image: 'Choose or upload an image',
    },
  },
  pl: {
    ulams: {
      partial: 'Część wspólna {token}. Edytuj ją w Ustawieniach, tutaj nie można jej zmienić.',
      href_variable: 'Link ze zmiennej',
      variable_none: 'Wybierz zmienną',
      choose_image: 'Wybierz lub wgraj obraz',
    },
  },
  zh: {
    ulams: {
      partial: '共享部分 {token}。请在“设置”中编辑，此处无法修改。',
      href_variable: '来自变量的链接',
      variable_none: '选择变量',
      choose_image: '选择或上传图片',
    },
  },
  fr: {
    ulams: {
      partial:
        'Partie partagée {token}. Modifiez-la dans les Paramètres, elle ne peut pas être changée ici.',
      href_variable: 'Lien depuis une variable',
      variable_none: 'Choisir une variable',
      choose_image: 'Choisir ou téléverser une image',
    },
  },
};

/** Locales of the editor UI. `detectLocale` stays off, the admin decides the language. */
export const editorMessages: Record<string, Record<string, unknown>> = {
  en: { ...ulamsMessages.en },
  pl: { ...withDefault(plCore), ...withDefault(plPlugin), ...ulamsMessages.pl },
  zh: { ...withDefault(zhCore), ...zhPlugin, ...ulamsMessages.zh },
  fr: { ...withDefault(frCore), ...withDefault(frPlugin), ...ulamsMessages.fr },
};

/** Maps an admin locale (`pl-PL`) to a locale of the editor, English when there is none. */
export const editorLocale = (adminLocale: string | undefined): string => {
  const short = (adminLocale ?? 'en').toLowerCase().split(/[-_]/)[0];
  return short in editorMessages ? short : 'en';
};

/** Label of a locked shared part inside the canvas, `{token}` is replaced by the caller. */
export const partialMessage = (adminLocale: string | undefined): string =>
  ulamsMessages[editorLocale(adminLocale)].ulams.partial;
