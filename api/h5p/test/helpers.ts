import { randomBytes } from 'node:crypto';
import { exportSPKI, generateKeyPair, SignJWT } from 'jose';
import { Pool } from 'pg';
import { ListObjectsV2Command, S3 } from '@aws-sdk/client-s3';

import { AppConfig, loadConfig } from '../src/config';
import { qi } from '../src/db/pool';
import { deleteObjects } from '../src/storage/s3';

export const randomSuffix = (): string => randomBytes(4).toString('hex');

/**
 * Integration tests run inside a container on the ulams network, so the
 * defaults in loadConfig() (postgres, redis, minio) apply. Each test file uses
 * its own schema `h5p_test_<random>` and S3 prefix `h5p-test-<random>`.
 */
export function testConfig(overrides: Partial<{ schema: string; prefix: string }> = {}): AppConfig {
    const suffix = randomSuffix();
    const cfg = loadConfig();
    cfg.db.schema = overrides.schema ?? `h5p_test_${suffix}`;
    cfg.s3.prefix = overrides.prefix ?? `h5p-test-${suffix}`;
    cfg.redis.keyPrefix = `h5p-test-${suffix}:`;
    cfg.paths.libraries = `/tmp/h5p-test-${suffix}/libraries`;
    cfg.paths.temp = `/tmp/h5p-test-${suffix}/tmp`;
    cfg.h5p.enableHub = false;
    return cfg;
}

export async function dropSchema(pool: Pool, schema: string): Promise<void> {
    await pool.query(`DROP SCHEMA IF EXISTS ${qi(schema)} CASCADE`);
}

export async function deletePrefix(s3: S3, bucket: string, prefix: string): Promise<void> {
    const keys: string[] = [];
    let token: string | undefined;
    do {
        const res = await s3.send(
            new ListObjectsV2Command({ Bucket: bucket, Prefix: `${prefix}/`, ContinuationToken: token })
        );
        keys.push(...(res.Contents ?? []).map((c) => c.Key as string));
        token = res.IsTruncated ? res.NextContinuationToken : undefined;
    } while (token);
    if (keys.length > 0) {
        await deleteObjects(keys, bucket, s3);
    }
}

export interface KeyPair {
    publicPem: string;
    sign: (claims: Record<string, unknown>, opts?: { expiresInSec?: number; sub?: string }) => Promise<string>;
}

/** RSA key pair + signer producing Passport-like tokens (float iat/nbf/exp). */
export async function makeKeyPair(): Promise<KeyPair> {
    const { publicKey, privateKey } = await generateKeyPair('RS256', { extractable: true });
    const publicPem = await exportSPKI(publicKey);
    return {
        publicPem,
        sign: async (claims, opts = {}) => {
            const now = Date.now() / 1000;
            return new SignJWT({ scopes: [], ...claims, iat: now, nbf: now, exp: now + (opts.expiresInSec ?? 300) })
                .setProtectedHeader({ alg: 'RS256', typ: 'JWT' })
                .setSubject(opts.sub ?? '1')
                .setAudience('a2ee6383-c6c5-4ae7-9080-1d7280018278')
                .setJti(randomSuffix())
                .sign(privateKey);
        }
    };
}
