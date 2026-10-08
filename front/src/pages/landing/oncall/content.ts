import type { FormatKey } from "../shared/formats";

/** Brief copy for "On-Call" (front/docs/design/experiences.md §3), used when the API has no data yet. */
export const ONCALL = {
  brand: "On-Call",
  courseTitleHint: "on-call",
  courseTitle: "On-Call — Incident Command for Platform Engineers",
  hero: {
    status: "Applications open",
    title: "Stay calm at 3 a.m.",
    sub: "A 4-week cohort for engineers who carry the pager.",
    summary:
      "Command a simulated outage, coordinate responders, talk to stakeholders and write the postmortem — with a live game day graded by people who have done it for real.",
    cohort: { label: "Next cohort", date: "4 Nov", taken: 18, seats: 24 },
  },
  incident: {
    id: "INC-2041",
    service: "payments-api",
    events: [
      { t: "03:12:04", level: "red", label: "SEV1", text: "Declared — checkout error rate 38%" },
      { t: "03:13:40", level: "blue", label: "IC", text: "Incident commander assigned: priya" },
      { t: "03:15:02", level: "amber", label: "COMMS", text: "Status page: Investigating" },
      { t: "03:21:57", level: "blue", label: "OPS", text: "Rollback of payments-api v412 started" },
      { t: "03:41:18", level: "green", label: "MITIGATED", text: "Error rate back to 0.4%" },
      { t: "03:58:00", level: "muted", label: "RESOLVED", text: "Postmortem due in 5 business days" },
    ] as Array<{ t: string; level: "red" | "amber" | "green" | "blue" | "muted"; label: string; text: string }>,
  },
  modules: [
    {
      title: "Briefing",
      week: "Week 1",
      topics: ["Course kickoff", "Rules of engagement"],
      formats: ["video", "reading"],
    },
    {
      title: "Signals",
      week: "Week 1",
      topics: ["Anatomy of an alert", "SLOs and error budgets", "Alert fatigue is a bug"],
      formats: ["image", "reading", "embed"],
    },
    {
      title: "Command",
      week: "Week 2",
      topics: ["Taking command", "Radio discipline", "Who does what?", "Runbook template"],
      formats: ["video", "audio", "interactive", "pdf"],
    },
    {
      title: "Game day",
      week: "Week 3",
      topics: ["Outage simulator", "Live drill", "Comms check"],
      formats: ["scorm", "tracked", "quiz"],
    },
    {
      title: "After",
      week: "Week 4",
      topics: ["Blameless postmortems", "Postmortem assignment", "Reading list"],
      formats: ["reading", "project"],
    },
  ] as Array<{ title: string; week: string; topics: string[]; formats: FormatKey[] }>,
  formatLabels: {
    video: "video",
    audio: "audio",
    reading: "reading",
    image: "image",
    pdf: "pdf runbook",
    embed: "embed",
    interactive: "branching",
    scorm: "scorm sim",
    tracked: "live drill",
    quiz: "quiz",
    project: "postmortem",
  } as Record<FormatKey, string>,
  chat: [
    { t: "03:13", who: "priya", role: "IC", text: "I'm IC. marek has comms, ola has ops. Scribe: tom." },
    { t: "03:14", who: "ola", role: "ops", text: "5xx started with deploy v412 at 03:09. Proposing rollback." },
    { t: "03:15", who: "marek", role: "comms", text: "Status page posted. Next update 03:30." },
    { t: "03:16", who: "priya", role: "IC", text: "Approved. Roll back, then watch error rate for 10 min." },
  ],
  metrics: [
    { name: "error rate", value: "38.2%", level: "red", points: "0,28 10,27 20,28 30,26 40,6 50,4 60,5 70,12 80,22 90,26 100,27" },
    { name: "p99 latency", value: "2.4 s", level: "amber", points: "0,24 10,25 20,23 30,22 40,10 50,8 60,9 70,14 80,20 90,23 100,24" },
    { name: "queue depth", value: "1.2k", level: "green", points: "0,26 10,25 20,24 30,24 40,20 50,18 60,19 70,22 80,24 90,25 100,25" },
  ] as Array<{ name: string; value: string; level: "red" | "amber" | "green"; points: string }>,
  services: [
    { name: "checkout-web", status: "degraded", level: "amber" },
    { name: "payments-api", status: "incident", level: "red" },
    { name: "ledger", status: "healthy", level: "green" },
    { name: "notifications", status: "healthy", level: "green" },
  ] as Array<{ name: string; status: string; level: "red" | "amber" | "green" }>,
  instructors: [
    {
      name: "Priya Raman",
      role: "Staff SRE",
      stats: [
        ["incidents commanded", "140+"],
        ["years on-call", "9"],
      ],
    },
    {
      name: "Marek Lis",
      role: "Incident commander, fintech",
      stats: [
        ["SEV1s run", "60+"],
        ["game days a year", "4"],
      ],
    },
  ],
  pricing: [
    {
      name: "Per seat",
      price: "€490",
      unit: "per engineer",
      items: ["4-week cohort", "Live game day", "Certificate: Incident Commander — Level 1"],
      cta: "Apply",
    },
    {
      name: "Team subscription",
      price: "Custom",
      unit: "5+ seats",
      items: ["Seats across cohorts", "Private drill for your stack", "Progress reports for leads"],
      cta: "Talk to us",
    },
    {
      name: "Voucher codes",
      price: "Prepaid",
      unit: "for companies",
      items: ["Buy seats now, assign later", "One code per engineer", "Valid for 12 months"],
      cta: "Get codes",
    },
  ],
  slots: ["Tue 10:00", "Tue 15:30", "Wed 09:00", "Thu 17:00"],
  webinar: { title: "Postmortem teardown — live", when: "Every second Thursday, 18:00 CET" },
  testimonials: [
    { ts: "2026-09-14T10:02Z", who: "eng-lead@fintech", text: "Our time to mitigate dropped from 52 to 31 minutes after two cohorts." },
    { ts: "2026-08-30T16:44Z", who: "head-of-platform@retail", text: "The game day felt more real than our last real SEV1. In a good way." },
    { ts: "2026-07-21T08:15Z", who: "sre-manager@saas", text: "New joiners now ask to take IC. That never happened before." },
  ],
  faq: [
    { q: "How much time does it take?", a: "About 4 hours a week, plus the 2-hour live game day in week 3." },
    { q: "Do I need a specific stack?", a: "No. The simulator and drills are tool-agnostic; examples use Kubernetes and Grafana-style dashboards." },
    { q: "How is the game day graded?", a: "Tutors score role clarity, comms cadence and decision quality from the drill timeline and the chat log." },
    { q: "Can my company pay?", a: "Yes. Buy voucher codes or a team subscription and assign seats later." },
  ],
};
