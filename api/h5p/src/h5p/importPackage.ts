import * as H5P from '@lumieducation/h5p-server';

import { HttpError } from '../http/respond';

export interface ImportResult {
    contentId: string;
    metadata: H5P.IContentMetadata;
    installedLibraries: { type: string; library: string }[];
}

/** Library ubername "H5P.MultiChoice 1.16" from package metadata. */
export function mainLibraryUbername(metadata: H5P.IContentMetadata): string {
    const main = metadata.preloadedDependencies?.find((d) => d.machineName === metadata.mainLibrary);
    if (!main) {
        throw new HttpError(422, `The package does not list its main library ${metadata.mainLibrary} as a dependency.`);
    }
    return `${main.machineName} ${main.majorVersion}.${main.minorVersion}`;
}

/**
 * Imports a .h5p package the way the H5P editor does: uploadPackage()
 * installs missing/newer libraries (if the user may install libraries) and
 * puts content files into temporary storage, then saving the content moves
 * them into permanent storage.
 */
export async function importPackage(
    editor: H5P.H5PEditor,
    source: Buffer | string,
    user: H5P.IUser
): Promise<ImportResult> {
    const { metadata, parameters, installedLibraries } = await editor.uploadPackage(source, user);
    if (!metadata) {
        throw new HttpError(422, 'The package contains no content.');
    }
    const saved = await editor.saveOrUpdateContentReturnMetaData(
        undefined as unknown as string,
        parameters,
        metadata,
        mainLibraryUbername(metadata),
        user
    );
    // For new content Lumi copies (not moves) the package's files from
    // temporary storage into the content, under the same names. Nobody else
    // references those temporary copies, so delete them now instead of
    // leaving them to the expiry sweep.
    const files = await editor.contentStorage.listFiles(saved.id, user);
    await Promise.all(
        files.map((file) => editor.temporaryFileManager.deleteFile(file, user).catch(() => undefined))
    );
    return {
        contentId: saved.id,
        metadata: saved.metadata,
        installedLibraries: (installedLibraries ?? [])
            .filter((l) => l.type !== 'none' && (l.newVersion || l.oldVersion))
            .map((l) => ({
                type: l.type,
                library: H5P.LibraryName.toUberName((l.newVersion ?? l.oldVersion) as H5P.ILibraryName)
            }))
    };
}
