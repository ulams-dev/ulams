# Personalisation design prompts (Stitch)

Prompts sent to the Stitch project "ulams Course Builder" (`4264779900987202361`), design system
"Editorial Intelligence" (`assets/f711dc67c76f4846a76c762f05e0ff6e`), desktop, on 2026-10-09. Kept so
the screens can be regenerated. Plan: `docs/plans/phase-4.md` section 11.3.

Screens 01–03 are learner pages: when implemented they render in the tenant theme (`--ulams-*`), not
the platform brand. Screens 04–05 are studio/admin screens in the platform brand.

## 01-learner-lesson-support — Lesson with a personal remediation and its explanation

ulams learner lesson page — Personalisation: personal help inside a lesson. Clean learner reading layout
in a neutral tenant theme (white page, one accent colour, Inter UI, serif headings). Top bar with course
title "Git Basics for Teams", lesson "2.3 Rebasing without fear", progress "Lesson 7 of 12 · 6 min left".
Main column: the lesson text. Below the quiz link, a card labelled with a small sparkle icon and the text
"Extra help for you" and a tag "AI-generated · based on this course's sources". Card title "Another way to
see rebasing". Content: a short simpler explanation, one worked example in a code block (`git rebase
main`), and two citation chips (§3.2 Rebasing, §3.4 Conflicts). A disclosure "Why am I seeing this?"
expanded: "You answered question 3 of the lesson 2.3 quiz incorrectly twice. Suggested because you scored
low on: Rebasing onto main." with a link "Your personalisation settings". Buttons: "This helped", "Not
helpful", "Hide extra help in this course". Right rail: "What's next" with "Retry the quiz (attempt 3 of
3)" and the course outline with icon+text status per lesson ("Done", "In progress", "Needs review").
Everything accessible: no colour-only status, visible focus rings, readable at 360 px.

## 02-learner-privacy — Personalisation and privacy settings for a learner

ulams learner account page — Personalisation and privacy. Tenant theme, account layout with left nav
(Profile, My courses, Certificates, Personalisation & privacy (active)). Title "Personalisation &
privacy", intro "We use your activity in this organisation's courses to offer extra help. You decide."
Section 1 "Personalised help" with a switch (on) and text "Extra explanations and practice when you seem
stuck. Generated from course sources only." Section 2 "Reminders" with a switch and a select "At most one
reminder per week". Section 3 "What we use" — a plain list: quiz attempts and scores, lesson progress
and time spent, interactive activity results (SCORM, H5P, cmi5). "We never send your name, e-mail or
other courses to the AI provider. Only the lesson text and the question you struggled with." Section 4
"Your current status" — per course: "Git Basics for Teams — On track" with reason "Last active 2 days
ago, all quizzes passed"; "Kubernetes Essentials — Needs attention" with reasons "9 days without
activity; 2 failed attempts on quiz 3.1". Section 5 "Your data" with buttons "Download my learning data
(JSON)", "Delete my personalisation data", note "Kept for 180 days, set by your organisation." Footer
link "How personalisation works". All controls labelled, keyboard accessible.

## 03-learner-tutor — AI tutor panel in a lesson

ulams learner lesson page — AI tutor. Same lesson layout as 01, with a right-side panel "Ask about this
course" (labelled "AI tutor · answers only from this course"). Conversation: learner asks "Why does rebase
rewrite commit hashes?"; tutor answers in 3 sentences with two citation chips (§3.2 Rebasing, §3.5 Golden
rule) and a link "Open §3.2 in the lesson". Second learner message "What's the answer to quiz question
4?" and the tutor replies "You're in an active quiz attempt, so I can't give answers. I can explain the
idea behind it: …". A third question "How do I set up GitHub Actions?" answered "That's outside this
course. The course covers local Git workflows." Composer with placeholder "Ask a question about this
course", a note "Conversations are anonymised in reports" and a counter "18 of 30 questions left today".
States shown subtly: streaming dots on the last message. No colour-only states.

## 04-studio-insights — Course insights for authors

ulams Course Builder — Personalisation: Course insights. Studio shell (dark ink left rail: Sessions,
Sources, Updates, Insights (active), Audit). Warm paper canvas, white cards, hairline borders, Inter UI,
Newsreader headings, JetBrains Mono for ids and numbers. Title "Where learners struggle", subtitle
"Aggregated over 214 learners in the last 30 days. Individual learners are not shown." Filters: period,
cohort (all / enrolled after 1 Oct), minimum group size 10. Four stat cards: "On track 71%", "Struggling
21%", "At risk 8%", "Recovered after help 64%". Main table by element (lesson › block or quiz question,
label in mono): struggle rate, remediation rate, recovery rate, top reasons ("failed attempts", "time far
above median", "rewatching"), each with a small bar and the number in text. Row for "2.3 Rebasing · quiz
question 3" highlighted: "Triggers extra help for 38% of learners". Row action "Propose a course
improvement" opening a side panel: "Create an update proposal for this element. The AI will suggest a
clearer explanation, grounded in your sources. You review it as a diff before anything changes." with
estimate "≈ $0.06" and button "Create proposal". Second panel "Interventions": nudges sent 42, opened 31,
learners back on track 19. Empty-state note for small groups: "Not enough learners yet to show this
element (fewer than 10)."

## 05-admin-insights-settings — Learner Insights settings for a tenant

ulams admin — Learner Insights settings (platform brand, settings page with left nav: General, AI,
Learner Insights (active), Notifications). Title "Learner Insights". Master switch "Enable Learner
Insights for this organisation" (on). Section "Signals and retention": retention select "180 days",
"Delete signals of deleted users immediately" (checked, disabled), "Backfill from existing activity" with
button "Run backfill" and last run status. Section "Rules" as a table with editable thresholds and
defaults in muted text: "Inactive for more than 7 days → at risk", "2 failed attempts on the same
question → struggling", "Time on element above 3× cohort median → struggling", "Lesson opened but not
finished for 5 days → struggling", "Same video rewatched 3+ times → struggling"; each row with a toggle
and "Reset to default". Section "Interventions": personal help on/off, monthly AI budget "$20", cache
reuse note, nudges via e-mail and in-app, "at most 1 per learner per 7 days". Section "Transparency":
"Learners can see why they got help (always on)", "Show status to learners" toggle, "Learners can opt
out" (always on), link "Data flow to the AI provider". Save bar.
