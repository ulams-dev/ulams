# Git Basics for Course Authors

How to keep course material under version control with Git.

## Repositories

### Creating a repository

A repository is a folder whose history Git records. Create one with `git init` inside the folder.
Git stores its data in a hidden `.git` directory; do not edit it by hand.

```bash
mkdir my-course
cd my-course
git init
```

### Cloning

To work on an existing repository, copy it with `git clone` and the repository URL. The clone has
the full history and is linked to the original as the remote called `origin`.

## Recording changes

### Staging and committing

Git records changes in two steps. First you stage the files you want to record with `git add`, then
you record them with `git commit`. A commit is a snapshot of the staged files with a message that
explains why they changed.

```bash
git add lessons/intro.md
git commit -m "Add the introduction lesson"
```

Write commit messages in the imperative mood ("Add", "Fix") and keep the first line under about 50
characters.

### Checking the state

`git status` lists changed, staged and untracked files. `git diff` shows the changes that are not
staged yet, and `git diff --staged` shows what the next commit will contain.

## Branches

### Working on a branch

A branch is a movable pointer to a commit. Create one with `git switch -c` and a name, work on it,
and commit as usual. The main branch stays untouched until you merge.

```bash
git switch -c lesson-2
```

### Merging

When the work is ready, switch back to the main branch and run `git merge` with the branch name.
If both branches changed the same lines, Git stops with a merge conflict: edit the marked lines,
stage the file and commit to finish the merge.
