<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" backupGlobals="false" backupStaticAttributes="false" bootstrap="vendor/autoload.php" colors="true" convertErrorsToExceptions="true" convertNoticesToExceptions="true" convertWarningsToExceptions="true" processIsolation="false" stopOnFailure="false" xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd">
  <coverage processUncoveredFiles="true">
    <include>
      <directory suffix=".php">./packages/auth/src</directory>
      <directory suffix=".php">./packages/bookmarks_notes/src</directory>
      <directory suffix=".php">./packages/bulk-notifications/src</directory>
      <directory suffix=".php">./packages/cart/src</directory>
      <directory suffix=".php">./packages/categories/src</directory>
      <directory suffix=".php">./packages/core/src</directory>
      <directory suffix=".php">./packages/courses/src</directory>
      <directory suffix=".php">./packages/course-access/src</directory>
      <directory suffix=".php">./packages/courses-import-export/src</directory>
      <directory suffix=".php">./packages/csv-users/src</directory>
      <directory suffix=".php">./packages/dictionaries/src</directory>
      <directory suffix=".php">./packages/files/src</directory>
      <directory suffix=".php">./packages/h5p/src</directory>
      <directory suffix=".php">./packages/images/src</directory>
      <directory suffix=".php">./packages/invoices/src</directory>
      <directory suffix=".php">./packages/lrs/src</directory>
      <directory suffix=".php">./packages/notifications/src</directory>
      <directory suffix=".php">./packages/mailerlite/src</directory>
      <directory suffix=".php">./packages/mattermost/src</directory>
      <directory suffix=".php">./packages/model-fields/src</directory>
      <directory suffix=".php">./packages/pages/src</directory>
      <directory suffix=".php">./packages/payments/src</directory>
      <directory suffix=".php">./packages/permissions/src</directory>
      <directory suffix=".php">./packages/recommender/src</directory>
      <directory suffix=".php">./packages/reports/src</directory>
      <directory suffix=".php">./packages/scorm/src</directory>
      <directory suffix=".php">./packages/settings/src</directory>
      <directory suffix=".php">./packages/stationary-events/src</directory>
      <directory suffix=".php">./packages/tags/src</directory>
      <directory suffix=".php">./packages/tasks/src</directory>
      <directory suffix=".php">./packages/topic-types/src</directory>
      <directory suffix=".php">./packages/topic-type-gift/src</directory>
      <directory suffix=".php">./packages/topic-type-project/src</directory>
      <directory suffix=".php">./packages/templates/src</directory>
      <directory suffix=".php">./packages/templates-email/src</directory>
      <directory suffix=".php">./packages/templates-sms/src</directory>
      <directory suffix=".php">./packages/templates-pdf/src</directory>
      <directory suffix=".php">./packages/questionnaire/src</directory>
      <directory suffix=".php">./packages/assign-without-account/src</directory>
      <!-- <directory suffix=".php">./packages/tracker/src</directory> -->
      <directory suffix=".php">./packages/translations/src</directory>
      <directory suffix=".php">./packages/vouchers/src</directory>
      <directory suffix=".php">./packages/consultations/src</directory>
      <directory suffix=".php">./packages/consultation-access/src</directory>
      <directory suffix=".php">./packages/webinar/src</directory>
      <directory suffix=".php">./packages/cmi5/src</directory>
      <!-- <directory suffix=".php">./packages/video/src</directory> -->

    </include>
  </coverage>
  <testsuites>
    <testsuite name="Integrations">
      <directory suffix="Test.php">./tests/Integrations</directory>
    </testsuite>
    <testsuite name="auth">
      <directory suffix="Test.php">./packages/auth/tests</directory>
    </testsuite>
    <testsuite name="bookmarks_notes">
      <directory suffix="Test.php">./packages/bookmarks_notes/tests</directory>
    </testsuite>
    <testsuite name="bulk-notifications">
      <directory suffix="Test.php">./packages/bulk-notifications/tests</directory>
    </testsuite>
    <testsuite name="cart">
      <directory suffix="Test.php">./packages/cart/tests</directory>
    </testsuite>
    <testsuite name="categories">
      <directory suffix="Test.php">./packages/categories/tests</directory>
    </testsuite>
    <testsuite name="core">
      <directory suffix="Test.php">./packages/core/tests</directory>
    </testsuite>
    <testsuite name="courses">
      <directory suffix="Test.php">./packages/courses/tests</directory>
    </testsuite>
    <testsuite name="course-access">
      <directory suffix="Test.php">./packages/course-access/tests</directory>
    </testsuite>
    <testsuite name="courses-import-export">
      <directory suffix="Test.php">./packages/courses-import-export/tests</directory>
    </testsuite>
    <testsuite name="csv-users">
      <directory suffix="Test.php">./packages/csv-users/tests</directory>
    </testsuite>
    <testsuite name="dictionaries">
      <directory suffix="Test.php">./packages/dictionaries/tests</directory>
    </testsuite>
    <testsuite name="files">
      <directory suffix="Test.php">./packages/files/tests</directory>
    </testsuite>
    <testsuite name="h5p">
      <directory suffix="Test.php">./packages/h5p/tests</directory>
    </testsuite>
    <testsuite name="images">
      <directory suffix="Test.php">./packages/images/tests</directory>
    </testsuite>
    <testsuite name="invoices">
      <directory suffix="Test.php">./packages/invoices/tests</directory>
    </testsuite>
    <testsuite name="lrs">
      <directory suffix="Test.php">./packages/lrs/tests</directory>
    </testsuite>
    <testsuite name="notifications">
      <directory suffix="Test.php">./packages/notifications/tests</directory>
    </testsuite>
    <testsuite name="mailerlite">
      <directory suffix="Test.php">./packages/mailerlite/tests</directory>
    </testsuite>
    <testsuite name="mattermost">
      <directory suffix="Test.php">./packages/mattermost/tests</directory>
    </testsuite>
    <testsuite name="model-fields">
      <directory suffix="Test.php">./packages/model-fields/tests</directory>
    </testsuite>
    <testsuite name="pages">
      <directory suffix="Test.php">./packages/pages/tests</directory>
    </testsuite>
    <testsuite name="payments">
      <directory suffix="Test.php">./packages/payments/tests</directory>
    </testsuite>
    <testsuite name="permissions">
      <directory suffix="Test.php">./packages/permissions/tests</directory>
    </testsuite>
    <testsuite name="recommender">
      <directory suffix="Test.php">./packages/recommender/tests</directory>
    </testsuite>
    <testsuite name="reports">
      <directory suffix="Test.php">./packages/reports/tests</directory>
    </testsuite>
    <testsuite name="scorm">
      <directory suffix="Test.php">./packages/scorm/tests</directory>
    </testsuite>
     <testsuite name="settings">
      <directory suffix="Test.php">./packages/settings/tests</directory>
    </testsuite>
    <testsuite name="stationary-events">
      <directory suffix="Test.php">./packages/stationary-events/tests</directory>
    </testsuite>
    <testsuite name="tags">
      <directory suffix="Test.php">./packages/tags/tests</directory>
    </testsuite>
    <testsuite name="tasks">
      <directory suffix="Test.php">./packages/tasks/tests</directory>
    </testsuite>
    <testsuite name="topic-types">
      <directory suffix="Test.php">./packages/topic-types/tests</directory>
    </testsuite>
    <testsuite name="topic-type-gift">
      <directory suffix="Test.php">./packages/topic-type-gift/tests</directory>
    </testsuite>
    <testsuite name="topic-type-project">
      <directory suffix="Test.php">./packages/topic-type-project/tests</directory>
    </testsuite>
    <testsuite name="templates">
      <directory suffix="Test.php">./packages/templates/tests</directory>
    </testsuite>
    <testsuite name="templates-email">
      <directory suffix="Test.php">./packages/templates-email/tests</directory>
    </testsuite>
    <testsuite name="templates-sms">
      <directory suffix="Test.php">./packages/templates-sms</directory>
    </testsuite>
    <testsuite name="templates-pdf">
      <directory suffix="Test.php">./packages/templates-pdf/tests</directory>
    </testsuite>
    <testsuite name="questionnaire">
      <directory suffix="Test.php">./packages/questionnaire/tests</directory>
    </testsuite>
    <testsuite name="assign-without-account">
      <directory suffix="Test.php">./packages/assign-without-account/tests</directory>
    </testsuite>
    <!--
    <testsuite name="tracker">
      <directory suffix="Test.php">./packages/tracker/tests</directory>
    </testsuite>
    -->
    <testsuite name="translations">
      <directory suffix="Test.php">./packages/translations/tests</directory>
    </testsuite>
    <testsuite name="vouchers">
      <directory suffix="Test.php">./packages/vouchers/tests</directory>
    </testsuite>
    <testsuite name="consultations">
      <directory suffix="Test.php">./packages/consultations/tests</directory>
    </testsuite>
    <testsuite name="consultation-access">
      <directory suffix="Test.php">./packages/consultation-access/tests</directory>
    </testsuite>
    <testsuite name="webinar">
      <directory suffix="Test.php">./packages/webinar/tests</directory>
    </testsuite>
    <testsuite name="cmi5">
      <directory suffix="Test.php">./packages/cmi5/tests</directory>
    </testsuite>
    <!--
    <testsuite name="video">
      <directory suffix="Test.php">./packages/video/tests</directory>
    </testsuite>
    -->
  </testsuites>
    <php>
    <env name="APP_KEY" value="AckfSECXIvnK5r28GVIWUAxmbBSjTsmF"/>
    <env name="DB_CONNECTION" value="mysql"/>
    <env name="DB_HOST" value="mysql"/>
    <env name="DB_PORT" value="3306"/>
    <env name="DB_DATABASE" value="test"/>
    <env name="DB_USERNAME" value="root"/>
    <env name="DB_PASSWORD" value="password"/>
    <env name="CONFIG_USE_DATABASE" value="true"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
    <ini name="memory_limit" value="1024M"/>
  </php>
</phpunit>
