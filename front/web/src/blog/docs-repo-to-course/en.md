I pointed ULAMS at a GitHub repository of product docs and got a cited course out of it. Then I changed the docs and watched the course react.

The docs are `ulams-dev/demo-docs`: four Markdown files for Pigeon, a webhook service I made up (MIT). The Studio read 13 passages from them.

What I did in the Studio:

- connected the repository: owner/name, default branch, no token
- answered the interview: developers and operators, intermediate, 60 minutes, professional tone, a quiz after each lesson
- approved the outline: 3 modules, 6 lessons, 22 learning objectives. The README was left out because it describes the repo, not the product
- applied the course, acknowledged the warnings, published

What came out: 6 lessons, 18 quiz questions, 35 blocks. All 35 cite one of 12 source passages. The grounding check flagged three statements as possibly unsupported, and I saw them before publishing.

Timings from the Studio's own records: the outline took 24 seconds, lessons, quizzes and checks 6 min 57 s, apply 23 seconds. The whole build cost $0.28, 27 model calls in total (Sonnet for outline, lessons and quizzes, Haiku for the interview, the grounding check and metadata). Two calls returned output that failed validation.

Then the part I care about. I put a commit on another branch of the repo: a timeout from 10 to 15 seconds, a disable window from 3 to 5 days, and a new section on pausing an endpoint. I pointed the course at that branch and pressed "Check now":

- the new revision showed 2 changed passages and 1 added, found without a model
- the course listed 12 elements across 3 lessons that might need an update, 2 quiz answers to check and 1 section no lesson covers
- the AI proposed edits as diffs, each citing its passage. The analysis took 1 min 23 s and about $0.09
- nothing in the course changed. AI proposes, the author approves

What's real, and what isn't:

- real model, real repository, the real Studio, on a local academy
- Pigeon and its docs are fictional. I wrote them for this
- the course was not deployed anywhere, so there is no learner link
- I did not try the Notion source in this run, and I'm not claiming anything about it here
- this is one run on a small repo. It says nothing about large documentation sets

Do you have docs that drift away from the training built on them?
