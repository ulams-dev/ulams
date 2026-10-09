import path from 'node:path';
import { Pool } from 'pg';
import { S3 } from '@aws-sdk/client-s3';
import type { RedisClientType } from 'redis';
import * as H5P from '@lumieducation/h5p-server';
import RedisLockProvider from '@lumieducation/h5p-redis-lock';
import SvgSanitizer from '@lumieducation/h5p-svg-sanitizer';

import { AppConfig } from '../config';
import { LmsPermissionSystem } from '../auth/PermissionSystem';
import { H5PUser } from '../auth/users';
import PgContentStorage from '../storage/PgContentStorage';
import PgContentUserDataStorage from '../storage/PgContentUserDataStorage';
import S3TemporaryFileStorage from '../storage/S3TemporaryFileStorage';
import { RedisCache } from './RedisCache';

/** Per-tenant H5P object graph (editor/player bound to the tenant's DB + bucket). */
export interface H5PServices {
    config: H5P.H5PConfig;
    editor: H5P.H5PEditor;
    player: H5P.H5PPlayer;
    contentStorage: PgContentStorage;
    userDataStorage: PgContentUserDataStorage;
    temporaryStorage: S3TemporaryFileStorage;
    permissionSystem: LmsPermissionSystem;
}

/** Process-wide pieces shared by all tenants. */
export interface SharedH5P {
    redis: RedisClientType<any, any, any>;
    /** One library volume (+ Redis cache) for every tenant. */
    libraryStorage: H5P.ILibraryStorage;
    libraryCache: RedisCache;
    lockProvider: H5P.ILockProvider;
    translate?: H5P.ITranslationFunction;
}

/** What differs between tenants. */
export interface TenantStorageSettings {
    /** Used to namespace Redis keys (content type cache, hub uuid). */
    id: string;
    pool: Pool;
    s3: S3;
    schema: string;
    s3Bucket: string;
    s3Prefix: string;
    s3MaxKeyLength: number;
}

/**
 * Only the hub registration uuid is persisted from H5PConfig; every other
 * setting comes from environment variables so a stale stored value can never
 * override the deployment configuration.
 */
class UuidOnlyStorage implements H5P.IKeyValueStorage {
    constructor(private readonly inner: H5P.IKeyValueStorage) {}
    async load(key: string): Promise<any> {
        return key === 'uuid' ? this.inner.load(key) : undefined;
    }
    async save(key: string, value: any): Promise<any> {
        return key === 'uuid' ? this.inner.save(key, value) : undefined;
    }
}

export function createSharedH5P(
    app: AppConfig,
    redis: RedisClientType<any, any, any>,
    translate?: H5P.ITranslationFunction
): SharedH5P {
    const libraryCache = new RedisCache(redis, `${app.redis.keyPrefix}lib:`, 60 * 60 * 24);
    const libraryStorage = new H5P.cacheImplementations.CachedLibraryStorage(
        new H5P.fsImplementations.FileLibraryStorage(app.paths.libraries),
        libraryCache as any
    );
    return {
        redis,
        libraryStorage,
        libraryCache,
        lockProvider: new RedisLockProvider(redis as any),
        translate
    };
}

export async function createH5P(
    app: AppConfig,
    shared: SharedH5P,
    tenant: TenantStorageSettings
): Promise<H5PServices> {
    // Content type cache and hub uuid per tenant (cache-manager v4 contract on Redis).
    const kvCache = new RedisCache(shared.redis, `${app.redis.keyPrefix}t:${tenant.id}:kv:`, 0);

    const config = new H5P.H5PConfig(
        new UuidOnlyStorage(new H5P.cacheImplementations.CachedKeyValueStorage('config', kvCache as any)),
        {
            baseUrl: app.h5pBaseUrl,
            maxFileSize: app.h5p.maxFileSize,
            maxTotalSize: app.h5p.maxTotalSize,
            contentUserStateSaveInterval: app.h5p.contentUserStateSaveIntervalMs,
            temporaryFileLifetime: app.h5p.temporaryFileLifetimeMs,
            setFinishedEnabled: true,
            fetchingDisabled: app.h5p.enableHub ? 0 : 1,
            contentHubEnabled: false,
            sendUsageStatistics: false,
            platformName: 'Wellms',
            platformVersion: '1.0',
            siteType: 'local',
            // SVGs are sanitised by SvgSanitizer below.
            contentWhitelist: `${new H5P.H5PConfig().contentWhitelist} svg`
        }
    );
    await config.load();

    // H5P core cannot send Authorization headers, so AJAX / state / finished
    // URLs carry the caller's token as ?_token= (picked up by authMiddleware).
    const urlGenerator = new H5P.UrlGenerator(config, {
        queryParamGenerator: (user: H5P.IUser) => {
            const token = (user as H5PUser).token;
            return token ? { name: '_token', value: token } : { name: '', value: '' };
        },
        protectAjax: true,
        protectContentUserData: true,
        protectSetFinished: true
    });

    const contentStorage = new PgContentStorage(tenant.pool, tenant.s3, {
        schema: tenant.schema,
        s3Bucket: tenant.s3Bucket,
        s3Prefix: tenant.s3Prefix,
        maxKeyLength: tenant.s3MaxKeyLength
    });
    const userDataStorage = new PgContentUserDataStorage(tenant.pool, tenant.schema);
    const temporaryStorage = new S3TemporaryFileStorage(tenant.s3, {
        s3Bucket: tenant.s3Bucket,
        s3Prefix: tenant.s3Prefix,
        temporaryFileLifetimeMs: app.h5p.temporaryFileLifetimeMs,
        maxKeyLength: tenant.s3MaxKeyLength
    });
    const permissionSystem = new LmsPermissionSystem((id) => contentStorage.getOwner(id));
    // Lumi types the permission system on IUser; ours works on H5PUser (req.user).
    const lumiPermissionSystem = permissionSystem as unknown as H5P.IPermissionSystem;

    const editor = new H5P.H5PEditor(
        new H5P.cacheImplementations.CachedKeyValueStorage('kvcache', kvCache as any),
        config,
        shared.libraryStorage,
        contentStorage,
        temporaryStorage,
        shared.translate,
        urlGenerator,
        {
            enableHubLocalization: true,
            enableLibraryNameLocalization: true,
            lockProvider: shared.lockProvider,
            permissionSystem: lumiPermissionSystem,
            fileSanitizers: [new SvgSanitizer()]
        },
        userDataStorage
    );
    editor.setRenderer((model) => model);

    const player = new H5P.H5PPlayer(
        editor.libraryStorage,
        editor.contentStorage,
        config,
        undefined,
        urlGenerator,
        shared.translate,
        { permissionSystem: lumiPermissionSystem },
        userDataStorage
    );
    player.setRenderer((model) => model);

    return { config, editor, player, contentStorage, userDataStorage, temporaryStorage, permissionSystem };
}

/** Directory with Lumi's server translations (i18next fs backend). */
export function translationsPath(): string {
    return path.join(
        path.dirname(require.resolve('@lumieducation/h5p-server/package.json')),
        'build/assets/translations/{{ns}}/{{lng}}.json'
    );
}
