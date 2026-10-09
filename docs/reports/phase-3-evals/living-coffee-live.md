# Living Course eval: coffee

Driver: anthropic · 68 s · cost $0.0934 (estimate $0.1769)
Items: {"citation_remap":1,"no_change":11,"update":7,"uncovered":1,"remove":4}

| Check | Result | Detail |
|---|---|---|
| detection.revision | pass | {"changed":4,"moved":0,"removed":1,"added":1,"trivial":0,"minor":1,"substantive":3,"total":6} |
| analysis.completed | pass | started / ready |
| impact.expected_elements_decided | pass | 4 sections |
| no_update_for_trivial_changes | pass | 0 item(s) |
| facts.new_present | pass | all found |
| facts.old_absent | pass | none left |
| citations.resolve_to_new_revision | pass |  |
| no_raw_markup | pass |  |
| reasons.name_the_section | pass | 0 of 22 reasons do not |
| answers.change_detected | pass | 3 of 3 expected corrections |
| cache.read_after_first_group | pass | 8144,8144,8144,8144 |

## Calls

| Task | Model | Status | Attempt | In | Out | Cache read | Cache write | Cost USD | ms |
|---|---|---|---|---|---|---|---|---|---|
| update | claude-sonnet-5-5 | ok | 1 | 431 | 322 | 2914 | 5230 | 0.0256 | 4936 |
| update | claude-sonnet-5-5 | ok | 1 | 1903 | 1266 | 8144 | 0 | 0.0181 | 15141 |
| grounding | claude-haiku-5-5 | ok | 1 | 385 | 12 | 644 | 0 | 0.0001 | 3297 |
| update | claude-sonnet-5-5 | ok | 1 | 1937 | 1288 | 8144 | 0 | 0.0184 | 12073 |
| grounding | claude-haiku-5-5 | ok | 1 | 348 | 12 | 644 | 0 | 0.0000 | 1965 |
| update | claude-sonnet-5-5 | ok | 1 | 1895 | 1374 | 8144 | 0 | 0.0192 | 12733 |
| grounding | claude-haiku-5-5 | ok | 1 | 394 | 40 | 644 | 0 | 0.0001 | 1678 |
| update | claude-sonnet-5-5 | ok | 1 | 1919 | 650 | 8144 | 0 | 0.0120 | 5645 |
