export enum TopicType {
  Unselected = "",
  RichText = "Ulams\\TopicTypes\\Models\\TopicContent\\RichText",
  OEmbed = "Ulams\\TopicTypes\\Models\\TopicContent\\OEmbed",
  Audio = "Ulams\\TopicTypes\\Models\\TopicContent\\Audio",
  Video = "Ulams\\TopicTypes\\Models\\TopicContent\\Video",
  H5P = "Ulams\\TopicTypes\\Models\\TopicContent\\H5P",
  Image = "Ulams\\TopicTypes\\Models\\TopicContent\\Image",
  Pdf = "Ulams\\TopicTypes\\Models\\TopicContent\\PDF",
  Scorm = "Ulams\\TopicTypes\\Models\\TopicContent\\ScormSco",
  Project = "Ulams\\TopicTypeProject\\Models\\Project",
  GiftQuiz = "Ulams\\TopicTypeGift\\Models\\GiftQuiz",
  Lti = "Ulams\\Lti\\Models\\LtiLink",
}

export enum BookmarkableType {
  Course = "Ulams\\Courses\\Models\\Course",
  Lesson = "Ulams\\Courses\\Models\\Lesson",
  Topic = "Ulams\\Courses\\Models\\Topic",
}

export enum PaymentStatusType {
  NEW = "new",
  PAID = "paid",
  CANCELLED = "cancelled",
}

export type IEvent =
  | "http://adlnet.gov/expapi/verbs/experienced"
  | "http://adlnet.gov/expapi/verbs/attended"
  | "http://adlnet.gov/expapi/verbs/attempted"
  | "http://adlnet.gov/expapi/verbs/completed"
  | "http://adlnet.gov/expapi/verbs/passed"
  | "http://adlnet.gov/expapi/verbs/failed"
  | "http://adlnet.gov/expapi/verbs/answered"
  | "http://adlnet.gov/expapi/verbs/interacted"
  | "http://adlnet.gov/expapi/verbs/imported"
  | "http://adlnet.gov/expapi/verbs/created"
  | "http://adlnet.gov/expapi/verbs/shared"
  | "http://adlnet.gov/expapi/verbs/voided"
  | "http://activitystrea.ms/schema/1.0/consume"
  | "http://adlnet.gov/expapi/verbs/mastered";

export enum EventTypes {
  UserLogged = "Ulams\\Auth\\Events\\UserLogged",
  StationaryEventAssigned = "Ulams\\StationaryEvents\\Events\\StationaryEventAssigned",
  StationaryEventUnassigned = "Ulams\\StationaryEvents\\Events\\StationaryEventUnassigned",
  StationaryEventAuthorAssigned = "Ulams\\StationaryEvents\\Events\\StationaryEventAuthorAssigned",
  StationaryEventAuthorUnassigned = "Ulams\\StationaryEvents\\Events\\StationaryEventAuthorUnassigned",
  AbandonedCartEvent = "Ulams\\Cart\\Events\\AbandonedCartEvent",
  OrderCancelled = "Ulams\\Cart\\Events\\OrderCancelled",
  OrderCreated = "Ulams\\Cart\\Events\\OrderCreated",
  OrderPaid = "Ulams\\Cart\\Events\\OrderPaid",
  ProductableAttached = "Ulams\\Cart\\Events\\ProductableAttached",
  ProductableDetached = "Ulams\\Cart\\Events\\ProductableDetached",
  ProductAddedToCart = "Ulams\\Cart\\Events\\ProductAddedToCart",
  ProductAttached = "Ulams\\Cart\\Events\\ProductAttached",
  ProductBought = "Ulams\\Cart\\Events\\ProductBought",
  ProductDetached = "Ulams\\Cart\\Events\\ProductDetached",
  ProductRemovedFromCart = "Ulams\\Cart\\Events\\ProductRemovedFromCart",
  PaymentCancelled = "Ulams\\Payments\\Events\\PaymentCancelled",
  PaymentFailed = "Ulams\\Payments\\Events\\PaymentFailed",
  PaymentRegistered = "Ulams\\Payments\\Events\\PaymentRegistered",
  PaymentSuccess = "Ulams\\Payments\\Events\\PaymentSuccess",
  CourseAccessFinished = "Ulams\\Courses\\Events\\CourseAccessFinished",
  CourseAccessStarted = "Ulams\\Courses\\Events\\CourseAccessStarted",
  CourseAssigned = "Ulams\\Courses\\Events\\CourseAssigned",
  CourseDeadlineSoon = "Ulams\\Courses\\Events\\CourseDeadlineSoon",
  CoursedPublished = "Ulams\\Courses\\Events\\CoursedPublished",
  CourseFinished = "Ulams\\Courses\\Events\\CourseFinished",
  CourseStarted = "Ulams\\Courses\\Events\\CourseStarted",
  CourseStatusChanged = "Ulams\\Courses\\Events\\CourseStatusChanged",
  CourseTutorAssigned = "Ulams\\Courses\\Events\\CourseTutorAssigned",
  CourseTutorUnassigned = "Ulams\\Courses\\Events\\CourseTutorUnassigned",
  CourseUnassigned = "Ulams\\Courses\\Events\\CourseUnassigned",
  TopicFinished = "Ulams\\Courses\\Events\\TopicFinished",
  TopicTypeChanged = "Ulams\\TopicTypes\\Events\\TopicTypeChanged",
  ApprovedTerm = "Ulams\\Consultations\\Events\\ApprovedTerm",
  ApprovedTermWithTrainer = "Ulams\\Consultations\\Events\\ApprovedTermWithTrainer",
  ChangeTerm = "Ulams\\Consultations\\Events\\ChangeTerm",
  RejectTerm = "Ulams\\Consultations\\Events\\RejectTerm",
  RejectTermWithTrainer = "Ulams\\Consultations\\Events\\RejectTermWithTrainer",
  ReminderAboutTerm = "Ulams\\Consultations\\Events\\ReminderAboutTerm",
  ReminderTrainerAboutTerm = "Ulams\\Consultations\\Events\\ReminderTrainerAboutTerm",
  ReportTerm = "Ulams\\Consultations\\Events\\ReportTerm",
  WebinarReminderAboutTerm = "Ulams\\Webinar\\Events\\ReminderAboutTerm",
  WebinarTrainerAssigned = "Ulams\\Webinar\\Events\\WebinarTrainerAssigned",
  WebinarTrainerUnassigned = "Ulams\\Webinar\\Events\\WebinarTrainerUnassigned",
  ProcessVideoFailed = "ProcessVideoFailed",
  ProcessVideoStarted = "ProcessVideoStarted",
  ImportedNewUserTemplateEvent = "Ulams\\CsvUsers\\Events\\UlamsImportedNewUserTemplateEvent",
  AssignToProduct = "AssignToProduct", // ASSIGN WITHOUT ACCONT
  AssignToProductable = "AssignToProductable", // ASSIGN WITHOUT ACCONT
  FileDeleted = "FileDeleted",
  FileStored = "FileStored",
  SettingPackageConfigUpdated = "Ulams\\Settings\\Events\\SettingPackageConfigUpdated",
  AccountBlocked = "Ulams\\Auth\\Events\\AccountBlocked",
  AccountConfirmed = "Ulams\\Auth\\Events\\AccountConfirmed",
  AccountDeleted = "Ulams\\Auth\\Events\\AccountDeleted",
  AccountMustBeEnableByAdmin = "Ulams\\Auth\\Events\\AccountMustBeEnableByAdmin",
  AccountRegistered = "Ulams\\Auth\\Events\\AccountRegistered",
  ForgotPassword = "Ulams\\Auth\\Events\\ForgotPassword",
  Login = "Ulams\\Auth\\Events\\Login",
  Logout = "Ulams\\Auth\\Events\\Logout",
  PasswordChanged = "Ulams\\Auth\\Events\\PasswordChanged",
  ResetPassword = "Ulams\\Auth\\Events\\ResetPassword",
  UserAddedToGroup = "Ulams\\Auth\\Events\\UserAddedToGroup",
  UserRemovedFromGroup = "Ulams\\Auth\\Events\\UserRemovedFromGroup",
  BulkNotification = "Ulams\\BulkNotifications\\Events\\NotificationSent",
  PushNotification = "Ulams\\BulkNotifications\\Channels\\PushNotificationChannel",
  // new and missed event types
  WebinarUserAssigned = "Ulams\\Webinar\\Events\\WebinarUserAssigned",
  UlamsCartOrderSuccessTemplateEvent = "Ulams\\Cart\\Events\\UlamsCartOrderSuccessTemplateEvent",
  UlamsLoginTemplateEvent = "Ulams\\Auth\\Events\\UlamsLoginTemplateEvent",
  UlamsAccountBlockedTemplateEvent = "Ulams\\Auth\\Events\\UlamsAccountBlockedTemplateEvent",
  UlamsCourseFinishedTemplateEvent = "Ulams\\Courses\\Events\\UlamsCourseFinishedTemplateEvent",
  UlamsImportedNewUserTemplateEvent = "Ulams\\CsvUsers\\Events\\UlamsImportedNewUserTemplateEvent",
  TaskUpdatedEvent = "Ulams\\Tasks\\Events\\TaskUpdatedEvent",
  CartOrderPaid = "Ulams\\Cart\\Events\\CartOrderPaid",
  UlamsUserRemovedFromGroupTemplateEvent = "Ulams\\Auth\\Events\\UlamsUserRemovedFromGroupTemplateEvent",
  UlamsUserAddedToGroupTemplateEvent = "Ulams\\Auth\\Events\\UlamsUserAddedToGroupTemplateEvent",
  ProjectSolutionCreatedEvent = "Ulams\\TopicTypeProject\\Events\\ProjectSolutionCreatedEvent",
  QuizAttemptFinishedEvent = "Ulams\\TopicTypeGift\\Events\\QuizAttemptFinishedEvent",
  UlamsTopicTypeChangedTemplateEvent = "Ulams\\TopicTypes\\Events\\UlamsTopicTypeChangedTemplateEvent",
  UlamsCourseUnassignedTemplateEvent = "Ulams\\Courses\\Events\\UlamsCourseUnassignedTemplateEvent",
  UlamsAccountDeletedTemplateEvent = "Ulams\\Auth\\Events\\UlamsAccountDeletedTemplateEvent",
  TaskOverdueEvent = "Ulams\\Tasks\\Events\\TaskOverdueEvent",
  TaskNoteCreatedEvent = "Ulams\\Tasks\\Events\\TaskNoteCreatedEvent",
  TaskAssignedEvent = "Ulams\\Tasks\\Events\\TaskAssignedEvent",
  UlamsCoursedPublishedTemplateEvent = "Ulams\\Courses\\Events\\UlamsCoursedPublishedTemplateEvent",
  UlamsResetPasswordTemplateEvent = "Ulams\\Auth\\Events\\UlamsResetPasswordTemplateEvent",
  UlamsForgotPasswordTemplateEvent = "Ulams\\Auth\\Events\\UlamsForgotPasswordTemplateEvent",
  UlamsCourseAssignedTemplateEvent = "Ulams\\Courses\\Events\\UlamsCourseAssignedTemplateEvent",
  UlamsPaymentRegisteredTemplateEvent = "Ulams\\Payments\\Events\\UlamsPaymentRegisteredTemplateEvent",
  UlamsPermissionRoleChangedTemplateEvent = "Ulams\\Permissions\\Events\\UlamsPermissionRoleChangedTemplateEvent",
  UlamsPermissionRoleRemovedTemplateEvent = "Ulams\\Permissions\\Events\\UlamsPermissionRoleRemovedTemplateEvent",
  UlamsAccountConfirmedTemplateEvent = "Ulams\\Auth\\Events\\UlamsAccountConfirmedTemplateEvent",
  UlamsCartOrderPaidTemplateEvent = "Ulams\\Cart\\Events\\UlamsCartOrderPaidTemplateEvent",
  PdfCreated = "Ulams\\TemplatesPdf\\Events\\PdfCreated",
  LessonFinished = "Ulams\\Courses\\Events\\LessonFinished",
  CourseAccessEnquiryStudentCreatedEvent = "Ulams\\CourseAccess\\Events\\CourseAccessEnquiryStudentCreatedEvent",
}

export enum AttendanceStatus {
  PRESENT = "present",
  PRESENT_NOT_EXERCISING = "present_not_exercising",
  ABSENT = "absent",
  EXCUSED_ABSENCE = "excused_absence",
}

export enum CompetencyChallengeType {
  Simple = "simple",
  Complex = "complex",
}

export enum CourseProgressItemElementStatus {
  INCOMPLETE = 0,
  COMPLETE = 1,
  IN_PROGRESS = 2,
}

export enum CertificateAssignableTypes {
  Course = "Ulams\\Courses\\Models\\Course",
}

export enum QuestionType {
  MULTIPLE_CHOICE = "multiple_choice",
  MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS = "multiple_choice_with_multiple_right_answers",
  TRUE_FALSE = "true_false",
  SHORT_ANSWERS = "short_answers",
  MATCHING = "matching",
  NUMERICAL_QUESTION = "numerical_question",
  ESSAY = "essay",
  DESCRIPTION = "description",
}

export type TaskRelatedType =
  | "Ulams\\Courses\\Course"
  | "Ulams\\Courses\\Topic"
  | "Ulams\\Courses\\Lesson";

export type IEventException =
  | "GuessTheAnswer"
  | "Questionnaire"
  | "QuestionSet";
