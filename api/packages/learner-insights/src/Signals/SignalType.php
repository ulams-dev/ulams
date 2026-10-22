<?php

namespace Ulams\LearnerInsights\Signals;

/**
 * The closed set of signal types and, per type, the only keys `data` may carry (ADR 0057). Values
 * are scalars; free text never gets in.
 */
final class SignalType
{
    public const COURSE_STARTED = 'course.started';
    public const COURSE_COMPLETED = 'course.completed';
    public const TOPIC_OPENED = 'topic.opened';
    public const TOPIC_PROGRESSED = 'topic.progressed';
    public const TOPIC_COMPLETED = 'topic.completed';
    public const QUIZ_ATTEMPT_STARTED = 'quiz.attempt_started';
    public const QUIZ_ATTEMPT_FINISHED = 'quiz.attempt_finished';
    public const QUIZ_ANSWER = 'quiz.answer';
    public const SCORM_STATUS = 'scorm.status';
    public const XAPI_RESULT = 'xapi.result';
    public const H5P_RESULT = 'h5p.result';
    public const MEDIA_REPLAYED = 'media.replayed';
    public const MEDIA_SEEKED_BACK = 'media.seeked_back';
    public const SESSION_LOGIN = 'session.login';
    public const REMEDIATION_SHOWN = 'remediation.shown';
    public const REMEDIATION_FEEDBACK = 'remediation.feedback';
    public const NUDGE_SENT = 'nudge.sent';
    public const NUDGE_OPENED = 'nudge.opened';
    public const TUTOR_QUESTION = 'tutor.question';
    public const CODE_ATTEMPT_FINISHED = 'code.attempt_finished';

    public const SOURCES = ['progress', 'gift', 'scorm', 'lrs', 'h5p', 'auth', 'client', 'tutor', 'remediation', 'nudge', 'backfill', 'code'];

    /** Types a browser may send through POST /api/learner-insights/signals. */
    public const CLIENT_TYPES = [self::MEDIA_REPLAYED, self::MEDIA_SEEKED_BACK];

    /** @var array<string,string[]> */
    private const DATA_KEYS = [
        self::COURSE_STARTED => [],
        self::COURSE_COMPLETED => [],
        self::TOPIC_OPENED => ['revisit'],
        self::TOPIC_PROGRESSED => ['status'],
        self::TOPIC_COMPLETED => [],
        self::QUIZ_ATTEMPT_STARTED => ['attempt_no'],
        self::QUIZ_ATTEMPT_FINISHED => ['attempt_no', 'passed'],
        self::QUIZ_ANSWER => ['question_id', 'correct', 'chosen'],
        self::SCORM_STATUS => ['lesson_status', 'completion_status'],
        self::XAPI_RESULT => ['verb', 'success', 'completion'],
        self::H5P_RESULT => ['verb'],
        self::MEDIA_REPLAYED => ['media'],
        self::MEDIA_SEEKED_BACK => ['media'],
        self::SESSION_LOGIN => [],
        self::REMEDIATION_SHOWN => ['remediation_id'],
        self::REMEDIATION_FEEDBACK => ['remediation_id', 'helpful'],
        self::NUDGE_SENT => ['nudge_id', 'channel'],
        self::NUDGE_OPENED => ['nudge_id', 'channel'],
        self::TUTOR_QUESTION => ['conversation_id'],
        self::CODE_ATTEMPT_FINISHED => ['attempt_no', 'passed', 'verified'],
    ];

    public const MAX_DATA_BYTES = 2048;

    /** @return string[] */
    public static function all(): array
    {
        return array_keys(self::DATA_KEYS);
    }

    public static function isKnown(string $type): bool
    {
        return isset(self::DATA_KEYS[$type]);
    }

    /**
     * Keeps only the whitelisted keys with scalar values (strings cut to 80 characters); null when
     * the result would still exceed the size cap.
     *
     * @return array<string,scalar|string[]|null>
     */
    public static function cleanData(string $type, array $data): array
    {
        $clean = [];
        foreach (self::DATA_KEYS[$type] ?? [] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($key === 'chosen') {
                // option ids of the course content (ADR 0181): a short list of short ids, nothing else
                $ids = is_array($value) ? array_values(array_filter($value, fn ($v) => is_string($v) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $v))) : [];
                if ($ids !== []) {
                    $clean[$key] = array_slice($ids, 0, 6);
                }
                continue;
            }
            if (is_string($value)) {
                $value = mb_substr($value, 0, 80);
            } elseif (!is_scalar($value) && $value !== null) {
                continue;
            }
            $clean[$key] = $value;
        }

        return strlen((string) json_encode($clean)) > self::MAX_DATA_BYTES ? [] : $clean;
    }
}
