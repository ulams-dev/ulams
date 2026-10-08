<?php

namespace Ulams\Lti\Support;

/**
 * LTI 1.3 / AGS / Deep Linking claim names and our endpoint URLs.
 */
final class Lti
{
    public const VERSION = '1.3.0';

    public const CLAIM_MESSAGE_TYPE = 'https://purl.imsglobal.org/spec/lti/claim/message_type';
    public const CLAIM_VERSION = 'https://purl.imsglobal.org/spec/lti/claim/version';
    public const CLAIM_DEPLOYMENT_ID = 'https://purl.imsglobal.org/spec/lti/claim/deployment_id';
    public const CLAIM_TARGET_LINK_URI = 'https://purl.imsglobal.org/spec/lti/claim/target_link_uri';
    public const CLAIM_RESOURCE_LINK = 'https://purl.imsglobal.org/spec/lti/claim/resource_link';
    public const CLAIM_ROLES = 'https://purl.imsglobal.org/spec/lti/claim/roles';
    public const CLAIM_CONTEXT = 'https://purl.imsglobal.org/spec/lti/claim/context';
    public const CLAIM_TOOL_PLATFORM = 'https://purl.imsglobal.org/spec/lti/claim/tool_platform';
    public const CLAIM_LAUNCH_PRESENTATION = 'https://purl.imsglobal.org/spec/lti/claim/launch_presentation';
    public const CLAIM_CUSTOM = 'https://purl.imsglobal.org/spec/lti/claim/custom';
    public const CLAIM_AGS = 'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint';
    public const CLAIM_DL_SETTINGS = 'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings';
    public const CLAIM_DL_CONTENT_ITEMS = 'https://purl.imsglobal.org/spec/lti-dl/claim/content_items';
    public const CLAIM_DL_DATA = 'https://purl.imsglobal.org/spec/lti-dl/claim/data';
    public const CLAIM_DL_MSG = 'https://purl.imsglobal.org/spec/lti-dl/claim/msg';
    public const CLAIM_DL_ERRORMSG = 'https://purl.imsglobal.org/spec/lti-dl/claim/errormsg';

    public const MSG_RESOURCE_LINK = 'LtiResourceLinkRequest';
    public const MSG_DEEP_LINKING_REQUEST = 'LtiDeepLinkingRequest';
    public const MSG_DEEP_LINKING_RESPONSE = 'LtiDeepLinkingResponse';

    public const ROLE_LEARNER = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner';
    public const ROLE_INSTRUCTOR = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';
    public const ROLE_ADMINISTRATOR = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator';
    public const ROLE_SYSTEM_ADMINISTRATOR = 'http://purl.imsglobal.org/vocab/lis/v2/system/person#Administrator';
    public const CONTEXT_COURSE_OFFERING = 'http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering';

    public const SCOPE_LINEITEM = 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem';
    public const SCOPE_LINEITEM_READONLY = 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem.readonly';
    public const SCOPE_RESULT_READONLY = 'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly';
    public const SCOPE_SCORE = 'https://purl.imsglobal.org/spec/lti-ags/scope/score';

    public const AGS_SCOPES = [self::SCOPE_LINEITEM, self::SCOPE_LINEITEM_READONLY, self::SCOPE_RESULT_READONLY, self::SCOPE_SCORE];

    public static function issuer(): string
    {
        return rtrim((string) (config('ulams_lti.issuer') ?: config('app.url')), '/');
    }

    public static function url(string $path): string
    {
        return self::issuer() . '/' . ltrim($path, '/');
    }

    /**
     * Our endpoints, as shown to administrators when they register a tool or a platform.
     */
    public static function endpoints(): array
    {
        return [
            'issuer' => self::issuer(),
            'jwks_url' => self::url('api/lti/jwks'),
            'platform' => [
                'oidc_auth_url' => self::url('api/lti/platform/authorize'),
                'token_url' => self::url('api/lti/platform/token'),
                'deep_linking_return_url' => self::url('api/lti/platform/deep-links'),
            ],
            'tool' => [
                'oidc_login_url' => self::url('api/lti/tool/login'),
                'launch_url' => self::url('api/lti/tool/launch'),
                'deep_linking_url' => self::url('api/lti/tool/launch'),
            ],
        ];
    }
}
