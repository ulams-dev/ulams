# 0032. Demo login supports the tutor role

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

`POST /api/demo/login` accepted `student` and `admin` only, so `{"role": "tutor"}` answered 422. Tutors are
the authors who use the Course Builder studio, and every tenant already gets a `tutor@<domain>` account from
`ulams:tenant:seed-demo`, which survives the hourly reset. A demo visitor could not try the author flow
without the admin role.

## Decision

Support the tutor: `DemoRole::TUTOR`, resolved like the student (`DEMO_TUTOR_EMAIL`, else `tutor@` at the
admin's e-mail domain, else the first user with the role). `GET /api/demo` lists the tutor account when it
exists. A tenant without a seeded tutor still answers 422. No other change: demo mode already has no
security by design (README of the demo package), and a tutor holds fewer permissions than the admin that
the same endpoint already issues.

## Consequences

- Good: the studio can be demonstrated with the author role only.
- Bad: one more passwordless account on a demo tenant, with the same exposure as the existing two.
