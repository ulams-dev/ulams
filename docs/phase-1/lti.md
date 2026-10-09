# LTI 1.3 with Moodle (and other LMSs)

Notes for the docs site. API and security: `api/packages/lti/README.md`; decision: ADR 0012. The
settings below are the ones the nightly conformance run uses against Moodle 5.0
(`.github/conformance/lti/moodle-setup.php`), so they are known to work.

## Moodle launches ulams courses (ulams as a tool)

In Moodle: *Site administration → Plugins → Activity modules → External tool → Manage tools →
configure a tool manually*:

| Moodle field | Value |
|---|---|
| Tool URL | `<ulams API>/api/lti/tool/launch` |
| LTI version | LTI 1.3 |
| Public key type | **Keyset URL** |
| Public keyset | `<ulams API>/api/lti/jwks` |
| Initiate login URL | `<ulams API>/api/lti/tool/login` |
| Redirection URI(s) | `<ulams API>/api/lti/tool/launch` |
| Default launch container | **New window** (inside an iframe the browser may block the ulams session cookie) |
| Share launcher's name / e-mail | as you prefer (an e-mail only creates an account if no ulams account has it) |
| Accept grades from the tool | Always; IMS LTI Assignment and Grade Services: *Use this service for grade sync and column management* |

Moodle then shows the **client ID** and the **deployment ID** (the tool id). In ulams
(*Integrations → LTI → Platforms*) add a platform with: issuer = Moodle's site URL, the client ID,
the deployment ID, authentication request URL `<moodle>/mod/lti/auth.php`, access token URL
`<moodle>/mod/lti/token.php`, keyset URL `<moodle>/mod/lti/certs.php`.

Add the ulams course to a Moodle course as an *External tool* activity, either with **Select
content** (deep linking: the ulams course picker) or with the custom parameter `course_id=<id>`.

What learners get: a ulams account linked to their Moodle user (never matched by e-mail), access to
the course, and their progress in ulams sent to Moodle's gradebook as a percentage every time they
finish a topic (100 % when every topic is finished). Instructors become tutors; nobody becomes admin.

## ulams launches a Moodle course (ulams as a platform)

1. In Moodle, enable *Enrolments → Publish as LTI tool* and the *LTI* authentication plugin, then
   publish a course (or activity) as an LTI 1.3 tool with grade sync on. Moodle shows its tool URLs
   (login, launch, deep linking, JWKS) and the custom parameter `id=<uuid>` of the resource.
2. In Moodle *Tool registration*, register ulams as a platform. In ulams (*Integrations → LTI →
   Tools*) add the tool with Moodle's URLs; ulams issues a client ID and a deployment ID. Enter them in
   Moodle's registration with ulams' issuer, `.../api/lti/platform/authorize`,
   `.../api/lti/platform/token` and `.../api/lti/jwks`, and add the deployment.
3. Add a lesson topic of type *External tool (LTI)* with that tool, the launch URL
   `<moodle>/enrol/lti/launch.php` and the custom parameter `id=<uuid>`.
4. Moodle answers a launch with an "Open tool" link instead of the course unless
   *Security → HTTP security → Allow frame embedding* is on; turn it on, or use the *New window*
   presentation.

Grades Moodle sends (course total, through Moodle's grade sync task) are stored per learner in
ulams; a `Completed`/`FullyGraded` score completes the topic.

## Local and test setups

Outgoing LTI calls only go to public `https` addresses. For Moodle in Docker or on `http`, set
`LTI_ALLOW_INSECURE_URLS=true` on the API, and in Moodle clear *HTTP security → cURL blocked hosts*
and *cURL allowed ports* (Moodle blocks private addresses by default).
