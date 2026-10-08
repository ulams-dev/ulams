import { IUser } from '@lumieducation/h5p-server';

/**
 * The user object we put on req.user. It satisfies Lumi's IUser and carries
 * the LMS roles/permissions the permission system needs.
 */
export interface H5PUser extends IUser {
    id: string;
    name: string;
    email: string;
    type: 'local';
    roles: string[];
    permissions: string[];
    /** True for the shared anonymous learner (no or invalid token). */
    isAnonymous: boolean;
    /** True for internal service calls (X-Internal-Token). */
    isSystem: boolean;
    /**
     * The bearer token the request was authenticated with. Used by the
     * UrlGenerator to append `?_token=` to H5P AJAX URLs, because H5P core
     * cannot send an Authorization header. Never serialised.
     */
    token?: string;
}

export const ANONYMOUS_ID = 'anonymous';
export const SYSTEM_ID = 'system';

/** All h5p_* permissions known to the LMS. */
export const ALL_H5P_PERMISSIONS = [
    'h5p_list',
    'h5p_read',
    'h5p_create',
    'h5p_update',
    'h5p_delete',
    'h5p_author_list',
    'h5p_author_update',
    'h5p_author_delete',
    'h5p_library_list',
    'h5p_library_read',
    'h5p_library_create',
    'h5p_library_update',
    'h5p_library_delete',
    'h5p_library_install',
    'h5p_library_upload'
] as const;

export function anonymousUser(): H5PUser {
    return {
        id: ANONYMOUS_ID,
        name: 'Anonymous',
        email: '',
        type: 'local',
        roles: [],
        permissions: [],
        isAnonymous: true,
        isSystem: false
    };
}

export function systemUser(): H5PUser {
    return {
        id: SYSTEM_ID,
        name: 'System',
        email: '',
        type: 'local',
        roles: ['system'],
        permissions: [...ALL_H5P_PERMISSIONS],
        isAnonymous: false,
        isSystem: true
    };
}

export function hasPermission(user: H5PUser | undefined, permission: string): boolean {
    if (!user) {
        return false;
    }
    return user.isSystem === true || (user.permissions ?? []).includes(permission);
}

export function hasAnyPermission(user: H5PUser | undefined, ...permissions: string[]): boolean {
    return permissions.some((p) => hasPermission(user, p));
}

/** Logged-in LMS user (not anonymous). The system user counts as authenticated. */
export function isAuthenticated(user: H5PUser | undefined): boolean {
    return !!user && !user.isAnonymous && user.id !== ANONYMOUS_ID;
}
