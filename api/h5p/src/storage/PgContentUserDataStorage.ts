import { Pool } from 'pg';
import {
    ContentId,
    H5pError,
    IContentUserData,
    IContentUserDataStorage,
    IFinishedUserData,
    IUser
} from '@lumieducation/h5p-server';

import { qi } from '../db/pool';
import PgContentStorage from './PgContentStorage';

interface UserDataRow {
    content_id: string;
    user_id: string;
    data_type: string;
    sub_content_id: string;
    context_id: string;
    user_state: string;
    preload: boolean;
    invalidate: boolean;
}

interface FinishedRow {
    content_id: string;
    user_id: string;
    score: number;
    max_score: number;
    opened_timestamp: number | null;
    finished_timestamp: number | null;
    completion_time: number | null;
}

const USER_DATA_COLUMNS =
    'content_id::text AS content_id, user_id, data_type, sub_content_id, context_id, user_state, preload, invalidate';
const FINISHED_COLUMNS =
    'content_id::text AS content_id, user_id, score, max_score, opened_timestamp, finished_timestamp, completion_time';

/**
 * Writes for content that does not exist (bad id or FK violation) surface as
 * 404 instead of a generic 500.
 */
async function guardWrite(contentId: ContentId, op: () => Promise<unknown>): Promise<void> {
    if (!PgContentStorage.isValidId(contentId)) {
        throw new H5pError('mongo-s3-content-storage:content-not-found', {}, 404);
    }
    try {
        await op();
    } catch (error: any) {
        if (error?.code === '23503') {
            throw new H5pError('mongo-s3-content-storage:content-not-found', {}, 404);
        }
        throw error;
    }
}

/** contextId is optional in Lumi; we store "no context" as ''. */
const ctx = (contextId?: string | null): string => contextId ?? '';

function toUserData(row: UserDataRow): IContentUserData {
    return {
        contentId: row.content_id,
        userId: row.user_id,
        dataType: row.data_type,
        subContentId: row.sub_content_id,
        contextId: row.context_id === '' ? undefined : row.context_id,
        userState: row.user_state,
        preload: row.preload,
        invalidate: row.invalidate
    };
}

function toFinished(row: FinishedRow): IFinishedUserData {
    return {
        contentId: row.content_id,
        userId: row.user_id,
        score: row.score,
        maxScore: row.max_score,
        openedTimestamp: row.opened_timestamp as number,
        finishedTimestamp: row.finished_timestamp as number,
        completionTime: row.completion_time as number
    };
}

/**
 * Postgres storage for user states (`<schema>.content_user_data`) and
 * completion data (`<schema>.finished_data`).
 *
 * Port of @lumieducation/h5p-mongos3 MongoContentUserDataStorage
 * (GPL-3.0-or-later). Rows reference `<schema>.contents` with ON DELETE
 * CASCADE, so deleting content also deletes its user data.
 */
export default class PgContentUserDataStorage implements IContentUserDataStorage {
    private readonly userData: string;
    private readonly finished: string;

    constructor(
        private readonly pool: Pool,
        schema: string
    ) {
        this.userData = `${qi(schema)}.content_user_data`;
        this.finished = `${qi(schema)}.finished_data`;
    }

    public async getContentUserData(
        contentId: ContentId,
        dataType: string,
        subContentId: string,
        userId: string,
        contextId?: string
    ): Promise<IContentUserData> {
        if (!PgContentStorage.isValidId(contentId)) {
            return undefined as unknown as IContentUserData;
        }
        const { rows } = await this.pool.query<UserDataRow>(
            `SELECT ${USER_DATA_COLUMNS} FROM ${this.userData}
              WHERE content_id = $1::bigint AND data_type = $2 AND sub_content_id = $3
                AND user_id = $4 AND context_id = $5`,
            [contentId, dataType, String(subContentId), userId, ctx(contextId)]
        );
        // Lumi expects undefined/null when there is no state.
        return (rows[0] ? toUserData(rows[0]) : undefined) as IContentUserData;
    }

    public async getContentUserDataByUser(user: IUser): Promise<IContentUserData[]> {
        const { rows } = await this.pool.query<UserDataRow>(
            `SELECT ${USER_DATA_COLUMNS} FROM ${this.userData} WHERE user_id = $1 ORDER BY content_id, id`,
            [user.id]
        );
        return rows.map(toUserData);
    }

    public async getContentUserDataByContentIdAndUser(
        contentId: ContentId,
        userId: string,
        contextId?: string
    ): Promise<IContentUserData[]> {
        if (!PgContentStorage.isValidId(contentId)) {
            return [];
        }
        const { rows } = await this.pool.query<UserDataRow>(
            `SELECT ${USER_DATA_COLUMNS} FROM ${this.userData}
              WHERE content_id = $1::bigint AND user_id = $2 AND context_id = $3
              ORDER BY id`,
            [contentId, userId, ctx(contextId)]
        );
        return rows.map(toUserData);
    }

    public async createOrUpdateContentUserData(userData: IContentUserData): Promise<void> {
        await guardWrite(userData.contentId as string, () => this.pool.query(
            `INSERT INTO ${this.userData}
                (content_id, user_id, data_type, sub_content_id, context_id, user_state, preload, invalidate)
             VALUES ($1::bigint, $2, $3, $4, $5, $6, $7, $8)
             ON CONFLICT ON CONSTRAINT content_user_data_unique DO UPDATE
                SET user_state = EXCLUDED.user_state,
                    preload = EXCLUDED.preload,
                    invalidate = EXCLUDED.invalidate,
                    updated_at = now()`,
            [
                userData.contentId,
                userData.userId,
                userData.dataType,
                String(userData.subContentId),
                ctx(userData.contextId),
                userData.userState ?? '',
                Boolean(userData.preload),
                Boolean(userData.invalidate)
            ]
        ));
    }

    public async createOrUpdateFinishedData(finishedData: IFinishedUserData): Promise<void> {
        await guardWrite(finishedData.contentId, () => this.pool.query(
            `INSERT INTO ${this.finished}
                (content_id, user_id, score, max_score, opened_timestamp, finished_timestamp, completion_time)
             VALUES ($1::bigint, $2, $3, $4, $5, $6, $7)
             ON CONFLICT (content_id, user_id) DO UPDATE
                SET score = EXCLUDED.score,
                    max_score = EXCLUDED.max_score,
                    opened_timestamp = EXCLUDED.opened_timestamp,
                    finished_timestamp = EXCLUDED.finished_timestamp,
                    completion_time = EXCLUDED.completion_time,
                    updated_at = now()`,
            [
                finishedData.contentId,
                finishedData.userId,
                Number(finishedData.score) || 0,
                Number(finishedData.maxScore) || 0,
                finishedData.openedTimestamp ?? null,
                finishedData.finishedTimestamp ?? null,
                finishedData.completionTime ?? null
            ]
        ));
    }

    public async deleteInvalidatedContentUserData(contentId: ContentId): Promise<void> {
        if (!PgContentStorage.isValidId(contentId)) {
            return;
        }
        await this.pool.query(`DELETE FROM ${this.userData} WHERE content_id = $1::bigint AND invalidate`, [
            contentId
        ]);
    }

    public async deleteAllContentUserDataByUser(user: IUser): Promise<void> {
        await this.pool.query(`DELETE FROM ${this.userData} WHERE user_id = $1`, [user.id]);
    }

    public async deleteAllContentUserDataByContentId(contentId: ContentId): Promise<void> {
        if (!PgContentStorage.isValidId(contentId)) {
            return;
        }
        await this.pool.query(`DELETE FROM ${this.userData} WHERE content_id = $1::bigint`, [contentId]);
    }

    public async getFinishedDataByContentId(contentId: ContentId): Promise<IFinishedUserData[]> {
        if (!PgContentStorage.isValidId(contentId)) {
            return [];
        }
        const { rows } = await this.pool.query<FinishedRow>(
            `SELECT ${FINISHED_COLUMNS} FROM ${this.finished} WHERE content_id = $1::bigint ORDER BY user_id`,
            [contentId]
        );
        return rows.map(toFinished);
    }

    public async getFinishedDataByUser(user: IUser): Promise<IFinishedUserData[]> {
        const { rows } = await this.pool.query<FinishedRow>(
            `SELECT ${FINISHED_COLUMNS} FROM ${this.finished} WHERE user_id = $1 ORDER BY content_id`,
            [user.id]
        );
        return rows.map(toFinished);
    }

    public async deleteFinishedDataByContentId(contentId: ContentId): Promise<void> {
        if (!PgContentStorage.isValidId(contentId)) {
            return;
        }
        await this.pool.query(`DELETE FROM ${this.finished} WHERE content_id = $1::bigint`, [contentId]);
    }

    public async deleteFinishedDataByUser(user: IUser): Promise<void> {
        await this.pool.query(`DELETE FROM ${this.finished} WHERE user_id = $1`, [user.id]);
    }
}
