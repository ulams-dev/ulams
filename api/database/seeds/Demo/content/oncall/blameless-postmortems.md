# Blameless postmortems

An incident is an unplanned investment in your system. The postmortem is how you collect the return.

![Game-day outage timeline]({{asset:postmortem-timeline.png}})

## Why blameless

People do what makes sense to them with the information, tools and pressure they have at that moment. If a single person *could* take checkout down with one deploy, the system allowed it. Name the person, and next time people hide what they did; describe the conditions, and next time the system catches it.

> **Blameless is not "no accountability".**
> We hold people accountable for **taking part honestly** and for **finishing the action items**, not for being the last hand to touch a fragile system.

## Template

| Section | What goes in it |
|---|---|
| **Summary** | two or three sentences a customer could understand |
| **Impact** | who, how many, how long, what it cost (orders, SLO budget, support tickets) |
| **Timeline** | UTC timestamps from the first trigger to the all-clear, taken from the scribe's notes |
| **Contributing factors** | technical, process and organisational conditions, without names |
| **What went well** | so you keep doing it |
| **Where we got lucky** | the near-misses you must not count on next time |
| **Action items** | each with an owner, a due date and a type: *prevent*, *detect* or *mitigate* |

## The five whys, used carefully

Ask "why?" until you reach something you can change.

1. **Why** did checkout fail? The database connection pool was exhausted.
2. **Why** was the pool exhausted? Release v2.31 ran one query per cart item: an N+1.
3. **Why** did the N+1 reach production? The load test uses carts with one item.
4. **Why** does the load test use one-item carts? Its fixtures were written in 2021 and never revisited.
5. **Why** were they never revisited? Nobody owns the load-test suite.

The five whys suggests a single chain. Real incidents have **several contributing factors** that only cause harm together: the N+1 query **and** one-item test fixtures **and** an alert threshold that waited five minutes **and** a rollback that took six. Fix more than one link.

## Contributing factors vs root cause

| "Root cause" thinking | "Contributing factors" thinking |
|---|---|
| one cause, found by digging | several conditions that interact |
| ends at a person or a line of code | ends at things the organisation can change |
| "Ben deployed a bad query" | "an N+1 passed review and load tests, alerts were slow, rollback was manual" |
| one action item | several smaller, owned action items |

## Good action items

- **Specific:** "Add a 20-item cart fixture to the checkout load test", not "improve testing".
- **Owned:** one named team or person.
- **Dated:** due within a sprint or two, tracked like any other work.
- **Balanced:** at least one *detect* or *mitigate* item, not only *prevent*.

## The review meeting

Hold it within five working days, while memories are fresh. Invite everyone who responded, plus the owners of the systems involved. The facilitator is not the IC of the incident. Read the timeline together, agree on contributing factors, then on action items. Publish the document to the whole engineering organisation: the next team will thank you.
