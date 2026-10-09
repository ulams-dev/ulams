#!/usr/bin/env bash
# Create a course with a lesson, a text topic and a quiz, publish it and enrol a student.
set -euo pipefail

course=$(ulams courses create --title "Kubernetes 101" --fields id --json | jq -r '.data.id')
lesson=$(ulams lessons create --course-id "$course" --title "Install" --order 1 --fields id --json | jq -r '.data.id')

printf '# Install kubectl\n\nRun `kubectl version --client`.\n' > install.md
ulams topics create-richtext --lesson "$lesson" --title "Install kubectl" --markdown @install.md --json > /dev/null

cat > quiz.yaml <<'YAML'
questions:
  - prompt: Which command prints the client version?
    options:
      - { text: "kubectl version --client", correct: true }
      - { text: "kubectl get version" }
YAML
ulams topics create-quiz --lesson "$lesson" --title "Check" --input @quiz.yaml --json > /dev/null

ulams courses publish "$course" --json > /dev/null
student=$(ulams users list --search student1 --fields id --json | jq -r '.data[0].id')
ulams access grant --course "$course" --user "$student" --json
