export default {
  'menu.Analytics.authoring': '课程制作',
  authoring_metrics_title: '课程制作指标',
  authoring_metrics_intro:
    '用课程构建器制作的每门课程花了多长时间，模型调用花了多少钱，以及 AI 写的内容有多少被原样保留。',
  authoring_metrics_own_figures: '这些是本学院自己的测量数据，不与其他学院或任何行业数据比较。',
  authoring_metrics_filters: '筛选与导出',
  authoring_metrics_from: '课程创建起始日期',
  authoring_metrics_to: '课程创建截止日期',
  authoring_metrics_export_csv: '下载 CSV',
  authoring_metrics_export_jsonl: '下载 JSON lines',
  authoring_metrics_downloaded: '已下载 {name}',
  authoring_metrics_export_failed: '无法下载文件。',
  authoring_metrics_loading: '正在加载数据…',
  authoring_metrics_forbidden:
    '你需要读取课程制作指标的权限。作者可以在工作室中查看自己课程的数据。',
  authoring_metrics_error: '无法加载数据。',
  authoring_metrics_retry: '重试',
  authoring_metrics_empty: '此期间内还没有用课程构建器制作且有数据的课程。',
  authoring_metrics_scroll: '各课程的制作指标（小屏幕上可横向滚动）',
  authoring_metrics_caption: '课程制作指标，每门课程一行',
  authoring_metrics_course_fallback: '课程 {id}',
  authoring_metrics_col_course: '课程',
  authoring_metrics_col_generation: '生成与 AI 修改时间',
  authoring_metrics_col_interview: '访谈时间',
  authoring_metrics_col_author: '作者时间',
  authoring_metrics_col_build: '构建成本',
  authoring_metrics_col_maintenance: '维护成本',
  authoring_metrics_col_content: '内容',
  authoring_metrics_col_hours: '每学员学时的工作量',
  authoring_metrics_col_elements: 'AI 写了什么',
  authoring_metrics_hours_value: '{n} 小时',
  authoring_metrics_hours_formula:
    '每学员学时的工作量 =（生成时间 + 访谈时间 + 作者时间）/ 学员时间，其中学员时间为课时声明的分钟数。只有为该课程记录了作者时间时才会显示。维护成本单独作为成本显示。',
  authoring_metrics_summary_title: '全学院',
  authoring_metrics_summary_median:
    '中位数：每 1 小时学员学习时间需 {n} 小时工作，基于 {courses} 门记录了作者时间的课程。',
  authoring_metrics_summary_not_enough:
    '课程数量不足：只有 {courses} 门记录了作者时间，至少需要 {min} 门才能计算中位数。',
  authoring_metrics_summary_no_author_time:
    '暂无数字：没有任何课程记录了作者时间。请在设置中开启审阅时间以记录。',
  authoring_metrics_summary_own:
    '你在设置中填写的自己此前的数字：每 1 小时学员学习时间需 {n} 小时工作（你自己的估计，不是测量值）。',
  authoring_metrics_summary_maintenance: '维护成本单独显示：{courses} 门课程共 {cost} 的模型调用。',
  authoring_metrics_not_recorded: '未记录',
  authoring_metrics_content: '课时：{lessons}，题目：{questions}',
  authoring_metrics_no_ai_elements: '暂无',
  authoring_metrics_split:
    '保持原样：{kept}（{share}），应要求修改：{revised}，已编辑：{edited}，未知：{unknown}',
  authoring_metrics_note:
    '耗时为实际经过的时间。成本包含失败的调用，不含学员辅导和个人帮助。只有开启审阅时间记录后才会记录作者时间。',
  authoring_metrics_review_title: '审阅时间',
  authoring_metrics_review_intro:
    '开启审阅时间后，学院会累计人们在工作室和课程编辑器中积极处理某门课程的分钟数，以便说明制作一门课程用了多长时间。开启前请告知你的作者：测量工作时间可能需要法律依据，在某些国家还需要工会或职工委员会的参与。这不构成法律建议。',
  authoring_metrics_review_measured:
    '测量内容：针对一门课程的完整活跃屏幕分钟数。当标签页可见且位于前台，并且最近两分钟内有键盘、指针或滚动操作时，这一分钟才会计入。',
  authoring_metrics_review_stored:
    '存储内容：课程、该分钟的起点、所在位置（工作室或后台）以及打开页面时生成的随机代码。不存储：姓名或账号、地址、浏览器或设备，以及打开了哪个课时、内容块或题目。',
  authoring_metrics_review_shown:
    '显示内容：每门课程、每天和总计的作者分钟数，绝不按个人显示。只有一位作者的课程会显示这位作者的时间。',
  authoring_metrics_review_kept:
    '保存期限：每晚按课程和日期汇总分钟数，单个分钟记录在 7 天后删除；每日合计与课程同时保留。',
  authoring_metrics_review_switch: '通过下方的设置“review_time”开启或关闭。关闭后立即停止收集。',
  authoring_metrics_review_delete: '删除已记录的审阅时间',
  authoring_metrics_review_delete_confirm:
    '这将删除本学院所有已记录的审阅分钟和每日合计。生成时间、成本和数量会保留。此操作无法撤销。',
  authoring_metrics_review_delete_yes: '是，删除',
  authoring_metrics_review_delete_cancel: '取消',
  authoring_metrics_review_deleted: '已删除 {minutes} 分钟记录和 {days} 条每日合计。',
  authoring_metrics_review_delete_failed: '无法删除审阅时间。',
  authoring_metrics_duration_seconds: '{s} 秒',
  authoring_metrics_duration_minutes: '{m} 分 {s} 秒',
  authoring_metrics_duration_hours: '{h} 小时 {m} 分钟',
};
