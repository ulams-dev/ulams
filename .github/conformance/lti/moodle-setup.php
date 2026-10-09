<?php

/*
 * Moodle side of the LTI round trip (nightly-conformance.yml), run inside the Moodle container:
 *
 *   ULAMS_URL=http://ulams.test:18000 ULAMS_COURSE_ID=1 php /conformance/moodle-setup.php setup
 *       -> JSON with Moodle's issuer, client id, deployment id, OIDC/token/JWKS URLs and the
 *          activity to launch
 *   php /conformance/moodle-setup.php grade <cmid> <username>
 *       -> {"grade": <0-100 or null>}
 *
 * The other direction (ulams launches Moodle, Moodle sends grades back through AGS):
 *   php /conformance/moodle-setup.php tool-draft
 *       -> {"uniqueid", "login_url", "launch_url", "deep_linking_url", "jwks_url", "resource_uuid"}
 *          a course published as an LTI 1.3 tool with grade sync, and a draft registration
 *   php /conformance/moodle-setup.php tool-complete <uniqueid> '<ulams-setup.php tool JSON>'
 *          completes the registration with ulams as the platform and adds its deployment
 *   php /conformance/moodle-setup.php tool-grade <grade>
 *          grades every LTI-launched user of the published course and runs the grade sync task
 *
 * It registers ulams as an LTI 1.3 external tool (manual registration with our JWKS URL),
 * creates a course with an LTI activity that launches the ulams course (custom parameter
 * course_id) and accepts grades, and a student enrolled in it. Test fixture only.
 */

define('CLI_SCRIPT', true);

$root = getenv('MOODLE_DIR') ?: '/opt/bitnami/moodle';
require $root . '/config.php';
require_once $CFG->dirroot . '/mod/lti/locallib.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/course/modlib.php';
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->libdir . '/enrollib.php';
require_once $CFG->libdir . '/gradelib.php';

\core\session\manager::set_user(get_admin());

$command = $argv[1] ?? '';

if ($command === 'setup') {
    $ulams = rtrim((string) getenv('ULAMS_URL'), '/');
    $ulamsCourse = (int) getenv('ULAMS_COURSE_ID');
    if ($ulams === '' || $ulamsCourse <= 0) {
        fwrite(STDERR, "ULAMS_URL and ULAMS_COURSE_ID are required\n");
        exit(2);
    }

    // the conformance network uses private addresses and a non-standard port
    set_config('curlsecurityblockedhosts', '');
    set_config('curlsecurityallowedport', '');

    $type = new stdClass();
    $type->state = LTI_TOOL_STATE_CONFIGURED;
    $type->course = SITEID;
    $type->coursevisible = LTI_COURSEVISIBLE_ACTIVITYCHOOSER;

    $config = new stdClass();
    $config->lti_typename = 'ulams';
    $config->lti_toolurl = $ulams . '/api/lti/tool/launch';
    $config->lti_description = 'ulams courses';
    $config->lti_ltiversion = LTI_VERSION_1P3;
    $config->lti_keytype = LTI_JWK_KEYSET;
    $config->lti_publickeyset = $ulams . '/api/lti/jwks';
    $config->lti_initiatelogin = $ulams . '/api/lti/tool/login';
    $config->lti_redirectionuris = $ulams . '/api/lti/tool/launch';
    $config->lti_coursevisible = LTI_COURSEVISIBLE_ACTIVITYCHOOSER;
    $config->lti_launchcontainer = LTI_LAUNCH_CONTAINER_WINDOW;
    $config->lti_sendname = LTI_SETTING_ALWAYS;
    $config->lti_sendemailaddr = LTI_SETTING_ALWAYS;
    $config->lti_acceptgrades = LTI_SETTING_ALWAYS;
    $config->lti_forcessl = 0;
    // AGS: grade synchronisation and line item management
    $config->ltiservice_gradesynchronization = 2;
    $config->ltiservice_memberships = 0;
    $config->ltiservice_toolsettings = 0;
    $typeid = lti_add_type($type, $config);
    $clientid = $DB->get_field('lti_types', 'clientid', ['id' => $typeid], MUST_EXIST);

    $course = create_course((object) [
        'fullname' => 'LTI conformance',
        'shortname' => 'lticonf-' . time(),
        'category' => $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}'),
    ]);

    $student = $DB->get_record('user', ['username' => 'student']);
    if (!$student) {
        $id = user_create_user((object) [
            'username' => 'student',
            'password' => 'Student-1234!',
            'firstname' => 'Sam',
            'lastname' => 'Student',
            'email' => 'student@example.com',
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ]);
        $student = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    }
    enrol_try_internal_enrol($course->id, $student->id, $DB->get_field('role', 'id', ['shortname' => 'student']));

    $moduleinfo = (object) [
        'modulename' => 'lti',
        'module' => $DB->get_field('modules', 'id', ['name' => 'lti'], MUST_EXIST),
        'course' => $course->id,
        'section' => 0,
        'visible' => 1,
        'name' => 'ulams course',
        'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        'typeid' => $typeid,
        'toolurl' => '',
        'securetoolurl' => '',
        'instructorcustomparameters' => 'course_id=' . $ulamsCourse,
        'launchcontainer' => LTI_LAUNCH_CONTAINER_WINDOW,
        'instructorchoicesendname' => 1,
        'instructorchoicesendemailaddr' => 1,
        'instructorchoiceacceptgrades' => 1,
        'grade' => 100,
        'showtitlelaunch' => 1,
        'showdescriptionlaunch' => 0,
    ];
    $cm = create_module($moduleinfo);

    echo json_encode([
        'issuer' => $CFG->wwwroot,
        'client_id' => $clientid,
        'deployment_id' => (string) $typeid,
        'auth_login_url' => $CFG->wwwroot . '/mod/lti/auth.php',
        'auth_token_url' => $CFG->wwwroot . '/mod/lti/token.php',
        'jwks_url' => $CFG->wwwroot . '/mod/lti/certs.php',
        'course_id' => (int) $course->id,
        'cmid' => (int) $cm->coursemodule,
        'student' => 'student',
    ], JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

if ($command === 'grade') {
    $cmid = (int) ($argv[2] ?? 0);
    $username = (string) ($argv[3] ?? 'student');
    $cm = get_coursemodule_from_id('lti', $cmid, 0, false, MUST_EXIST);
    $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
    $grades = grade_get_grades($cm->course, 'mod', 'lti', $cm->instance, $user->id);
    $grade = null;
    foreach ($grades->items ?? [] as $item) {
        if (isset($item->grades[$user->id]) && $item->grades[$user->id]->grade !== null) {
            $grade = (float) $item->grades[$user->id]->grade;
        }
    }
    // a tool-created line item (AGS) is a separate grade item of the course
    $items = $DB->get_records('grade_items', ['courseid' => $cm->course, 'itemmodule' => 'lti']);
    $all = [];
    foreach ($items as $item) {
        $value = $DB->get_field('grade_grades', 'finalgrade', ['itemid' => $item->id, 'userid' => $user->id]);
        $all[] = ['item' => $item->itemname, 'grade' => $value === false || $value === null ? null : (float) $value, 'max' => (float) $item->grademax];
    }
    echo json_encode(['grade' => $grade, 'items' => $all], JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

if ($command === 'tool-draft') {
    // enable the LTI enrolment and authentication plugins
    $enrol = array_filter(explode(',', (string) get_config('core', 'enrol_plugins_enabled')));
    if (!in_array('lti', $enrol, true)) {
        $enrol[] = 'lti';
        set_config('enrol_plugins_enabled', implode(',', $enrol));
    }
    $auth = array_filter(explode(',', (string) get_config('core', 'auth')));
    if (!in_array('lti', $auth, true)) {
        $auth[] = 'lti';
        set_config('auth', implode(',', $auth));
    }
    set_config('curlsecurityblockedhosts', '');
    set_config('curlsecurityallowedport', '');
    // otherwise enrol/lti/launch.php answers with an "Open tool" link instead of the course
    set_config('allowframembedding', 1);
    core_plugin_manager::reset_caches();

    $course = create_course((object) [
        'fullname' => 'Published to ulams',
        'shortname' => 'ltitool-' . time(),
        'category' => $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}'),
    ]);
    $item = new grade_item(['courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Points', 'grademax' => 100, 'grademin' => 0]);
    $item->insert();

    $plugin = enrol_get_plugin('lti');
    $instanceid = $plugin->add_instance($course, [
        'name' => 'ulams conformance',
        'contextid' => context_course::instance($course->id)->id,
        'ltiversion' => 'LTI-1p3',
        'status' => ENROL_INSTANCE_ENABLED,
        'gradesync' => 1,
        'gradesynccompletion' => 0,
        'membersync' => 0,
        'provisioningmodeinstructor' => 1,
        'provisioningmodelearner' => 1,
        'roleinstructor' => $DB->get_field('role', 'id', ['shortname' => 'editingteacher']),
        'rolelearner' => $DB->get_field('role', 'id', ['shortname' => 'student']),
    ]);
    $tool = $DB->get_record('enrol_lti_tools', ['enrolid' => $instanceid], '*', MUST_EXIST);

    $uniqueid = bin2hex(random_bytes(16));
    $registration = \enrol_lti\local\ltiadvantage\entity\application_registration::create_draft('ulams', $uniqueid);
    (new \enrol_lti\local\ltiadvantage\repository\application_registration_repository())->save($registration);

    echo json_encode([
        'uniqueid' => $uniqueid,
        'login_url' => $CFG->wwwroot . '/enrol/lti/login.php?id=' . $uniqueid,
        'launch_url' => $CFG->wwwroot . '/enrol/lti/launch.php',
        'deep_linking_url' => $CFG->wwwroot . '/enrol/lti/launch_deeplink.php',
        'jwks_url' => $CFG->wwwroot . '/enrol/lti/jwks.php',
        'resource_uuid' => $tool->uuid,
        'course_id' => (int) $course->id,
    ], JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

if ($command === 'tool-complete') {
    $uniqueid = (string) ($argv[2] ?? '');
    $ulams = json_decode((string) ($argv[3] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    $repo = new \enrol_lti\local\ltiadvantage\repository\application_registration_repository();
    $registration = $repo->find_by_uniqueid($uniqueid);
    if ($registration === null) {
        fwrite(STDERR, "unknown registration {$uniqueid}\n");
        exit(2);
    }
    $registration->set_platformid(new moodle_url($ulams['platform']['issuer']));
    $registration->set_clientid($ulams['client_id']);
    $registration->set_authenticationrequesturl(new moodle_url($ulams['platform']['oidc_auth_url']));
    $registration->set_jwksurl(new moodle_url($ulams['platform']['jwks_url']));
    $registration->set_accesstokenurl(new moodle_url($ulams['platform']['token_url']));
    $registration->complete_registration();
    $registration = $repo->save($registration);
    $deployment = $registration->add_tool_deployment('ulams', (string) $ulams['deployment_id']);
    (new \enrol_lti\local\ltiadvantage\repository\deployment_repository())->save($deployment);
    echo json_encode(['registration' => $registration->get_id()]) . "\n";
    exit(0);
}

if ($command === 'tool-grade') {
    $value = (float) ($argv[2] ?? 0);
    $graded = [];
    foreach ($DB->get_records('enrol_lti_tools') as $tool) {
        $instance = $DB->get_record('enrol', ['id' => $tool->enrolid]);
        if (!$instance || $tool->ltiversion !== 'LTI-1p3') {
            continue;
        }
        $item = grade_item::fetch(['courseid' => $instance->courseid, 'itemtype' => 'manual', 'itemname' => 'Points']);
        if (!$item) {
            continue;
        }
        foreach ($DB->get_records('user_enrolments', ['enrolid' => $instance->id]) as $ue) {
            $item->update_final_grade($ue->userid, $value, 'conformance');
            $graded[] = (int) $ue->userid;
        }
        grade_regrade_final_grades($instance->courseid);
    }
    $task = new \enrol_lti\local\ltiadvantage\task\sync_grades();
    ob_start();
    $task->execute();
    $log = ob_get_clean();
    echo json_encode(['graded' => $graded, 'log' => mb_substr($log, -2000)], JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

fwrite(STDERR, "usage: moodle-setup.php setup | grade <cmid> <username> | tool-draft | tool-complete <uniqueid> '<json>' | tool-grade <grade>\n");
exit(2);
