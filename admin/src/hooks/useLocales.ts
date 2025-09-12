import { getAllLocales, useModel } from '@umijs/max';
import { useMemo } from 'react';
import { getLocale } from 'umi';

export const useGetLocales = () => {
  const locales: string[] = getAllLocales() || [];
  const { initialState } = useModel('@@initialState');
  const currentLocale = getLocale().split('-')[0];

  const currentLocales = useMemo(() => {
    const l = locales
      .map((locale) => locale.split('-')[0])
      .reduce((a, v) => ({ ...a, [v]: v }), {});

    const additionalLocales = JSON.parse(
      initialState?.config?.find((e) => e.key === 'locales')?.value || '{}',
    );

    if (additionalLocales) {
      return { ...l, ...additionalLocales };
    }
    return l;
  }, [locales, initialState]);

  return {
    currentLocales,
    currentLocale,
  };
};
