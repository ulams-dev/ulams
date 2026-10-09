# Rules of engagement

Every team that carries a pager needs the same two agreements before the first page: **how bad is bad** (severity) and **who does what** (roles). Settle them in daylight, write them down, and you will not argue about them at 3 a.m.

## Severity matrix

| Level | Definition | Examples | Who is paged | Update cadence |
|---|---|---|---|---|
| **SEV1** | Customer-facing outage or data loss | checkout down; login failing for >10 % of users; data corruption | IC + primary on-call, 24/7; exec on-call informed | every 30 min |
| **SEV2** | Major degradation, no full outage | p99 latency 5× normal; one region down with failover; payments delayed | primary on-call, 24/7 | every 60 min |
| **SEV3** | Minor impact, a workaround exists | CSV export failing; search slow for one tenant | business hours | daily |
| **SEV4** | Cosmetic, no user impact | typo in an email; a noisy but harmless alert | backlog | — |

> **Declare high, downgrade fast.**
> Declaring a SEV1 that turns out to be a SEV2 costs you one extra message. Declaring a SEV2 that is really a SEV1 costs you thirty minutes of the wrong people asleep.

## Roles

| Role | Owns | Does **not** |
|---|---|---|
| **Incident commander (IC)** | decisions, priorities, assigning roles, the clock, declaring mitigation | debug, run commands, write code |
| **Communications lead** | status page, stakeholder updates, support team, exec questions | speculate about the cause in public |
| **Operations lead** | investigation and mitigation; directs responders | talk to executives |
| **Scribe** | the timeline: timestamps, decisions, hypotheses, who is doing what | edit the history later |

In a small team one person may hold two roles, but **never IC and ops lead together**: the person with their hands on the keyboard cannot also watch the whole board.

## The incident lifecycle

```
detect → triage → declare → mitigate → resolve → learn
           │         │          │
           │         │          └─ roll back, fail over, flag off, shed load
           │         └─ severity, roles, channel, first update
           └─ is it real? who is affected? since when? what changed?
```

## Ground rules on the bridge

1. **Address by name and role.** "Aiko, ops: check the deploy log." Not "can someone…".
2. **Close the loop.** Every request gets an answer: "Done", "Can't, because…", or "Need 5 more minutes".
3. **One conversation.** Side investigations go to a thread and report back.
4. **Facts, not feelings.** "Error rate 18 % since 03:07" beats "it's really bad".
5. **No blame, now or later.** We look for conditions, not culprits.
6. **The IC can always reset.** "Hold. Let's recap what we know."

## Handover

Incidents that last longer than four hours change IC. The outgoing IC writes a handover note (what happened, current state, open risks, owners) and stays on the bridge for 15 minutes. Tired people make poor decisions: rotating is not weakness.
