Your course follows the repository, the Drive doc or the Notion page it was built from. That's what I shipped this week 🙂

Until now Living Course watched an uploaded file or a few web pages. Now it can watch:

- **any Git server** that speaks https (GitHub, GitLab, Gitea, your own), read with the plain `git` command
- **a Google Drive** file or folder: Google Docs, text, Word and PDF files
- **a Notion page**, optionally with the pages under it

What happens when the source changes:

- the connector notices (a commit, a new Drive version, a newer Notion edit) and stores a new revision
- ULAMS shows the diff, and finds the lessons and quiz questions that cite the changed passage
- it proposes new versions, each one citing the passage it came from. AI proposes, you approve. Nothing in the course changes until you apply

I ran this on a small on-call runbook. One commit changed "escalate after 10 minutes" to 15, and added a section. Result: 5 course elements to review (2 text blocks, 2 quiz questions, 1 objective), 2 answers flagged as changed, and the new section offered as an uncovered one. One click on "Suggest a lesson" gave a cited draft lesson. The whole check took about half a second on my laptop. The full run, with the real requests and numbers, is here: [/creators/git-sync-worked-example/](/creators/git-sync-worked-example/)

What I care about most is safety, because you point this at servers you don't control:

- git runs sandboxed: https only, no redirects, no hooks, no submodules, no LFS, a size and time cap, an empty environment, and a temporary folder that is always removed
- the address goes through the same SSRF checks as everything else, and the checked address is pinned
- your token goes into a request header, never into a URL or a log, and git's own error messages are never shown
- for Drive and Notion, the sign-in tokens stay on the server. The browser only gets a one-time grant

What's real:

- the Git connector is tested against real git repositories and a recording stand-in for the binary
- Drive and Notion are tested only against scripted servers. I haven't called the live Google or Notion APIs yet, so treat them as ready to try, not proven
- all three are off by default. An admin turns them on with `LIVING_COURSE_CONNECTORS`, the image needs `git`, and Drive and Notion need an OAuth app that your installation owns. Drive and Notion live inside the living-course package
- in my run the AI was the built-in simulation, so the cost of $0.02 is a calculation, not a bill, and it says nothing about real model quality

How to switch them on: [/admin/living-course/](/admin/living-course/)

Which source would you point your course at first: the repo, a Drive doc or Notion?
