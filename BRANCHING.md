# GitHub Branching And Safe Deploy Flow

Use this branch structure so production stays recoverable when a deployment goes wrong.

## Branch Roles

- `develop`: integration branch for everyday feature work
- `staging`: release branch for final validation before production
- `main`: production branch only

## Branch Naming

Create short-lived working branches from `develop`:

- `feature/<name>`
- `fix/<name>`
- `chore/<name>`
- `docs/<name>`
- `refactor/<name>`
- `test/<name>`

For urgent production issues, branch from `main`:

- `hotfix/<name>`

## Safe Merge Path

Normal release flow:

1. `feature/*` or `fix/*` -> `develop`
2. `develop` -> `staging`
3. `staging` -> `main`

Emergency flow:

1. `hotfix/*` -> `main`
2. Merge the same hotfix back into `develop`
3. Promote it forward again through `staging`

The repository now includes a GitHub Actions workflow named `branch-policy` that enforces this merge path for pull requests into `develop`, `staging`, and `main`.

## GitHub Branch Protection Settings

In GitHub:

1. Open `Settings` -> `Branches`
2. Add branch protection rules for `develop`, `staging`, and `main`

Recommended settings for `main`:

- require a pull request before merging
- require at least 1 approval
- dismiss stale approvals when new commits are pushed
- require status checks to pass before merging
- require these checks:
  - `branch-policy`
  - `linter`
  - `tests`
- block force pushes
- block branch deletion

Recommended settings for `staging`:

- require a pull request before merging
- require status checks:
  - `branch-policy`
  - `linter`
  - `tests`
- block force pushes

Recommended settings for `develop`:

- require a pull request before merging
- require status checks:
  - `branch-policy`
  - `linter`
  - `tests`

## Deployment Safety

- Configure Render, Railway, or any production host to auto-deploy from `main` only
- If you want preview testing, connect a non-production environment to `staging`
- Do not deploy feature branches directly to production

## Rollback Procedure

If a production deployment fails:

1. Revert the bad merge commit on `main`
2. Push the revert through a pull request
3. Redeploy production from the reverted `main`
4. Fix the issue in a new branch
5. Promote the fix back through `develop` -> `staging` -> `main`

Example commands:

```bash
git checkout main
git pull origin main
git checkout -b hotfix/revert-bad-deploy
git revert <merge-commit-sha>
git push -u origin hotfix/revert-bad-deploy
```

Then open a pull request into `main`.
