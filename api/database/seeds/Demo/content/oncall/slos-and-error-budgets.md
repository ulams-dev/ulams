# SLOs and error budgets

An alert should fire when **users are hurting**, not when a CPU is busy. Service level objectives turn "users are hurting" into a number you can page on.

## Vocabulary

| Term | Meaning | Example |
|---|---|---|
| **SLI** (indicator) | a measurement of user experience, as a ratio of good events | share of checkout requests that return 2xx in < 800 ms |
| **SLO** (objective) | the target for the SLI over a window | 99.9 % over 30 rolling days |
| **SLA** (agreement) | a contract with consequences, looser than the SLO | 99.5 % or service credits |
| **Error budget** | the amount of unreliability the SLO allows | 0.1 % of requests, or 43.2 minutes |

## The budget

$$
\text{budget} = (1 - \text{SLO}) \times \text{window}
$$

**Worked example.** A 99.9 % SLO over 30 days:

$$
(1 - 0.999) \times 30 \times 24 \times 60\ \text{min} = 0.001 \times 43\,200\ \text{min} = 43.2\ \text{min}
$$

So the service may be fully down for **43.2 minutes a month**, or serve 1 % errors for 72 hours, or any mix of the two, and still meet its objective.

| SLO | Budget per 30 days | Per week |
|---|---|---|
| 99 % | 7 h 12 min | 1 h 41 min |
| 99.5 % | 3 h 36 min | 50 min |
| 99.9 % | 43.2 min | 10.1 min |
| 99.95 % | 21.6 min | 5.0 min |
| 99.99 % | 4.3 min | 1.0 min |

> **Each nine costs ten times more.**
> Moving from 99.9 % to 99.99 % means surviving a bad deploy in under 5 minutes, every time. Choose the lowest SLO your users are happy with, not the highest one you can imagine.

## Burn rate: alerting on the budget

The **burn rate** is how fast you are spending the budget compared with spending it evenly over the window:

$$
\text{burn rate} = \frac{\text{observed error rate}}{1 - \text{SLO}}
$$

At a burn rate of 1 the budget lasts exactly 30 days. At 14.4 it is gone in 2 days. The Google SRE workbook recommends **multi-window, multi-burn-rate** alerts:

| Severity | Burn rate | Long window | Short window | Budget spent when it fires |
|---|---|---|---|---|
| Page | 14.4 | 1 h | 5 min | 2 % |
| Page | 6 | 6 h | 30 min | 5 % |
| Ticket | 1 | 3 days | 6 h | 10 % |

The short window makes the alert stop quickly once you have fixed the problem; the long window stops it from firing on a single noisy minute.

```yaml
# Prometheus: page when checkout burns the 99.9 % budget 14.4× too fast
- alert: CheckoutErrorBudgetFastBurn
  expr: |
    (sum(rate(http_requests_total{job="checkout",code=~"5.."}[1h]))
      / sum(rate(http_requests_total{job="checkout"}[1h]))) > (14.4 * 0.001)
    and
    (sum(rate(http_requests_total{job="checkout",code=~"5.."}[5m]))
      / sum(rate(http_requests_total{job="checkout"}[5m]))) > (14.4 * 0.001)
  labels: { severity: page }
```

## What the budget is for

The budget is not a target to stay far away from: it is permission to take risks. While budget remains, ship features. When the budget is spent, the team's agreed **error budget policy** applies, for example: freeze risky launches, put reliability work first in the next sprint, and hold a review with product.

## Check yourself

- Your SLO is 99.95 % over 30 days. How many minutes of full outage can you afford? *(21.6)*
- Checkout serves 2 % errors. With a 99.9 % SLO, what is the burn rate? *(20: the monthly budget is gone in 36 hours)*
