import {
    ContentId,
    ContentPermission,
    GeneralPermission,
    IPermissionSystem,
    TemporaryFilePermission,
    UserDataPermission
} from '@lumieducation/h5p-server';

import { H5PUser, hasAnyPermission, hasPermission, isAuthenticated } from './users';

/** Looks up the creator (users.id as string) of a content object. */
export type OwnerLookup = (contentId: ContentId) => Promise<string | undefined>;

/** Users who may use the editor (and therefore temporary files). */
export function isEditor(user: H5PUser | undefined): boolean {
    return hasAnyPermission(user, 'h5p_create', 'h5p_update', 'h5p_author_update');
}

/**
 * Maps Lumi permission checks to the LMS h5p_* permissions.
 *
 * Content
 *   View / Download / Embed  everyone, including anonymous learners
 *   List                     h5p_list, or h5p_author_list (the list route then
 *                            restricts to own content)
 *   Create                   h5p_create
 *   Edit                     h5p_update, or h5p_author_update + owner
 *   Delete                   h5p_delete, or h5p_author_delete + owner
 *
 * User data (state / finished)
 *   own data                 authenticated users (read access for anonymous
 *                            is allowed but there is never anything stored)
 *   other users' data        view only, h5p_read ("asUserId" for admins);
 *                            write/delete only for the internal system user
 *   whole-content deletes    users who may delete content (affectedUserId
 *                            undefined; Lumi calls this after deleting content)
 *
 * General
 *   CreateRestricted, InstallRecommended, UpdateAndInstallLibraries
 *                            h5p_library_install or h5p_library_update
 *
 * Temporary files            editor users (h5p_create / h5p_update /
 *                            h5p_author_update)
 *
 * The internal system user passes every check.
 */
export class LmsPermissionSystem implements IPermissionSystem<H5PUser> {
    constructor(private readonly getOwner: OwnerLookup) {}

    private async isOwner(user: H5PUser, contentId: ContentId | undefined): Promise<boolean> {
        if (!contentId || !isAuthenticated(user)) {
            return false;
        }
        const owner = await this.getOwner(contentId);
        return owner !== undefined && owner === String(user.id);
    }

    public async checkForContent(
        user: H5PUser | undefined,
        permission: ContentPermission,
        contentId?: ContentId
    ): Promise<boolean> {
        if (!user) {
            return false;
        }
        if (user.isSystem) {
            return true;
        }
        switch (permission) {
            case ContentPermission.View:
            case ContentPermission.Download:
            case ContentPermission.Embed:
                return true;
            case ContentPermission.List:
                return hasAnyPermission(user, 'h5p_list', 'h5p_author_list');
            case ContentPermission.Create:
                return hasPermission(user, 'h5p_create');
            case ContentPermission.Edit:
                if (hasPermission(user, 'h5p_update')) {
                    return true;
                }
                return hasPermission(user, 'h5p_author_update') && (await this.isOwner(user, contentId));
            case ContentPermission.Delete:
                if (hasPermission(user, 'h5p_delete')) {
                    return true;
                }
                return hasPermission(user, 'h5p_author_delete') && (await this.isOwner(user, contentId));
            default:
                return false;
        }
    }

    public async checkForUserData(
        user: H5PUser | undefined,
        permission: UserDataPermission,
        _contentId: ContentId,
        affectedUserId?: string
    ): Promise<boolean> {
        if (!user) {
            return false;
        }
        if (user.isSystem) {
            return true;
        }
        const isRead =
            permission === UserDataPermission.ViewState ||
            permission === UserDataPermission.ListStates ||
            permission === UserDataPermission.ViewFinished;

        // Content-wide operations (affectedUserId undefined): Lumi deletes all
        // states/finished data of a content object after deleting the content.
        if (affectedUserId === undefined) {
            if (
                permission === UserDataPermission.DeleteState ||
                permission === UserDataPermission.DeleteFinished
            ) {
                return hasAnyPermission(user, 'h5p_delete', 'h5p_author_delete', 'h5p_update');
            }
            return isRead && hasPermission(user, 'h5p_read');
        }

        if (String(affectedUserId) === String(user.id)) {
            if (isRead) {
                // Anonymous may "read" its own (always empty) state so the
                // player model can be generated; it can never write any.
                return true;
            }
            return isAuthenticated(user);
        }

        // Somebody else's data: admins with h5p_read may look (asUserId).
        return isRead && isAuthenticated(user) && hasPermission(user, 'h5p_read');
    }

    public async checkForTemporaryFile(
        user: H5PUser | undefined,
        _permission: TemporaryFilePermission,
        _filename?: string
    ): Promise<boolean> {
        if (!user) {
            return false;
        }
        return user.isSystem || (isAuthenticated(user) && isEditor(user));
    }

    public async checkForGeneralAction(user: H5PUser | undefined, permission: GeneralPermission): Promise<boolean> {
        if (!user) {
            return false;
        }
        if (user.isSystem) {
            return true;
        }
        switch (permission) {
            case GeneralPermission.CreateRestricted:
            case GeneralPermission.InstallRecommended:
            case GeneralPermission.UpdateAndInstallLibraries:
                return hasAnyPermission(user, 'h5p_library_install', 'h5p_library_update');
            default:
                return false;
        }
    }
}
