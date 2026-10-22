<?php

namespace Ulams\Identity\Exceptions;

use RuntimeException;

/**
 * A SAML message this service provider does not accept. `reason` is a fixed code for the audit log and the browser
 * (never text from the message itself, which an attacker controls).
 */
class SamlException extends RuntimeException
{
    public const TOO_LARGE = 'too_large';
    public const BAD_ENCODING = 'bad_encoding';
    public const DOCTYPE = 'doctype';
    public const INVALID_XML = 'invalid_xml';
    public const MISCONFIGURED = 'misconfigured';
    public const UNSIGNED = 'unsigned';
    public const SIGNATURE_INVALID = 'signature_invalid';
    public const ALGORITHM_NOT_ALLOWED = 'algorithm_not_allowed';
    public const SHA1_NOT_ALLOWED = 'sha1_not_allowed';
    public const AUDIENCE = 'audience_mismatch';
    public const DESTINATION = 'destination_mismatch';
    public const RECIPIENT = 'recipient_mismatch';
    public const ISSUER = 'issuer_mismatch';
    public const IN_RESPONSE_TO = 'in_response_to_mismatch';
    public const EXPIRED = 'expired';
    public const NOT_YET_VALID = 'not_yet_valid';
    public const TOO_OLD = 'assertion_too_old';
    public const REPLAYED = 'replayed';
    public const NOT_SUCCESS = 'status_not_success';
    public const ASSERTION_COUNT = 'assertion_count';
    public const SUBJECT = 'subject_confirmation';
    public const ENCRYPTION_REQUIRED = 'encryption_required';
    public const DECRYPTION_FAILED = 'decryption_failed';
    public const NO_NAME_ID = 'no_name_id';
    public const TRANSIENT_NAME_ID = 'transient_name_id';
    public const COMMENT_IN_VALUE = 'comment_in_value';
    public const SIGNATURE_STRUCTURE = 'signature_structure';
    public const INVALID = 'invalid_response';
    public const METADATA_UNREACHABLE = 'metadata_unreachable';
    public const METADATA_INVALID = 'metadata_invalid';
    public const CERTIFICATE_INVALID = 'certificate_invalid';
    public const CERTIFICATE_WEAK = 'certificate_weak';

    public function __construct(public readonly string $reason)
    {
        parent::__construct('SAML message refused: ' . $reason);
    }
}
