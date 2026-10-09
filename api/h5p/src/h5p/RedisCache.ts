import type { RedisClientType } from 'redis';

/**
 * Minimal cache-manager v4 compatible cache backed by Redis.
 *
 * @lumieducation/h5p-server@10.0.4 (the published build) calls the cache
 * passed to CachedKeyValueStorage and CachedLibraryStorage with the
 * cache-manager v4 API: get(key), set(key, value, { ttl }), del(key),
 * wrap(key, fn) and reset(). cache-manager v5+ (and Keyv-based stores) changed
 * those signatures (ttl in ms as a number, clear() instead of reset()), so we
 * implement the v4 contract directly on the redis client the lock provider
 * already uses instead of pulling in an old cache-manager store.
 *
 * Values are JSON-serialised; Date and Buffer survive the round trip (the
 * library cache stores fs stats with Date fields).
 */
export interface CacheTtlOptions {
    /** Seconds; 0 = no expiry. */
    ttl?: number;
}

const DATE_TAG = '__date';
const BUFFER_TAG = '__buffer';

export function serialize(value: unknown): string {
    return JSON.stringify(value, function replacer(this: any, key: string, val: unknown) {
        const raw = this[key];
        if (raw instanceof Date) {
            return { [DATE_TAG]: raw.toISOString() };
        }
        if (Buffer.isBuffer(raw)) {
            return { [BUFFER_TAG]: raw.toString('base64') };
        }
        return val;
    });
}

export function deserialize(text: string): unknown {
    return JSON.parse(text, (_key, val) => {
        if (val && typeof val === 'object' && !Array.isArray(val)) {
            const keys = Object.keys(val);
            if (keys.length === 1 && keys[0] === DATE_TAG) {
                return new Date(val[DATE_TAG]);
            }
            if (keys.length === 1 && keys[0] === BUFFER_TAG) {
                return Buffer.from(val[BUFFER_TAG], 'base64');
            }
        }
        return val;
    });
}

export class RedisCache {
    /**
     * @param redis connected node-redis v4 client
     * @param namespace key prefix, e.g. "h5p:lib:"
     * @param defaultTtlSec default TTL in seconds (0 = no expiry)
     */
    constructor(
        private readonly redis: RedisClientType<any, any, any>,
        private readonly namespace: string,
        private readonly defaultTtlSec = 0
    ) {}

    private k(key: string): string {
        return `${this.namespace}${key}`;
    }

    private ttlOf(options?: CacheTtlOptions | number): number {
        if (typeof options === 'number') {
            return options;
        }
        return options?.ttl ?? this.defaultTtlSec;
    }

    public async get<T = any>(key: string): Promise<T | undefined> {
        const raw = await this.redis.get(this.k(key));
        if (raw === null || raw === undefined) {
            return undefined;
        }
        return deserialize(raw) as T;
    }

    public async set<T = any>(key: string, value: T, options?: CacheTtlOptions | number): Promise<T> {
        if (value === undefined) {
            await this.del(key);
            return value;
        }
        const ttl = this.ttlOf(options);
        const payload = serialize(value);
        if (ttl > 0) {
            await this.redis.set(this.k(key), payload, { EX: Math.ceil(ttl) });
        } else {
            await this.redis.set(this.k(key), payload);
        }
        return value;
    }

    public async del(key: string): Promise<void> {
        await this.redis.del(this.k(key));
    }

    public async wrap<T>(key: string, fn: () => Promise<T>, options?: CacheTtlOptions | number): Promise<T> {
        const cached = await this.get<T>(key);
        if (cached !== undefined) {
            return cached;
        }
        const value = await fn();
        if (value !== undefined) {
            await this.set(key, value, options);
        }
        return value;
    }

    /** Deletes every key in this namespace (SCAN, never KEYS). */
    public async reset(): Promise<void> {
        const batch: string[] = [];
        for await (const key of this.redis.scanIterator({ MATCH: `${this.namespace}*`, COUNT: 500 })) {
            batch.push(key as string);
            if (batch.length >= 500) {
                await this.redis.del(batch.splice(0, batch.length));
            }
        }
        if (batch.length > 0) {
            await this.redis.del(batch);
        }
    }
}
