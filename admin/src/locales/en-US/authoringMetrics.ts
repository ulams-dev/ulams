export default {
  'menu.Analytics.authoring': 'Authoring',
  authoring_metrics_title: 'Authoring metrics',
  authoring_metrics_intro:
    'How long each course built with the Course Builder took to produce, what the model calls cost, and how much of what the AI wrote was kept as it was.',
  authoring_metrics_own_figures:
    "These are this academy's own measurements. They are not compared with another academy or with any industry figure.",
  authoring_metrics_filters: 'Filter and export',
  authoring_metrics_from: 'Courses created from',
  authoring_metrics_to: 'Courses created until',
  authoring_metrics_export_csv: 'Download CSV',
  authoring_metrics_export_jsonl: 'Download JSON lines',
  authoring_metrics_downloaded: 'Downloaded {name}',
  authoring_metrics_export_failed: 'The file could not be downloaded.',
  authoring_metrics_loading: 'Loading the figures…',
  authoring_metrics_forbidden:
    'You need the permission to read authoring metrics. Authors can see the figures of their own courses in the studio.',
  authoring_metrics_error: 'The figures could not be loaded.',
  authoring_metrics_retry: 'Try again',
  authoring_metrics_empty: 'No course built with the Course Builder has figures in this period yet.',
  authoring_metrics_scroll: 'Authoring metrics by course (scrolls sideways on small screens)',
  authoring_metrics_caption: 'Authoring metrics, one row per course',
  authoring_metrics_course_fallback: 'Course {id}',
  authoring_metrics_col_course: 'Course',
  authoring_metrics_col_generation: 'Generation and AI edit time',
  authoring_metrics_col_interview: 'Interview time',
  authoring_metrics_col_author: 'Author time',
  authoring_metrics_col_build: 'Build cost',
  authoring_metrics_col_maintenance: 'Maintenance cost',
  authoring_metrics_col_content: 'Content',
  authoring_metrics_col_hours: 'Work per learner hour',
  authoring_metrics_col_elements: 'What the AI wrote',
  authoring_metrics_hours_value: '{n} h',
  authoring_metrics_hours_formula:
    'Work per learner hour = (generation time + interview time + author time) / learner time, where learner time is the minutes declared for the lessons. It is shown only when author time is recorded for the course. Maintenance is shown apart, as a cost.',
  authoring_metrics_summary_title: 'Across the academy',
  authoring_metrics_summary_median:
    'Median: {n} h of work per hour of learner time, over {courses} courses with recorded author time.',
  authoring_metrics_summary_not_enough:
    'Not enough courses yet: {courses} with recorded author time, at least {min} are needed for a median.',
  authoring_metrics_summary_no_author_time:
    'No figure yet: author time is not recorded for any course. Turn on review time in Settings to record it.',
  authoring_metrics_summary_own:
    "Your own earlier figure, entered in Settings: {n} h of work per hour of learner time (your own estimate, not a measurement).",
  authoring_metrics_summary_maintenance:
    'Maintenance, shown apart: {cost} of model calls on {courses} courses.',
  authoring_metrics_not_recorded: 'Not recorded',
  authoring_metrics_content: 'Lessons: {lessons}, questions: {questions}',
  authoring_metrics_no_ai_elements: 'Nothing yet',
  authoring_metrics_split:
    'Kept: {kept} ({share}), revised on request: {revised}, edited: {edited}, unknown: {unknown}',
  authoring_metrics_note:
    'Times are wall-clock. Cost includes failed calls and leaves out tutoring and personal help for learners. Author time is only recorded when review time is turned on.',
  authoring_metrics_review_title: 'Review time',
  authoring_metrics_review_intro:
    'When review time is on, the academy adds up the minutes people actively spend on a course in the studio and in the course editor, so it can say how long a course took to produce. Tell your authors before you turn it on: measuring work can need a legal basis and, in some countries, the involvement of a works council. This is not legal advice.',
  authoring_metrics_review_measured:
    'Measured: whole minutes of active screen time on one course. A minute counts when the tab is visible and in front and there was keyboard, pointer or scroll input in the last two minutes.',
  authoring_metrics_review_stored:
    'Stored: the course, the start of the minute, the place (studio or admin) and a random code made when the page was opened. Not stored: a name or account, an address, a browser or device, or which lesson, block or question was open.',
  authoring_metrics_review_shown:
    'Shown: author-minutes per course, per day and in total, never per person. A course with one author shows that author\'s time.',
  authoring_metrics_review_kept:
    'Kept: the minutes are totalled per course and day every night and the single minutes are deleted after 7 days; the daily totals stay as long as the course.',
  authoring_metrics_review_switch:
    'Turn it on or off with the setting "review_time" below. Turning it off stops collection at once.',
  authoring_metrics_review_delete: 'Delete recorded review time',
  authoring_metrics_review_delete_confirm:
    'This deletes every recorded review minute and daily total of this academy. Generation time, cost and counts are kept. It cannot be undone.',
  authoring_metrics_review_delete_yes: 'Yes, delete it',
  authoring_metrics_review_delete_cancel: 'Cancel',
  authoring_metrics_review_deleted: 'Deleted {minutes} minutes and {days} daily totals.',
  authoring_metrics_review_delete_failed: 'Review time could not be deleted.',
  authoring_metrics_duration_seconds: '{s} s',
  authoring_metrics_duration_minutes: '{m} min {s} s',
  authoring_metrics_duration_hours: '{h} h {m} min',
};
