# Living Course eval: git

Driver: anthropic · 47.4 s · cost $0.0721 (estimate $0.1395)
Items: {"no_change":7,"update":8,"uncovered":1}

| Check | Result | Detail |
|---|---|---|
| detection.revision | pass | {"changed":3,"moved":0,"removed":1,"added":1,"trivial":0,"minor":1,"substantive":2,"total":5} |
| analysis.completed | pass | started / ready |
| impact.expected_elements_decided | pass | 3 sections |
| no_update_for_trivial_changes | pass | 0 item(s) |
| facts.new_present | pass | all found |
| facts.old_absent | pass | none left |
| citations.resolve_to_new_revision | pass |  |
| no_raw_markup | pass |  |
| reasons.name_the_section | pass | 0 of 15 reasons do not |
| answers.change_detected | pass | 2 of 2 expected corrections |
| cache.read_after_first_group | pass | 6666,6666,6666 |

## Calls

| Task | Model | Status | Attempt | In | Out | Cache read | Cache write | Cost USD | ms |
|---|---|---|---|---|---|---|---|---|---|
| update | claude-sonnet-5-5 | ok | 1 | 262 | 191 | 2914 | 3752 | 0.0180 | 6013 |
| update | claude-sonnet-5-5 | ok | 1 | 1429 | 1236 | 6666 | 0 | 0.0166 | 7552 |
| grounding | claude-haiku-5-5 | ok | 1 | 312 | 12 | 644 | 0 | 0.0000 | 1523 |
| update | claude-sonnet-5-5 | ok | 1 | 1885 | 928 | 6666 | 0 | 0.0144 | 8016 |
| grounding | claude-haiku-5-5 | ok | 1 | 317 | 12 | 644 | 0 | 0.0000 | 3382 |
| update | claude-sonnet-5-5 | ok | 1 | 1894 | 1786 | 6666 | 0 | 0.0230 | 11444 |
| grounding | claude-haiku-5-5 | ok | 1 | 398 | 12 | 644 | 0 | 0.0001 | 1883 |
