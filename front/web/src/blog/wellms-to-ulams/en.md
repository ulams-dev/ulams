For years at ESCOLA S.A. I built Wellms, an open source headless LMS on Laravel. Courses, lessons, progress, SCORM, H5P, the whole modern e-learning machine.

When I left, I knew I wasn't done playing with this topic. So I did what builders do best: I forked Wellms and started adding things, and I rebuilt the architecture as a monorepo.

That's how ULAMS ([ulams.app](https://ulams.app/)) is happening:

- a course built from documentation your team already has
- every generated sentence points at a source passage
- when the docs change, the course gets an update proposal
- AI only proposes, the author decides

I'm doing it after hours because I genuinely enjoy it. And because I want to explore generative UI (AG-UI, A2UI) on a live product: an interface the model proposes and the author approves. Over the next posts I'll show one example end to end: runbook → course → update. Real product, no slides.
