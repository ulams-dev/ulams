<?php

namespace Ulams\Identity\Exceptions;

use RuntimeException;

/**
 * An OpenID Connect step that failed. The reason is a code for the audit log and the tests; the message never
 * carries tokens, codes or response bodies, and the browser only ever sees a coarse reason.
 */
class OidcException extends RuntimeException
{
    public const DISCOVERY_FAILED = 'discovery_failed';
    public const ISSUER_MISMATCH = 'issuer_mismatch';
    public const INSECURE_ENDPOINT = 'insecure_endpoint';
    public const TOKEN_REQUEST_FAILED = 'token_request_failed';
    public const NO_ID_TOKEN = 'no_id_token';
    public const JWKS_FAILED = 'jwks_failed';
    public const BAD_ALGORITHM = 'bad_algorithm';
    public const UNKNOWN_KEY = 'unknown_key';
    public const BAD_SIGNATURE = 'bad_signature';
    public const EXPIRED = 'expired';
    public const NOT_YET_VALID = 'not_yet_valid';
    public const WRONG_ISSUER = 'wrong_issuer';
    public const WRONG_AUDIENCE = 'wrong_audience';
    public const WRONG_AUTHORIZED_PARTY = 'wrong_authorized_party';
    public const NONCE_MISMATCH = 'nonce_mismatch';
    public const MISSING_CLAIM = 'missing_claim';
    public const MALFORMED = 'malformed_token';
    public const USERINFO_FAILED = 'userinfo_failed';
    public const SUBJECT_MISMATCH = 'subject_mismatch';

    public function __construct(public readonly string $reason, ?\Throwable $previous = null)
    {
        parent::__construct('OpenID Connect step failed: ' . $reason, 0, $previous);
    }
}
