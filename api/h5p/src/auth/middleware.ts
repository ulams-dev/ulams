import { timingSafeEqual, createHash } from 'node:crypto';
import type { NextFunction, Request, Response } from 'express';

import { JwtVerifier } from './jwt';
import { ProfileClient, ProfileUnauthorizedError } from './profile';
import { anonymousUser, H5PUser, systemUser } from './users';
import type { Logger } from '../logger';

/** Credentials that apply to a request (per tenant). */
export interface AuthContext {
    verifier?: JwtVerifier;
    profiles: ProfileClient;
    internalToken?: string;
}

export interface AuthMiddlewareOptions {
    /** Returns the auth context for the request (the request's tenant). */
    forRequest: (req: Request) => AuthContext;
    logger?: Pick<Logger, 'warn' | 'debug'>;
}

export interface RequestWithH5PUser extends Request {
    user: H5PUser;
}

/** Set by a proxy that holds the learner's token itself (front/web `/h5p`). */
export const SESSION_PROXY_HEADER = 'x-ulams-session-proxy';

/** Extracts the bearer token from the Authorization header or ?_token=. */
export function extractToken(req: Request): string | undefined {
    const header = req.headers.authorization;
    if (typeof header === 'string') {
        const match = /^Bearer\s+(.+)$/i.exec(header.trim());
        if (match && match[1].trim() !== '') {
            return match[1].trim();
        }
    }
    const q = (req.query as Record<string, unknown> | undefined)?._token;
    if (typeof q === 'string' && q !== '') {
        return q;
    }
    return undefined;
}

function safeEqual(a: string, b: string): boolean {
    // Hash first so the comparison is constant-time regardless of length.
    const ha = createHash('sha256').update(a).digest();
    const hb = createHash('sha256').update(b).digest();
    return timingSafeEqual(ha, hb);
}

/**
 * Resolves the H5P user for a request and stores it in req.user:
 *
 *  1. X-Internal-Token equal to H5P_INTERNAL_TOKEN  -> system user (all
 *     permissions). A wrong internal token is rejected with 401.
 *  2. Valid RS256 Passport JWT (header or ?_token=) -> LMS user; roles and
 *     permissions come from Laravel /api/profile/me (cached in Redis).
 *  3. Anything else (no token, bad signature, expired, revoked) -> anonymous.
 *
 * Players must keep working for anonymous learners, so authentication never
 * fails the request here; routes and the permission system decide.
 */
export function authMiddleware(options: AuthMiddlewareOptions) {
    return async (req: Request, res: Response, next: NextFunction): Promise<void> => {
        const r = req as RequestWithH5PUser;
        try {
            const { verifier, profiles, internalToken } = options.forRequest(req);
            const internal = req.headers['x-internal-token'];
            if (typeof internal === 'string' && internal !== '') {
                if (internalToken && safeEqual(internal, internalToken)) {
                    r.user = systemUser();
                    return next();
                }
                res.status(401).json({ success: false, message: 'Invalid internal token' });
                return;
            }

            const token = extractToken(req);
            if (!token || !verifier) {
                r.user = anonymousUser();
                return next();
            }

            let verified;
            try {
                verified = await verifier.verify(token);
            } catch (error) {
                options.logger?.debug({ err: (error as Error).message }, 'JWT rejected; continuing as anonymous');
                r.user = anonymousUser();
                return next();
            }

            let user: H5PUser;
            try {
                const profile = await profiles.get(token, verified.exp);
                user = {
                    id: verified.sub,
                    name: profile.name,
                    email: profile.email,
                    type: 'local',
                    roles: profile.roles,
                    permissions: profile.permissions,
                    isAnonymous: false,
                    isSystem: false
                };
                if (profile.id !== verified.sub) {
                    options.logger?.warn(
                        { sub: verified.sub, profileId: profile.id },
                        'Profile id does not match token subject; ignoring profile permissions'
                    );
                    user.permissions = [];
                    user.roles = [];
                }
            } catch (error) {
                if (error instanceof ProfileUnauthorizedError) {
                    // Signature was fine but Laravel says no (e.g. revoked token).
                    r.user = anonymousUser();
                    return next();
                }
                // Laravel unreachable: keep the learner identity (so progress can
                // still be saved) but grant no management permissions.
                options.logger?.warn({ err: (error as Error).message }, 'Profile lookup failed; using token subject only');
                user = {
                    id: verified.sub,
                    name: `User ${verified.sub}`,
                    email: '',
                    type: 'local',
                    roles: [],
                    permissions: [],
                    isAnonymous: false,
                    isSystem: false
                };
            }
            // Not enumerable: never ends up in JSON or logs. Not set at all for requests that came
            // through a session proxy (the Astro front's /h5p route adds the learner's token on the
            // server): the model's AJAX URLs must then stay without `?_token=`, so the token never
            // reaches the content frame; the proxy adds it to those calls as well.
            if (req.headers[SESSION_PROXY_HEADER] !== '1') {
                Object.defineProperty(user, 'token', { value: token, enumerable: false });
            }
            r.user = user;
            return next();
        } catch (error) {
            return next(error);
        }
    };
}
