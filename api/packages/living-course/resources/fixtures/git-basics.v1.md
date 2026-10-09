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
stage the file and commit to finish the merge. Mispelled branch names are the usual reason a merge
does not find what you asked for.

## Sharing work

### Pulling changes

By default `git pull` fetches the remote branch and then merges it into your current branch, which
can create a merge commit. Run `git pull --rebase` to replay your own commits on top of the fetched
ones instead, which keeps the history in a straight line.

### Pushing

Send your commits to the remote with `git push`. The first time you push a new branch, name the
remote and the branch: `git push -u origin lesson-2`. Pushing is refused when the remote has commits
you do not have yet; pull first and push again.

## Saving work in progress

### Stashing

`git stash` puts your uncommitted changes aside so you can switch branches with a clean working
folder. `git stash pop` brings them back and removes the stash entry. List the entries with
`git stash list`.
