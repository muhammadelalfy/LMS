# Git and Agile Workflow

## Working agreement

The team works in small vertical slices. Each backlog item has a clear user story, acceptance criteria, and a demonstrable result. Work is split between backend and frontend ownership, but cross-layer features use coordinated Pull Requests and shared API contracts.

## Branch model

`main` is the protected release branch. `backend` and `frontend` are integration branches. A feature branch must be created from the integration branch that owns most of the change:

```text
main
├── backend
│   ├── feature/backend-exam-grading
│   └── bugfix/backend-pdf-rtl
└── frontend
    ├── feature/frontend-exam-review
    └── chore/frontend-pdf-snapshot
```

A cross-layer feature may use paired branches with the same work-item identifier, for example `feature/backend-exam-review-142` and `feature/frontend-exam-review-142`. The Pull Requests should reference one another and share the same acceptance criteria.

## Pull Request lifecycle

The author creates a focused branch, commits in small logical units, and opens a Pull Request against `backend` or `frontend`. Once the integration branch is stable, its maintainer opens a Pull Request into `main`. Every Pull Request must include the problem statement, acceptance criteria, implementation summary, test commands, migration or deployment notes, and screenshots or exported-PDF evidence for visual changes.

CI is required on every push and Pull Request. Reviewers check correctness, authorization boundaries, KISS/DRY design, Arabic RTL behavior, accessibility, data migration safety, and rollback impact. A Pull Request is merged only after CI passes and the required reviewer approves it. Squash merge is preferred for feature branches; the source branch is deleted after merge.

## Suggested sprint cadence

During sprint planning, select a small set of stories and assign each a branch name and acceptance criteria. During the sprint, keep Pull Requests small and open early. At review, demonstrate the working flow and attach evidence. At the sprint review, verify the completed acceptance criteria. At the retrospective, record process improvements as repository issues or backlog items.

## Safe commands

```bash
git switch main
git pull --ff-only
git switch -c feature/frontend-exam-review
# implement and test
git add .
git commit -m "feat(frontend): add exam review preview"
git push -u origin feature/frontend-exam-review
```

Never use `git push --force` on `main`, `backend`, or `frontend`. Database migrations must be reviewed with their rollback and data-preservation implications. A broken integration branch should be repaired through a new Pull Request rather than rewriting shared history.

## GitHub access and branch protection

The project has a configured GitHub connector in the Manus session, but this sandbox’s Git CLI is not authenticated through that connector: `gh` reports that no CLI login is present and `git push` cannot prompt for credentials. The local repository therefore contains the requested `github` remote and local branches, but pushing and opening Pull Requests must be completed through the Management UI’s GitHub export flow or from a separately authenticated GitHub environment. No password or personal access token should be committed or sent through chat.

After the repository is pushed, configure branch protection in GitHub under **Settings → Branches → Add branch ruleset**. Protect `main`, `backend`, and `frontend` with required Pull Requests, at least one reviewer approval, required CI checks (`Frontend checks` and `Laravel checks`), dismissal of stale approvals after new commits, conversation resolution, no force-push, and no branch deletion. Allow direct pushes only for repository administrators when an emergency recovery is documented.
