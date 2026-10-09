import i18next, { i18n as I18n } from 'i18next';
import FsBackend from 'i18next-fs-backend';
import * as i18nextHttpMiddleware from 'i18next-http-middleware';
import { ITranslationFunction } from '@lumieducation/h5p-server';

import { translationsPath } from './createH5P';

export interface I18nSetup {
    i18n: I18n;
    translate: ITranslationFunction;
    middleware: ReturnType<typeof i18nextHttpMiddleware.handle>;
}

/**
 * Lumi needs a translation function (server-side messages, hub texts, error
 * messages) and h5p-express expects req.t / req.language from the i18next
 * HTTP middleware.
 */
export async function createI18n(preload: string[] = ['en', 'pl', 'de']): Promise<I18nSetup> {
    const instance = i18next.createInstance();
    await instance
        .use(FsBackend)
        .use(i18nextHttpMiddleware.LanguageDetector)
        .init({
            backend: { loadPath: translationsPath() },
            defaultNS: 'server',
            fallbackLng: 'en',
            ns: [
                'client',
                'copyright-semantics',
                'hub',
                'library-metadata',
                'metadata-semantics',
                'mongo-s3-content-storage',
                's3-temporary-storage',
                'server',
                'storage-file-implementations'
            ],
            preload,
            detection: { order: ['querystring', 'header'], lookupQuerystring: 'language' }
        });
    return {
        i18n: instance,
        translate: (key, language) => instance.t(key, { lng: language }),
        middleware: i18nextHttpMiddleware.handle(instance)
    };
}
