import type { FormatKey } from "../shared/formats";

export type Level = "red" | "amber" | "green" | "blue" | "muted";

/**
 * Copy for "On-Call" (front/docs/design/experiences.md §3 and the Stitch screen
 * front/docs/design/stitch/screens/oncall-landing). Course, lessons, topics, tutors,
 * webinars and prices come from the tenant API when it has them; this is the fallback.
 */
export const ONCALL = {
  brand: "On-Call",
  courseTitleHint: "on-call",
  courseTitle: "On-Call — Incident Command for Platform Engineers",
  status: "Systems operational · latency 14ms",
  ticker: {
    tag: "INCIDENT-4092: RESOLVED",
    text: "prod-us-east // latency stabilized 42ms // all APIs nominal",
    drill: "Drill starts in 14d 06h",
  },
  hero: {
    kicker: "Incident command academy · SRE / DevOps specialization",
    title: "Stay calm at 3 a.m.",
    sub: "A 4-week cohort for engineers who carry the pager.",
    summary:
      "Command a simulated outage, coordinate responders, talk to stakeholders and write the postmortem — with a live game day graded by people who have done it for real.",
    cohort: { label: "Next cohort", date: "04 Nov", taken: 18, seats: 24 },
    stats: [
      { label: "MTTR reduction", value: "58m → 14m", note: "−75.8% avg duration", level: "green" },
      { label: "Drill pass rate", value: "94.2%", note: "2,400+ responders", level: "blue" },
    ] as Array<{ label: string; value: string; note: string; level: Level }>,
    image: {
      src: "/landing/oncall/war-room.webp",
      alt: "A dark operations war room at night, with an engineer watching wall-mounted dashboards and terminals.",
      caption: "War-room simulation pod // Berlin telemetry node",
      status: "Live drill ready",
    },
  },
  incident: {
    title: "SEV-1 active incident #4092",
    service: "prod-payment-gw-us-east-1",
    time: "03:12:04 UTC",
    roles: [
      ["IC", "@priya.raman"],
      ["Comms", "@marek.lis"],
      ["Ops", "@devon.k"],
      ["Scribe", "@sarah.m"],
    ] as Array<[string, string]>,
    telemetry: {
      label: "Telemetry: p99 latency spike (ms)",
      status: "Restored 42ms",
      baseline: "03:00 baseline (38ms)",
      peak: "Peak: 2,410ms",
      mitigated: "Mitigated: 42ms",
    },
    events: [
      {
        t: "03:12:04",
        level: "red",
        label: "ALERT",
        text: "P99 latency breached 2,400ms (>250ms threshold) — PagerDuty auto-escalated to primary IC.",
      },
      {
        t: "03:14:20",
        level: "blue",
        label: "COMMAND",
        text: "IC Priya Raman declared SEV-1. Incident room #inc-4092 open, war-room audio active (8 responders).",
      },
      {
        t: "03:18:55",
        level: "amber",
        label: "ACTION",
        text: "Ops sheds non-critical ingest traffic; circuit breaker isolates redis-cluster-replica-04.",
      },
      {
        t: "03:26:10",
        level: "blue",
        label: "COMMS",
        text: "First status update posted: “Investigating elevated latency on US-East card charges.”",
      },
      {
        t: "03:41:00",
        level: "green",
        label: "MITIGATION",
        text: "Hotfix v4.12.8 deployed. Queues drained, p99 back to 42ms. SEV-1 downgraded to SEV-3.",
      },
    ] as Array<{ t: string; level: Level; label: string; text: string }>,
    command: { cmd: "!ic handover", args: "to @devon.k --reason \"postmortem handoff\"" },
  },
  curriculum: {
    kicker: "// 4-week SRE blueprint",
    title: "Curriculum as a production pipeline",
    sub: "5 modules, 15 topics with real artifacts — and a live game day in week 3.",
    meta: "Total duration: 28 days · 12 hours live sims",
  },
  modules: [
    {
      title: "Briefing",
      week: "Week 1",
      blurb: "Cohort welcome, schedule, grading and the rules everyone follows on the bridge.",
      topics: [
        { title: "Course kickoff", format: "video" },
        { title: "Rules of engagement", format: "reading" },
      ],
      tags: ["video", "reading"],
    },
    {
      title: "Signals",
      week: "Week 1",
      blurb: "Read dashboards like an IC and alert on what users feel.",
      formula: "budget = (1 − SLO) × window",
      topics: [
        { title: "Anatomy of an alert", format: "image" },
        { title: "SLOs and error budgets", format: "reading" },
        { title: "Talk: “Alert fatigue is a bug”", format: "embed" },
      ],
      tags: ["image", "reading", "embed"],
    },
    {
      title: "Command",
      week: "Week 2",
      blurb: "Take command, keep the bridge disciplined and decide under pressure.",
      topics: [
        { title: "Taking command", format: "video" },
        { title: "Radio discipline", format: "audio" },
        { title: "Who does what?", format: "interactive" },
        { title: "Incident runbook template", format: "pdf" },
      ],
      tags: ["video", "audio", "interactive", "pdf"],
    },
    {
      title: "Game day",
      week: "Week 3",
      blurb: "Command a simulated cascading failure, run the live drill and check your comms.",
      topics: [
        { title: "Outage simulator", format: "scorm" },
        { title: "Live drill", format: "tracked" },
        { title: "Comms check", format: "quiz" },
      ],
      tags: ["scorm", "tracked", "quiz"],
    },
    {
      title: "After",
      week: "Week 4",
      blurb: "Turn the outage into learning: blameless postmortem, review, reading.",
      topics: [
        { title: "Blameless postmortems", format: "reading" },
        { title: "Postmortem assignment", format: "project" },
        { title: "Reading list", format: "reading" },
      ],
      tags: ["reading", "project"],
    },
  ] as Array<{
    title: string;
    week: string;
    blurb: string;
    formula?: string;
    topics: Array<{ title: string; format: FormatKey }>;
    tags: FormatKey[];
  }>,
  formatLabels: {
    video: "video",
    audio: "audio bridge",
    reading: "richtext",
    image: "image",
    pdf: "pdf runbook",
    embed: "embed",
    interactive: "h5p branching",
    scorm: "scorm simulator",
    tracked: "cmi5 live",
    quiz: "gift quiz",
    project: "project",
  } as Record<FormatKey, string>,
  simulator: {
    kicker: "// The chaos drill arena",
    title: "Outage simulator: live chaos injection",
    sub: "You do not learn command by watching slides. In week 3 you are dropped into an instrumented cluster with network partitions, cascading OOM kills and a split-brain database — and you run the bridge.",
    session: "chaos-runner :: session #891",
    target: "target: eu-west-multi-az-k8s",
    injected: "Chaos injected: 3 SEV-1 triggers",
    pods: {
      label: "Pod clusters // telemetry",
      status: "Degraded (18/48)",
      rows: [
        { name: "api-gateway-v2", status: "CrashLoopBackOff", level: "red" },
        { name: "payment-auth-srv", status: "OOMKilled (137)", level: "red" },
        { name: "redis-cache-shard-01", status: "Healthy (1.2ms)", level: "green" },
        { name: "postgres-primary", status: "Lock contention 89%", level: "amber" },
      ] as Array<{ name: string; status: string; level: Level }>,
      gauge: { label: "CPU saturation", value: 94.8, note: "limit threshold" },
    },
    chat: {
      channel: "#war-room-drill-live",
      responders: "8 active responders",
      lines: [
        { who: "@alex.lead", text: "!ic claim --role IC-primary" },
        { who: "@drill-bot", text: "Command acknowledged. @alex.lead is now incident commander." },
        { who: "@maria.ops", text: "!page database-oncall \"Postgres connection pool maxed out by auth loop\"" },
        { who: "@alex.lead", text: "!status update \"Rate limiting enabled on /v1/authorize to protect the DB\"" },
      ],
      prompt: "!status page \"Mitigation active. DB connections declining.\"",
    },
    bench: [
      { label: "Mean time to acknowledge", value: "1.8 min", note: "Down from 9.4 min baseline", level: "green" },
      { label: "Blameless review score", value: "98.4 / 100", note: "Peer validated", level: "blue" },
    ] as Array<{ label: string; value: string; note: string; level: Level }>,
    footnote: "Every learner runs four live-fire outage scenarios with instant feedback.",
  },
  instructorsIntro: {
    kicker: "// Directors of incident training",
    title: "Staff command instructors",
    sub: "Taught by a staff SRE and an incident commander who have carried real, high-stakes pagers.",
  },
  instructors: [
    {
      name: "Priya Raman",
      role: "Staff SRE",
      photo: "/landing/oncall/priya-raman.webp",
      affiliation: "Payments infrastructure · 9 years on call",
      bio: "Commanded 140+ SEV-1 incidents across distributed payment ledgers. Specialises in split-brain recovery and cascading-failure isolation.",
      stats: ["4.98/5 rating", "1,200+ alumni"],
    },
    {
      name: "Marek Lis",
      role: "Incident commander",
      photo: "/landing/oncall/marek-lis.webp",
      affiliation: "Fintech infrastructure · blameless postmortem author",
      bio: "Runs incident response for tier-1 banking rails. Has trained 800+ on-call engineers to replace finger-pointing with structural fixes.",
      stats: ["4.95/5 rating", "1,400+ alumni"],
    },
  ],
  review: {
    kicker: "// Synchronous expert access",
    title: "Book an on-call review",
    text: "A private 1:1 with Priya or Marek. Audit your escalation tiers, alert signal-to-noise and runbook coverage.",
    slotsLabel: "Available live slots (UTC)",
    slots: [
      { when: "12 Nov · 14:00 UTC", who: "Priya Raman · 1 slot" },
      { when: "14 Nov · 16:30 UTC", who: "Marek Lis · 2 slots" },
      { when: "19 Nov · 18:00 UTC", who: "Priya Raman · 1 slot" },
    ],
    cta: "Reserve a 1:1 review",
    note: "Included with team cohort passes",
  },
  webinar: {
    tag: "Free public war-room teardown",
    label: "Live stream",
    title: "Postmortem teardown — live",
    text: "A real postmortem taken apart step by step: the timeline, the routing tables and the fallback that failed, with the original incident commander.",
    date: "19 Nov",
    time: "17:00–18:30 UTC",
    cta: "Register for the live teardown",
  },
  reviews: {
    kicker: "// Incident retrospective logs",
    title: "System-verified reviews",
    sub: "Feedback from engineering leads who run high-scale production systems.",
    lines: [
      {
        ts: "2026-09-14T10:02:11Z",
        text: "Our junior on-call engineers went from panic and notification noise to structured incident command in 4 weeks.",
        who: "VP Infrastructure, Series C fintech",
      },
      {
        ts: "2026-08-30T16:44:02Z",
        text: "The game day felt more real than our last real SEV1. Unforgiving, high-adrenaline and genuinely educational.",
        who: "Head of Platform, retail",
      },
      {
        ts: "2026-07-21T08:15:40Z",
        text: "The blameless postmortem guide changed how our leadership handles production outages.",
        who: "Staff systems engineer, global CDN",
      },
      {
        ts: "2026-06-02T22:04:55Z",
        text: "Our time to mitigate dropped from 52 to 31 minutes after two cohorts.",
        who: "Director of Engineering, SaaS",
      },
    ],
  },
  pricing: {
    kicker: "// Enrollment plans",
    title: "Incident readiness pricing",
    sub: "Fits engineering training and professional development budgets. VAT invoices and reimbursement templates included.",
    individual: {
      name: "Individual engineer",
      meta: "4 weeks",
      price: "€490",
      unit: "/ engineer · one-off",
      text: "For SREs, software engineers and DevOps leads stepping into a primary on-call rotation.",
      items: [
        "All 5 modules and 15 topics",
        "Live game day with the outage simulator",
        "1:1 postmortem review by a staff SRE",
        "Certificate: Incident Commander — Level 1",
      ],
      cta: "Enrol as an individual",
    },
    team: {
      badge: "Recommended for teams",
      name: "Platform team cohort",
      meta: "5–20 seats",
      price: "€420",
      unit: "/ seat · save 15%",
      text: "Train your whole rotation together and standardise radio protocol, severity response and runbooks.",
      items: [
        "Everything in the individual seat",
        "Private game day on your own stack",
        "Company runbook audit",
        "Private channel with the instructors",
      ],
      cta: "Book a team cohort",
    },
    voucher: { label: "Have a company voucher code?", placeholder: "e.g. TEAM-SRE-2026", cta: "Apply code" },
  },
  faq: {
    kicker: "// Incident operational FAQ",
    title: "Frequently answered questions",
    items: [
      {
        q: "What is the weekly time commitment?",
        a: "About 4 hours a week: live game-day practice with recorded debriefs, asynchronous case studies, and time reviewing runbooks and postmortems. The live game day in week 3 takes 2 hours.",
      },
      {
        q: "Do I need Kubernetes or a local cluster?",
        a: "No. Drills run in a hosted sandbox. You need a modern browser; the simulator and drills are tool-agnostic, with examples in Kubernetes and Grafana-style dashboards.",
      },
      {
        q: "Can my company pay for it?",
        a: "Yes. Buy voucher codes or a team cohort and assign seats later. We send VAT invoices and a short justification letter for your manager.",
      },
      {
        q: "How does the Incident Commander certificate work?",
        a: "Pass the live drill and the comms check, and submit a blameless postmortem that passes the tutors' rubric. You then receive “Incident Commander — Level 1”.",
      },
    ],
  },
  cta: {
    kicker: "// Roster closing soon",
    title: "Ready to take command of production?",
    text: "Join 24 platform engineers in the November cohort. Learn to run calm incidents, write blameless postmortems and ship reliability.",
    secondary: "View the syllabus",
  },
  footer: {
    mesh: "Incident mesh: degraded 0 / healthy 284",
    sla: "Status: all systems operational",
    copy: "Production site reliability training.",
  },
};
