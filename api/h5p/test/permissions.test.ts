import { describe, expect, it } from 'vitest';
import {
    ContentPermission as C,
    GeneralPermission as G,
    TemporaryFilePermission as T,
    UserDataPermission as U
} from '@lumieducation/h5p-server';

import { LmsPermissionSystem } from '../src/auth/PermissionSystem';
import { anonymousUser, H5PUser, systemUser } from '../src/auth/users';

const OWNED_BY_10 = '1';
const OWNED_BY_20 = '2';
const owners: Record<string, string> = { [OWNED_BY_10]: '10', [OWNED_BY_20]: '20' };
const ps = new LmsPermissionSystem(async (id) => owners[id]);

function user(id: string, permissions: string[]): H5PUser {
    return { id, name: id, email: '', type: 'local', roles: [], permissions, isAnonymous: false, isSystem: false };
}

const anon = anonymousUser();
const system = systemUser();
const learner = user('30', []);
const admin = user('1', ['h5p_list', 'h5p_read', 'h5p_create', 'h5p_update', 'h5p_delete', 'h5p_library_install']);
const author = user('10', ['h5p_author_list', 'h5p_create', 'h5p_author_update', 'h5p_author_delete']);
const libAdmin = user('2', ['h5p_library_update']);

describe('content permissions', () => {
    it.each([C.View, C.Download, C.Embed])('everyone (incl. anonymous) may %s', async (p) => {
        for (const u of [anon, learner, author, admin]) {
            expect(await ps.checkForContent(u, p, OWNED_BY_10)).toBe(true);
        }
    });

    it('List needs h5p_list or h5p_author_list', async () => {
        expect(await ps.checkForContent(anon, C.List, undefined)).toBe(false);
        expect(await ps.checkForContent(learner, C.List, undefined)).toBe(false);
        expect(await ps.checkForContent(author, C.List, undefined)).toBe(true);
        expect(await ps.checkForContent(admin, C.List, undefined)).toBe(true);
    });

    it('Create needs h5p_create', async () => {
        expect(await ps.checkForContent(anon, C.Create, undefined)).toBe(false);
        expect(await ps.checkForContent(learner, C.Create, undefined)).toBe(false);
        expect(await ps.checkForContent(author, C.Create, undefined)).toBe(true);
        expect(await ps.checkForContent(admin, C.Create, undefined)).toBe(true);
    });

    it('Edit: h5p_update on anything, h5p_author_update only on own content', async () => {
        expect(await ps.checkForContent(admin, C.Edit, OWNED_BY_20)).toBe(true);
        expect(await ps.checkForContent(author, C.Edit, OWNED_BY_10)).toBe(true);
        expect(await ps.checkForContent(author, C.Edit, OWNED_BY_20)).toBe(false);
        expect(await ps.checkForContent(author, C.Edit, '999')).toBe(false); // unknown owner
        expect(await ps.checkForContent(learner, C.Edit, OWNED_BY_10)).toBe(false);
        expect(await ps.checkForContent(anon, C.Edit, OWNED_BY_10)).toBe(false);
    });

    it('Delete: h5p_delete on anything, h5p_author_delete only on own content', async () => {
        expect(await ps.checkForContent(admin, C.Delete, OWNED_BY_20)).toBe(true);
        expect(await ps.checkForContent(author, C.Delete, OWNED_BY_10)).toBe(true);
        expect(await ps.checkForContent(author, C.Delete, OWNED_BY_20)).toBe(false);
        expect(await ps.checkForContent(anon, C.Delete, OWNED_BY_10)).toBe(false);
    });

    it('an anonymous user can never own content', async () => {
        const fakeAnonAuthor = { ...anonymousUser(), id: '10', permissions: ['h5p_author_update'] };
        expect(await ps.checkForContent(fakeAnonAuthor, C.Edit, OWNED_BY_10)).toBe(false);
    });

    it('the system user may do everything', async () => {
        for (const p of [C.Create, C.Delete, C.Download, C.Edit, C.Embed, C.List, C.View]) {
            expect(await ps.checkForContent(system, p, OWNED_BY_20)).toBe(true);
        }
    });

    it('undefined user is denied', async () => {
        expect(await ps.checkForContent(undefined, C.View, OWNED_BY_10)).toBe(false);
    });
});

describe('user data permissions', () => {
    const writes = [U.EditState, U.DeleteState, U.EditFinished, U.DeleteFinished];
    const reads = [U.ViewState, U.ListStates, U.ViewFinished];

    it('authenticated users read and write only their own data', async () => {
        for (const p of [...writes, ...reads]) {
            expect(await ps.checkForUserData(learner, p, OWNED_BY_10, learner.id)).toBe(true);
            expect(await ps.checkForUserData(learner, p, OWNED_BY_10, author.id)).toBe(false);
        }
    });

    it('anonymous may read its own (always empty) state but never write', async () => {
        for (const p of reads) {
            expect(await ps.checkForUserData(anon, p, OWNED_BY_10, 'anonymous')).toBe(true);
        }
        for (const p of writes) {
            expect(await ps.checkForUserData(anon, p, OWNED_BY_10, 'anonymous')).toBe(false);
        }
        expect(await ps.checkForUserData(anon, U.ViewState, OWNED_BY_10, learner.id)).toBe(false);
    });

    it('h5p_read lets admins view (not change) other users\' data (asUserId)', async () => {
        for (const p of reads) {
            expect(await ps.checkForUserData(admin, p, OWNED_BY_10, learner.id)).toBe(true);
        }
        for (const p of writes) {
            expect(await ps.checkForUserData(admin, p, OWNED_BY_10, learner.id)).toBe(false);
        }
    });

    it('content-wide deletes (after content deletion) need a content delete permission', async () => {
        expect(await ps.checkForUserData(admin, U.DeleteState, OWNED_BY_10, undefined)).toBe(true);
        expect(await ps.checkForUserData(author, U.DeleteFinished, OWNED_BY_10, undefined)).toBe(true);
        expect(await ps.checkForUserData(learner, U.DeleteState, OWNED_BY_10, undefined)).toBe(false);
        expect(await ps.checkForUserData(anon, U.DeleteState, OWNED_BY_10, undefined)).toBe(false);
    });

    it('the system user may do everything', async () => {
        for (const p of [...writes, ...reads]) {
            expect(await ps.checkForUserData(system, p, OWNED_BY_10, learner.id)).toBe(true);
        }
    });
});

describe('general permissions', () => {
    it.each([G.CreateRestricted, G.InstallRecommended, G.UpdateAndInstallLibraries])(
        '%s needs h5p_library_install or h5p_library_update',
        async (p) => {
            expect(await ps.checkForGeneralAction(admin, p)).toBe(true); // install
            expect(await ps.checkForGeneralAction(libAdmin, p)).toBe(true); // update
            expect(await ps.checkForGeneralAction(author, p)).toBe(false);
            expect(await ps.checkForGeneralAction(learner, p)).toBe(false);
            expect(await ps.checkForGeneralAction(anon, p)).toBe(false);
            expect(await ps.checkForGeneralAction(system, p)).toBe(true);
        }
    );
});

describe('temporary file permissions', () => {
    it.each([T.Create, T.Delete, T.List, T.View])('%s is for authenticated editor users', async (p) => {
        expect(await ps.checkForTemporaryFile(admin, p, 'x.png')).toBe(true);
        expect(await ps.checkForTemporaryFile(author, p, 'x.png')).toBe(true);
        expect(await ps.checkForTemporaryFile(learner, p, 'x.png')).toBe(false);
        expect(await ps.checkForTemporaryFile(anon, p, 'x.png')).toBe(false);
        expect(await ps.checkForTemporaryFile(system, p, 'x.png')).toBe(true);
    });
});

