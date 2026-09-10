# Releasing

This repo publishes four independently-versioned things from one monorepo:
`vifrost/laravel` (Packagist) and `@vifrost/client`, `@vifrost/codegen`,
`@vifrost/vue` (npm). Versioning, tagging, changelogs, and publishing are all
automated with [semantic-release](https://semantic-release.gitbook.io/), driven
entirely by commit messages — nobody decides a version number by hand.

## The four release targets

| Target | Registry | Config | Tag format |
|---|---|---|---|
| `vifrost/laravel` | Packagist | `.releaserc.json` (repo root) | `v${version}` |
| `@vifrost/client` | npm | `adapters/vifrost-client/.releaserc.json` | `vifrost-client-v${version}` |
| `@vifrost/codegen` | npm | `adapters/vifrost-codegen/.releaserc.json` | `vifrost-codegen-v${version}` |
| `@vifrost/vue` | npm | `adapters/vifrost-vue/.releaserc.json` | `vifrost-vue-v${version}` |

Each has its own tag namespace specifically so they never collide with each
other (`v0.1.1` already meant "Composer release" before any of the npm
packages existed, and npm doesn't need a git tag at all — `package.json` +
the registry are its source of truth).

They're versioned **independently**, not in lockstep — a change to one
doesn't bump or republish the others. See the commit convention below for how
a commit says which target(s) it affects.

## Commit convention (mandatory, enforced)

Every commit must be `type(scope): subject`, Conventional Commits style, with
**scope required** — enforced by `commitlint.config.js`, checked both locally
(`.husky/commit-msg`, active once you've run `npm install` at the repo root)
and in CI (`.github/workflows/commitlint.yml`, on every PR — this is the check
that actually can't be bypassed).

**Scope must be exactly one of:** `client`, `codegen`, `vue`, `laravel`.

- `feat(client): ...` → minor bump for `@vifrost/client`
- `fix(codegen): ...` → patch bump for `@vifrost/codegen`
- `feat(vue)!: ...` or a `BREAKING CHANGE:` footer → major bump
- `chore(laravel): ...`, `docs(...)`, `refactor(...)`, `test(...)`, etc. →
  never trigger a release, for any target — use `chore` for repo/tooling
  changes that shouldn't publish anything (pick whichever scope is the
  closest fit; it doesn't matter which for a `chore`, since none of them
  release on `chore` regardless of scope).
- A commit scoped to one package **never** affects another's version — see
  "How scoping actually works" below.

A commit with no scope, or a scope outside these four, fails commitlint and
can't be committed (locally) or merged (CI check on the PR).

### Merge strategy matters

This only works because PRs are merged with **"Create a merge commit"**,
never squash or rebase — each individual commit lands on `master` with its
own message, so `commit-analyzer` sees them individually. If squash merging
is ever re-enabled, a squashed PR collapses to one commit using the *PR
title* as its message — meaning the PR title would need to carry the
`type(scope): ...` format instead, and a PR touching two scopes could no
longer be expressed as a single squashed commit. Repo setting: **Settings →
General → Pull Requests** — only "Allow merge commits" should be checked.

## What triggers a release

`.github/workflows/release.yml` runs on `workflow_run`, triggered only when
`.github/workflows/tests.yml` finishes **successfully** on `master` — a
release never ships on top of a failing test suite. Four independent jobs
(one per target) each run `npx semantic-release` scoped to their own
directory and `.releaserc.json`; a job with nothing to release for its scope
is a safe no-op (no version bump, no publish, no tag).

## Publishing mechanism per target

- **`vifrost/laravel`**: `@semantic-release/git` commits `CHANGELOG.md` +
  pushes it, then `@semantic-release/github` creates the tag + a GitHub
  Release. Packagist picks up the new tag via its GitHub webhook (see
  "One-time setup" below) — no npm-side publish step involved.
- **npm packages**: `@semantic-release/npm` bumps `package.json` and runs
  `npm publish`, authenticated via **npm Trusted Publishing (OIDC)** — no
  `NPM_TOKEN` secret stored anywhere. Requires `permissions: id-token: write`
  on the job (already set) and a Trusted Publisher configured per package on
  npmjs.com pointing at this repo + the exact workflow filename
  (`release.yml`).

## Local dev setup

Run `npm install` once at the repo root — this runs the `prepare` script,
which activates the `commit-msg` hook (`.husky/commit-msg`) via husky. This
is per-clone, per-machine: cloning alone doesn't activate it, and there's no
way to force it centrally, which is exactly why the CI commitlint check
exists as the check that actually can't be skipped.

## Why the repo isn't reorganized into `packages/`

Packagist.org (the free/public one) doesn't support installing a package
from a subdirectory of a repo — it expects `composer.json` at the repo root.
The standard fix for that (a git subtree split via `splitsh-lite`, mirroring
`packages/laravel/` out to its own dedicated repo that Packagist tracks
instead) is real infrastructure with real risk to an already-published
package, and was deliberately deferred as a separate project, decoupled from
this release automation. Current layout — `vifrost/laravel` at the repo
root, npm packages under `adapters/` — is a consequence of that constraint,
not an oversight.

## Debugging a failed release run

`gh run list --workflow=release.yml` / `gh run view <id> --log-failed` are
the fastest way to see what actually happened — the GitHub Actions UI works
too, but the `gh` CLI puts the real stderr in front of you immediately
instead of clicking through collapsed log groups. Two bugs already found
this way, both worth checking first if a run fails again:

- **`npm ci` fails with "package.json and package-lock.json are in sync"**:
  someone edited `adapters/*/package.json` (or the root `package.json`)
  without re-running `npm install` in that same directory afterward to
  regenerate the lockfile. Fix: `npm install` there, commit the updated
  lockfile.
- **`git commit` fails inside a release job, with commitlint's own error
  text in the `stderr`**: one of the `.releaserc.json` files' `message`
  template produces a scope commitlint doesn't allow (this happened with a
  hardcoded `chore(release): ...` — `release` isn't one of the four valid
  scopes). The release commit message templates must use one of
  `client`/`codegen`/`vue`/`laravel` like everything else does.
