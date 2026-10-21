<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The class names that polymorphic columns stored before the morph map (Ulams\Core\Support\MorphMap),
 * and the alias each one is stored as now. The list is frozen on purpose: it describes the data that exists,
 * so renaming a class later does not change it, and the migration that uses it stays correct.
 */
class LegacyMorphClassNames
{
    /** @var array<string, list<string>> alias => class names that were stored for it (the first one is what `down` restores) */
    public const CLASSES = [
        'user' => ['Ulams\\Core\\Models\\User', 'App\\Models\\User', 'Ulams\\Auth\\Models\\User', 'Ulams\\Notifications\\Models\\User', 'Ulams\\Cart\\Models\\User', 'Ulams\\Webinar\\Models\\User', 'Ulams\\Consultations\\Models\\User', 'Ulams\\StationaryEvents\\Models\\User', 'Ulams\\Dictionaries\\Models\\User'],
        'course' => ['Ulams\\Courses\\Models\\Course', 'App\\Models\\Course'],
        'lesson' => ['Ulams\\Courses\\Models\\Lesson'],
        'topic' => ['Ulams\\Courses\\Models\\Topic'],
        'topic.rich_text' => ['Ulams\\TopicTypes\\Models\\TopicContent\\RichText', 'Ulams\\Courses\\Models\\TopicContent\\RichText'],
        'topic.video' => ['Ulams\\TopicTypes\\Models\\TopicContent\\Video', 'Ulams\\Courses\\Models\\TopicContent\\Video', 'Ulams\\Video\\Models\\Video'],
        'topic.audio' => ['Ulams\\TopicTypes\\Models\\TopicContent\\Audio', 'Ulams\\Courses\\Models\\TopicContent\\Audio'],
        'topic.image' => ['Ulams\\TopicTypes\\Models\\TopicContent\\Image', 'Ulams\\Courses\\Models\\TopicContent\\Image'],
        'topic.pdf' => ['Ulams\\TopicTypes\\Models\\TopicContent\\PDF', 'Ulams\\Courses\\Models\\TopicContent\\PDF'],
        'topic.h5p' => ['Ulams\\TopicTypes\\Models\\TopicContent\\H5P', 'Ulams\\Courses\\Models\\TopicContent\\H5P'],
        'topic.oembed' => ['Ulams\\TopicTypes\\Models\\TopicContent\\OEmbed', 'Ulams\\Courses\\Models\\TopicContent\\OEmbed'],
        'topic.scorm' => ['Ulams\\TopicTypes\\Models\\TopicContent\\ScormSco', 'Ulams\\Courses\\Models\\TopicContent\\ScormSco'],
        'topic.cmi5' => ['Ulams\\TopicTypes\\Models\\TopicContent\\Cmi5Au'],
        'topic.gift_quiz' => ['Ulams\\TopicTypeGift\\Models\\GiftQuiz'],
        'topic.project' => ['Ulams\\TopicTypeProject\\Models\\Project'],
        'topic.liascript' => ['Ulams\\LiaScript\\Models\\LiaScriptTopic'],
        'topic.layout' => ['Ulams\\TopicTypeLayout\\Models\\LayoutTopic'],
        'topic.lti' => ['Ulams\\Lti\\Models\\LtiLink'],
        'topic.interactive' => ['Ulams\\Interactive\\Models\\InteractiveTopic'],
        'topic.code_exercise' => ['Ulams\\CodeExercises\\Models\\CodeExerciseTopic'],
        'webinar' => ['Ulams\\Webinar\\Models\\Webinar', 'App\\Models\\Webinar'],
        'consultation' => ['Ulams\\Consultations\\Models\\Consultation', 'App\\Models\\Consultation'],
        'stationary_event' => ['Ulams\\StationaryEvents\\Models\\StationaryEvent', 'App\\Models\\StationaryEvent'],
        'dictionary' => ['Ulams\\Dictionaries\\Models\\Dictionary', 'App\\Models\\Dictionary'],
        'product' => ['Ulams\\Cart\\Models\\Product', 'Ulams\\Vouchers\\Models\\Product'],
        'order' => ['Ulams\\Cart\\Models\\Order', 'Ulams\\Vouchers\\Models\\Order'],
        'project_solution' => ['Ulams\\TopicTypeProject\\Models\\ProjectSolution'],
        'task' => ['Ulams\\Tasks\\Models\\Task'],
        'task_note' => ['Ulams\\Tasks\\Models\\TaskNote'],
        'consultation_user' => ['Ulams\\Consultations\\Models\\ConsultationUserPivot'],
        'course_access_enquiry' => ['Ulams\\CourseAccess\\Models\\CourseAccessEnquiry'],
        'consultation_access_enquiry' => ['Ulams\\ConsultationAccess\\Models\\ConsultationAccessEnquiry'],
        'order_item' => ['Ulams\\Cart\\Models\\OrderItem', 'Ulams\\Vouchers\\Models\\OrderItem'],
    ];

    /** Tables whose values are serialized or transient; rewriting them would corrupt string lengths. */
    private const SKIP_TABLES = ['migrations', 'jobs', 'failed_jobs', 'job_batches', 'sessions', 'cache', 'cache_locks'];

    /**
     * Rewrites every polymorphic type column (a `<name>_type` column next to a `<name>_id` column) from the
     * class names to the aliases, or back. Only values that are exactly a known class name (or alias) change.
     *
     * @return array<string, int> "table.column" => rows changed
     */
    public static function rewrite(bool $toAliases): array
    {
        $driver = DB::connection()->getDriverName();
        $changed = [];

        foreach (self::typeColumns($driver) as [$table, $column, $max]) {
            $count = 0;
            foreach (self::CLASSES as $alias => $classes) {
                $from = $toAliases ? $classes : [$alias];
                $to = $toAliases ? $alias : $classes[0];
                if ($max !== null && strlen($to) > $max) {
                    continue; // the value cannot be stored in this column, so it is not polymorphic type data
                }
                $count += DB::table($table)->whereIn($column, $from)->update([$column => $to]);
            }
            if ($count > 0) {
                $changed["{$table}.{$column}"] = $count;
            }
        }

        return $changed;
    }

    /** @return list<array{0: string, 1: string, 2: int|null}> table, column and the longest value the column holds */
    public static function typeColumns(string $driver): array
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            return []; // sqlite test databases are created fresh and never hold class names
        }
        $schema = $driver === 'pgsql' ? 'current_schema()' : 'DATABASE()';
        $rows = DB::select(
            "SELECT c.table_name AS tbl, c.column_name AS col, c.character_maximum_length AS max_length
               FROM information_schema.columns c
               JOIN information_schema.tables t
                 ON t.table_schema = c.table_schema AND t.table_name = c.table_name
              WHERE c.table_schema = {$schema}
                AND t.table_type = 'BASE TABLE'
                AND c.data_type IN ('character varying', 'varchar', 'text', 'character', 'char')
                AND c.column_name LIKE '%\\_type'"
        );

        $columns = [];
        foreach ($rows as $row) {
            $idColumn = substr($row->col, 0, -5) . '_id';
            if (!in_array($row->tbl, self::SKIP_TABLES, true) && Schema::hasColumn($row->tbl, $idColumn)) {
                $columns[] = [$row->tbl, $row->col, $row->max_length === null ? null : (int) $row->max_length];
            }
        }

        return $columns;
    }
}
