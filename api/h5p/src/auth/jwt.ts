import { importSPKI, jwtVerify, JWTPayload, KeyObject, CryptoKey } from 'jose';
import { createPublicKey } from 'node:crypto';

export interface JwtVerifierOptions {
    /** PEM ("-----BEGIN PUBLIC KEY-----" or "BEGIN RSA PUBLIC KEY"). */
    publicKeyPem: string;
    audience?: string;
    issuer?: string;
    clockToleranceSec?: number;
}

export interface VerifiedToken {
    sub: string;
    exp: number;
    payload: JWTPayload;
}

export class JwtVerifier {
    private keyPromise: Promise<KeyObject | CryptoKey>;

    constructor(private readonly options: JwtVerifierOptions) {
        this.keyPromise = JwtVerifier.importKey(options.publicKeyPem);
    }

    private static async importKey(pem: string): Promise<KeyObject | CryptoKey> {
        const trimmed = pem.trim();
        if (trimmed.includes('BEGIN PUBLIC KEY')) {
            return importSPKI(trimmed, 'RS256');
        }
        // PKCS#1 ("BEGIN RSA PUBLIC KEY") - let node parse it.
        return createPublicKey({ key: trimmed, format: 'pem' });
    }

    /**
     * Verifies an RS256 Passport access token. Throws on any problem
     * (signature, algorithm, expiry, nbf, audience/issuer if configured).
     * Passport puts float timestamps into iat/nbf/exp; jose accepts those.
     */
    public async verify(token: string): Promise<VerifiedToken> {
        const key = await this.keyPromise;
        const { payload } = await jwtVerify(token, key, {
            algorithms: ['RS256'],
            clockTolerance: this.options.clockToleranceSec ?? 30,
            audience: this.options.audience,
            issuer: this.options.issuer,
            requiredClaims: ['sub', 'exp']
        });
        if (payload.sub === undefined || payload.sub === '') {
            throw new Error('Token has no subject');
        }
        return { sub: String(payload.sub), exp: Number(payload.exp), payload };
    }
}
