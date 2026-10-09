# Living Course design prompts (Stitch)

Prompts sent to the Stitch project "ulams Course Builder" (`4264779900987202361`), design system
"Editorial Intelligence" (`assets/f711dc67c76f4846a76c762f05e0ff6e`), desktop, on 2026-10-09. Kept so
the screens can be regenerated. Plan: `docs/plans/phase-3.md` section 11.3.

## 01-sources — Sources and sync settings

ulams Course Builder — Living Course: Sources and sync settings. Studio app shell (dark ink left rail:
Sessions, Sources (active), Updates, Audit). Warm paper canvas, white cards, hairline borders, Inter UI,
Newsreader headings, JetBrains Mono for ids, URLs, commit hashes, timestamps. Iris for primary actions,
amber only for citations, green for "In sync". Header "Sources of this course" with the subtitle "When a
source changes, ulams finds the affected lessons and proposes updates for you to review. Nothing changes
for learners until you approve." Buttons "Check for updates now", "Add a source". Three source cards:
Git repository (`github.com/acme/git-handbook`, `docs/guide/**/*.md`, `main`, "In sync · revision 4 ·
9f8e7d6", last checked by webhook, webhook URL with copy, secret with Rotate, token stored encrypted and
read-only, buttons Check now / Edit / Disconnect); uploaded file (`team-workflow.pdf`, "Update pending
review · revision 3", link "Review proposal #3", drop zone "Drop a new version of this file to check what
changed"); web page (`https://docs.acme.dev/cli/reference`, "Checking…", main content selector, weekly,
allowed host). Right column: revision timeline (r4 accepted, r3 no impact, r2 one lesson updated,
r1 initial import) and "Cost this month: $1.12 of $10.00 sync budget". Muted hint: "Connect Google Drive
or Notion — coming as plugins".

## 02-proposal-review — Update proposal review

ulams Course Builder — Living Course: Update proposal review. Studio shell (Updates active with a count
badge). Breadcrumb "Git Basics for Teams › Updates › Proposal #3". Title "Source update: 4 lessons
affected". Meta in mono: repository, path, branch, `a1b2c3d → 9f8e7d6`, detected 2 days ago, analysis
cost $0.38. Buttons "Reject all", "Accept 5 of 7 and apply". Four stat cards (source changes, elements
affected, quiz answers changed, learners affected with "progress is kept"). Left column "What changed in
the source": fragment cards with amber chip (§3.2 Rebasing), status pill (Changed / Removed / Added /
Moved), word-level diff with + and − markers (not colour only); an added section "Not used by any lesson
yet". Center "Proposed changes" grouped by lesson: element label in mono, plain reason ("The source now
says pull merges by default (§3.2)."), before/after diff in Newsreader, citation chips, Accept/Reject
toggle, "Ask for changes"; a question card with the warning "Correct answer changed: B → C. 12 learners
answered this question; they keep their score and are asked to re-attempt it."; a collapsed "Citation
update only — no content change" item; a "Conflict: you edited this element after the analysis —
regenerate" item. Right panel "Learner impact & rules" and a mini audit timeline. Sticky bar "5 accepted
· 1 rejected · 1 needs attention" and "Apply accepted changes".

## 03-workspace-staleness — Workspace with staleness signals

ulams Course Builder — Living Course: Workspace with staleness signals. The existing workspace layout
(tree, preview, inspector). Amber-tinted banner with icon: "The source changed 14 days ago. An update
proposal affects 4 lessons." with "Review proposal #3" and "Remind me tomorrow". Tree markers with icon
and text: "Update pending", "In sync", "Source removed", "Answer may be wrong"; filter chips "All · Stale
(5) · In sync". Preview paragraph with a dashed amber outline and margin note "Based on §3.2 Rebasing —
changed 14 days ago". Inspector "Freshness": cited sections with state, synced revision 3 vs latest
revision 4, small old/new diff, "Open in proposal", "Edit in chat", learner visibility toggle (off).
Header badge "Stale · 14 days", "Last synced 2 Oct".

## 04-audit-trail — Audit trail

ulams Course Builder — Living Course: Audit trail. Title "Who changed what, and why", subtitle "Every AI
proposal, decision and source revision for this course. Entries cannot be edited." Export CSV / JSON,
badge "Chain verified". Filters: date range, actor (person / system / agent), action, source. Table:
time (UTC, mono), actor (person, "System (webhook)", "Agent: release-bot on behalf of Anna"), action,
element label, source revision (`r4 · 9f8e7d6`), blueprint version (`v18 → v19`), details. Rows: applied
proposal, re-attempt requested for 12 learners with past scores unchanged, accepted change, rejected
change, proposal created with cost, revision detected by webhook, webhook secret rotated. Detail drawer:
reason, diff snippet, cited fragments, AI calls with task, profile label, tokens and cost, decided by,
shortened IP, entry hash and previous hash.

## 05-learner-notices — Learner lesson with update notices

ulams learner lesson page — Living Course notices for learners (tenant-themed learner player, not the
studio). Top bar with academy name, course title and progress. Lesson list where a completed lesson has
an "Updated" label. Notice card on lesson 2.1: "Updated since you completed it — 2 Oct 2026. What
changed: git pull now merges by default; the lesson explains how to turn rebase on. Your completion is
kept." with "See the changes" and "Mark as reviewed". Quiz notice: "One question in 'Quiz: Rebasing
safely' was corrected. Your previous score (8/10) stays on record. Retake that question to update your
result." with "Retake corrected question" and "Later". Sources footnotes with "§3.2 Rebasing — Git
handbook, revision 4". WCAG AA, visible focus outlines.
