export default {
  // AUTH
  'Ulams\\Auth\\Events\\AccountBlocked': 'Compte bloqué',
  'Ulams\\Auth\\Events\\AccountConfirmed': 'Compte confirmé',
  'Ulams\\Auth\\Events\\AccountDeleted': 'Compte supprimé',
  'Ulams\\Auth\\Events\\AccountMustBeEnableByAdmin':
    "Le compte doit être activé par l'administrateur",
  'Ulams\\Auth\\Events\\AccountRegistered': 'Compte enregistré',
  'Ulams\\Auth\\Events\\ForgotPassword': 'Mot de passe oublié',
  'Ulams\\Auth\\Events\\Login': 'Connexion',
  'Ulams\\Auth\\Events\\Logout': 'Déconnexion',
  'Ulams\\Auth\\Events\\PasswordChanged': 'Mot de passe modifié',
  'Ulams\\Auth\\Events\\ResetPassword': 'Réinitialiser le mot de passe',
  'Ulams\\Auth\\Events\\UserAddedToGroup': 'Utilisateur ajouté au groupe',
  'Ulams\\Auth\\Events\\UserRemovedFromGroup': 'Utilisateur supprimé du groupe',
  // SETTINGS
  'Ulams\\Settings\\Events\\SettingPackageConfigUpdated':
    'Configuration du package de configuration mise à jour',
  // CSV USER
  'Ulams\\CsvUsers\\Events\\UlamsImportedNewUserTemplateEvent':
    "Importation d'un nouvel événement de modèle d'utilisateur",
  // TOPIC
  'Ulams\\TopicTypes\\Events\\TopicTypeChanged': 'Type de sujet modifié',
  // CONSULTATIONS
  'Ulams\\Consultations\\Events\\ApprovedTerm': 'Durée approuvée de la consultation',
  'Ulams\\Consultations\\Events\\ApprovedTermWithTrainer':
    'Consultation terme approuvé avec le formateur',
  'Ulams\\Consultations\\Events\\ChangeTerm': 'Consultation changement terme',
  'Ulams\\Consultations\\Events\\RejectTerm': 'Condition de rejet de la consultation',
  'Ulams\\Consultations\\Events\\RejectTermWithTrainer':
    'La consultation a rejeté le terme avec le formateur',
  'Ulams\\Consultations\\Events\\ReminderAboutTerm': 'Rappel de consultation sur le terme',
  'Ulams\\Consultations\\Events\\ReminderTrainerAboutTerm':
    'Consultation rappel formateur sur terme',
  'Ulams\\Consultations\\Events\\ReportTerm': 'Durée du rapport de consultation',
  // WEBINAR
  'Ulams\\Webinar\\Events\\ReminderAboutTerm': 'Rappel du webinaire sur le terme',
  'Ulams\\Webinar\\Events\\WebinarTrainerAssigned': 'Formateur Webinart affecté',
  'Ulams\\Webinar\\Events\\WebinarTrainerUnassigned': 'Formateur de webinaire non attribué',
  // PAYMENT
  'Ulams\\Payments\\Events\\PaymentCancelled': 'Paiement annulé',
  'Ulams\\Payments\\Events\\PaymentFailed': 'Paiement échoué',
  'Ulams\\Payments\\Events\\PaymentRegistered': 'Paiement enregistré',
  'Ulams\\Payments\\Events\\PaymentSuccess': 'Paiement réussi',
  // COURSE
  'Ulams\\Courses\\Events\\CourseAccessFinished': 'Accès au cours terminé',
  'Ulams\\Courses\\Events\\CourseAccessStarted': "L'accès au cours a commencé",
  'Ulams\\Courses\\Events\\CourseAssigned': 'Cours attribué',
  'Ulams\\Courses\\Events\\CourseDeadlineSoon': 'Date limite du cours bientôt',
  'Ulams\\Courses\\Events\\CoursedPublished': 'Cours publié',
  'Ulams\\Courses\\Events\\CourseFinished': 'Cours terminé',
  'Ulams\\Courses\\Events\\CourseStarted': 'Cours commencé',
  'Ulams\\Courses\\Events\\CourseStatusChanged': 'Le statut du cours a changé',
  'Ulams\\Courses\\Events\\CourseTutorAssigned': 'Tuteur de cours assigné',
  'Ulams\\Courses\\Events\\CourseTutorUnassigned': 'Tuteur de cours non affecté',
  'Ulams\\Courses\\Events\\CourseUnassigned': 'Cours non attribué',
  'Ulams\\Courses\\Events\\TopicFinished': 'Sujet terminé',
  // STATIONARY EVENT
  'Ulams\\StationaryEvents\\Events\\StationaryEventAssigned': 'Evénement stationnaire affecté',
  'Ulams\\StationaryEvents\\Events\\StationaryEventUnassigned':
    'Événement stationnaire non affecté',
  'Ulams\\StationaryEvents\\Events\\StationaryEventAuthorAssigned':
    "Auteur d'événement stationnaire attribué",
  'Ulams\\StationaryEvents\\Events\\StationaryEventAuthorUnassigned':
    "Auteur d'événement stationnaire non attribué",
  // CART
  'Ulams\\Cart\\Events\\AbandonedCartEvent': 'Événement de panier abandonné',
  'Ulams\\Cart\\Events\\OrderCancelled': 'Commande de panier annulée',
  'Ulams\\Cart\\Events\\OrderCreated': 'Commande de panier créée',
  'Ulams\\Cart\\Events\\OrderPaid': 'Commande du panier payée',
  'Ulams\\Cart\\Events\\ProductableAttached': 'Produit produit attaché au panier',
  'Ulams\\Cart\\Events\\ProductableDetached': 'Panier productible détaché',
  'Ulams\\Cart\\Events\\ProductAddedToCart': 'Produit ajouté au panier',
  'Ulams\\Cart\\Events\\ProductAttached': 'Produit du panier joint',
  'Ulams\\Cart\\Events\\ProductBought': 'Produit du panier acheté',
  'Ulams\\Cart\\Events\\ProductDetached': 'Produit du panier détaché',
  'Ulams\\Cart\\Events\\ProductRemovedFromCart': 'Produit supprimé du panier',
  // TEMPLATES
  'Ulams\\Templates\\Events\\ManuallyTriggeredEvent':
    "Modèle d'événement déclenché manuellement",
  // ASSIGN WITHOUT ACCOUNT
  'Ulams\\AssignWithoutAccount\\Events\\AssignToProductable':
    'Assign to productable without account',
  'Ulams\\AssignWithoutAccount\\Events\\AssignToProduct': 'Attribuer au produit sans compte',
  // Youtube
  'Ulams\\Youtube\\Events\\YtProblem': 'Erreur YouTube',
  // COURSE ACCESS
  'Ulams\\CourseAccess\\Events\\CourseAccessEnquiryAdminCreatedEvent':
    "Événement créé par l'administrateur de la demande d'accès au cours",
  // TASKS
  'Ulams\\Tasks\\Events\\TaskAssignedEvent': 'Événement affecté à la tâche',
  'Ulams\\Tasks\\Events\\TaskCompleteUserConfirmationEvent':
    "Événement de confirmation de l'utilisateur de la tâche terminée",
  'Ulams\\Tasks\\Events\\TaskCompleteRequestEvent': 'Événement de demande de tâche terminée',
  'Ulams\\Tasks\\Events\\TaskOverdueEvent': 'Événement de tâche en retard',
  'Ulams\\Tasks\\Events\\TaskIncompleteEvent': 'Événement de tâche incomplète',
  'Ulams\\Tasks\\Events\\TaskNoteCreatedEvent': 'Événement de création de note de tâche',
  // CONSULTATION ACCESS
  'Ulams\\ConsultationAccess\\Events\\ConsultationAccessEnquiryAdminCreatedEvent':
    "Événement créé par l'administrateur de la demande d'accès à la consultation",
  'Ulams\\ConsultationAccess\\Events\\ConsultationAccessEnquiryDisapprovedEvent':
    "Événement de demande d'accès à la consultation refusé",
  'Ulams\\ConsultationAccess\\Events\\ConsultationAccessEnquiryApprovedEvent':
    "Événement approuvé de demande d'accès à la consultation",
  // TOPIC TYPE PROJECT
  'Ulams\\TopicTypeProject\\Events\\ProjectSolutionCreatedEvent':
    'Événement créé par la solution de projet',
};
