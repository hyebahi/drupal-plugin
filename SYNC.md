# Repo Sync Guide

This project has two remotes:

| Folder | Remote | Purpose |
|---|---|---|
| `trustarc/` | GitHub (`github.com/hyebahi-trustarc/drupal-plugin`) | Development working copy |
| `trustarc-upstream/` | GitLab (`git.drupalcode.org/project/trustarc`) | Official Drupal.org release |

Changes are made in `trustarc/`, tested locally, then synced to `trustarc-upstream/` before releasing.

---

## Workflow

### 1. Make and test changes in `trustarc/`

```bash
make all-checks
```

All checks must pass before syncing upstream.

### 2. Commit to GitHub

```bash
git add trustarc/<changed-files>
git commit -m "your message"
git push origin <branch>
```

### 3. Copy changed files to `trustarc-upstream/`

```bash
cp trustarc/<changed-file> trustarc-upstream/<changed-file>
```

If a file was deleted:

```bash
rm trustarc-upstream/<deleted-file>
```

### 4. Create a branch in `trustarc-upstream/` based on current `main`

Always branch from the latest `main` to avoid merge conflicts:

```bash
cd trustarc-upstream
git fetch https://hyebahi:<token>@git.drupalcode.org/project/trustarc.git main
git checkout -b your-branch-name FETCH_HEAD
```

### 5. Commit — no co-author line

The upstream commit must be under your name only, no tooling attribution:

```bash
git add <changed-files>
git commit -m "your message"
```

### 6. Push and open a Merge Request

```bash
git push https://hyebahi:<token>@git.drupalcode.org/project/trustarc.git your-branch-name
```

Then open the MR via the link printed by git, or via the GitLab API:

```bash
curl --request POST "https://git.drupalcode.org/api/v4/projects/173971/merge_requests" \
  --header "PRIVATE-TOKEN: <token>" \
  --header "Content-Type: application/json" \
  --data '{"source_branch": "your-branch-name", "target_branch": "main", "title": "your title"}'
```

### 7. Wait for the pipeline to pass, then merge

```bash
curl --request PUT "https://git.drupalcode.org/api/v4/projects/173971/merge_requests/<iid>/merge" \
  --header "PRIVATE-TOKEN: <token>" \
  --header "Content-Type: application/json" \
  --data '{"squash": true}'
```

---

## Releasing a new version

After merging to `main`, tag the release:

```bash
curl --request POST "https://git.drupalcode.org/api/v4/projects/173971/repository/tags" \
  --header "PRIVATE-TOKEN: <token>" \
  --header "Content-Type: application/json" \
  --data '{"tag_name": "1.x.y", "ref": "main", "message": "Release 1.x.y"}'
```

Drupal.org picks up the tag automatically and packages the release.

---

## GitLab token

Generate a personal access token at `https://git.drupalcode.org/-/user_settings/personal_access_tokens` with `write_repository` scope.
