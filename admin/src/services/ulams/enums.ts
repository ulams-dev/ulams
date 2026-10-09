export enum TopicType {
  Unselected = '',
  RichText = 'Ulams\\TopicTypes\\Models\\TopicContent\\RichText',
  OEmbed = 'Ulams\\TopicTypes\\Models\\TopicContent\\OEmbed',
  Audio = 'Ulams\\TopicTypes\\Models\\TopicContent\\Audio',
  Video = 'Ulams\\TopicTypes\\Models\\TopicContent\\Video',
  H5P = 'Ulams\\TopicTypes\\Models\\TopicContent\\H5P',
  Image = 'Ulams\\TopicTypes\\Models\\TopicContent\\Image',
  PDF = 'Ulams\\TopicTypes\\Models\\TopicContent\\PDF',
  SCORM = 'Ulams\\TopicTypes\\Models\\TopicContent\\ScormSco',
  Project = 'Ulams\\TopicTypeProject\\Models\\Project',
  GiftQuiz = 'Ulams\\TopicTypeGift\\Models\\GiftQuiz',
  LiaScript = 'Ulams\\LiaScript\\Models\\LiaScriptTopic',
  Lti = 'Ulams\\Lti\\Models\\LtiLink',
}

export enum EventTypes {
  OrderPaid = 'Ulams\\Cart\\Events\\OrderPaid',
  UserLogged = 'Ulams\\Auth\\Events\\UserLogged',
}

export enum CourseStatus {
  draft = 'draft',
  published = 'published',
  archived = 'archived',
}

export enum TemplateChannelValue {
  email = 'Ulams\\TemplatesEmail\\Core\\EmailChannel',
  pdf = 'Ulams\\TemplatesPdf\\Core\\PdfChannel',
  sms = 'Ulams\\TemplatesSms\\Core\\SmsChannel',
}

export enum TemplateEvents {
  ManuallyTriggeredEvent = 'Ulams\\Templates\\Events\\ManuallyTriggeredEvent',
}

export enum VouchersTypes {
  cart_fixed = 'cart_fixed',
  cart_percent = 'cart_percent',
  product_fixed = 'product_fixed',
  product_percent = 'product_percent',
}

export type BuyableTypes =
  | 'App\\Models\\Course'
  | 'App\\Models\\Consultation'
  | 'App\\Models\\Webinar'
  | 'App\\Models\\StationaryEvent'
  | 'Ulams\\Courses\\Models\\Course'
  | 'Ulams\\Consultations\\Models\\Consultation'
  | 'Ulams\\Webinars\\Models\\Webinar'
  | 'Ulams\\StationaryEvents\\Models\\StationaryEvent';

export enum QuestionType {
  MULTIPLE_CHOICE = 'multiple_choice',
  MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS = 'multiple_choice_with_multiple_right_answers',
  TRUE_FALSE = 'true_false',
  SHORT_ANSWERS = 'short_answers',
  MATCHING = 'matching',
  NUMERICAL_QUESTION = 'numerical_question',
  ESSAY = 'essay',
  DESCRIPTION = 'description',
}

export enum AttendanceValue {
  PRESENT = 'present',
  PRESENT_NOT_EXERCISING = 'present_not_exercising',
  ABSENT = 'absent',
  EXCUSED_ABSENCE = 'excused_absence',
}

export enum ExamGradeType {
  Manual = 'manual',
  ManualPass = 'manual_pass',
  ManualGrades = 'manual_grades',
  TeamsForms = 'teams_forms',
  TeamsLecture = 'teams_lecture',
  TestPortal = 'test_portal',
}

export enum ExamGradePassType {
  Passed = 'zal',
  Failed = 'nie zal',
}

export enum CompetencyChallengeType {
  Simple = 'simple',
  Complex = 'complex',
}

export enum TopicStatsKey {
  QuizSummary = 'Ulams\\Reports\\Stats\\Topic\\QuizSummaryForTopicTypeGIFT',
}

export enum FieldType {
  Boolean = 'boolean',
  Number = 'number',
  Varchar = 'varchar',
  Text = 'text',
  Json = 'json',
}

export enum QuestionnaireQuestionType {
  Rate = 'rate',
  Text = 'text',
  Review = 'review',
}

export enum BookmarkableType {
  Group = 'Ulams\\PcgIntegration\\Models\\Group',
}

export enum BulkNotificationChannelsEnum {
  PUSH = 'Ulams\\BulkNotifications\\Channels\\PushNotificationChannel',
}

export enum BulkNotificationSectionsKeysEnum {
  TITLE = 'title',
  BODY = 'body',
  IMAGE = 'image_url',
  REDIRECT_URL = 'redirect_url',
}
