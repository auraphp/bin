# Releasing an Aura package

**Releasing a package that has opted in:** go to its Actions tab, pick
**Release**, choose the branch and type the version. Or from a terminal:

    gh workflow run release.yml --ref 6.x -f version=6.0.0

A shared GitHub Actions workflow does the rest. There is nothing to install and
no token to configure.

**Do not tag by hand.** The workflow creates the tag, and only after every
check has passed. This is the whole point of the design — see
[Why the workflow creates the tag](#why-the-workflow-creates-the-tag).

Two release paths exist in this repo:

- **`.github/workflows/release.yml`** — a reusable workflow that packages call.
  Nothing runs on your machine.
- **`aura release1` / `release2` / `release3`** (legacy) — the original PHP
  commands under `src/Command/`. Still present and unchanged, but no longer the
  way to release.

## Opting a package in

Copy [`templates/package-release.yml`](templates/package-release.yml) to
`.github/workflows/release.yml` in the package and commit it. That file is five
meaningful lines; everything else lives here, so fixing the release process for
every package is a change to this repo alone.

All of the workflow inputs are optional:

| Input | Default | Use it when |
| --- | --- | --- |
| `php-version` | `8.4` | the package needs a different version to test on |
| `extensions` | none | the tests need extra PHP extensions |
| `test-command` | `./vendor/bin/phpunit` | the package runs tests differently |
| `run-tests` | `true` | set `false` to publish without the test gate |
| `changelog-file` | `CHANGELOG.md`, then `CHANGES.md` | the change log is somewhere else |

### What the workflow does

1. Rejects the version unless it looks like `1.2.3`, optionally suffixed with
   `-dev`, `-alpha1`, `-beta1` or `-RC1`. A suffixed version is published as a
   pre-release, so it does not become "latest" and Composer treats it as
   unstable.
2. Fails if that version is already tagged or already released.
3. Finds the change log, and **fails unless its top version heading matches the
   version you asked for** — so a release can never go out against the wrong
   notes. It also fails if that heading is still marked `(unreleased)`.

   Pre-releases are looser on both counts, so that alphas and betas do not each
   need a change log section of their own: `6.0.0-alpha1` is happy with a
   `## 6.0.0` heading, and with that heading still marked `(unreleased)` —
   which is precisely what it is while you are cutting alphas of it. Only the
   final `6.0.0` demands an exact heading with the marker removed.
4. Takes the release notes from that section of the change log. Headings at
   either `#` or `##` level work, and `###` subheadings inside a section are
   preserved.
5. Installs dependencies, validates `composer.json`, and runs the test suite
   once as a sanity gate. This is not a substitute for the CI matrix, which has
   already run on this commit; it is there so a broken tree cannot become a tag.
6. **Only now** creates the tag and the GitHub release, pointing the tag at the
   exact commit that was tested rather than at the branch name — so the tag is
   right even if someone pushed to the branch mid-run. Packagist picks the new
   tag up from its own webhook.

If any step fails, no tag is created, so nothing reaches Packagist and there is
nothing to clean up. Fix the problem and run the workflow again.

## Why the workflow creates the tag

Packagist builds package versions from **git tags**. It does not look at GitHub
releases at all. So the moment a tag reaches GitHub, that version is effectively
published: `composer require aura/html:6.0.0` starts working, whatever else may
or may not have happened.

That rules out the obvious design of "push a tag, let a workflow react to it".
By the time such a workflow runs, the tag already exists — its checks can fail
loudly while Composer happily serves the version they were meant to guard. The
only remedy is deleting a tag that people may already have in a lock file,
which is worse than the problem.

Inverting it fixes this. You ask for a version; the checks run; the tag is
created last, or not at all. A failed release leaves the repository exactly as
it was.

The cost is that releasing is a dispatch rather than a `git push`, and the tags
are lightweight rather than annotated — which matches every existing Aura tag
anyway.

## Release notes come from the change log, never from GitHub

The workflow does not use `--generate-notes`, and there is no fallback to it.
Notes are read from the package's own change log or the release does not
happen. If the section is missing or empty, the run fails and tells you so,
rather than publishing an auto-generated commit dump.

## What changed from the legacy commands

| Legacy | Now |
| --- | --- |
| GitHub token in `.env`, `milo/github-api` | `gh auth login`, `gh` CLI |
| `phpdoc` XML `@package` tag validation | dropped — phpDocumentor's XML template and the `@package` convention are both long gone |
| `.travis.yml` required | GitHub Actions workflow expected; `.travis.yml` warns |
| Rewrote `composer.json` mid-release, then failed its own clean-tree check | leaves `composer.json` alone; `composer validate` is the gate |
| Only understood `CHANGELOG.md` and `## x.y.z` headings | also reads `CHANGES.md` and `# x.y.z` headings |
| Ran on your machine, so every maintainer needed PHPUnit and PHPDocumentor installed globally | runs on GitHub; nothing to install |
| A tag was pushed before the checks ran | the tag is created after they pass, or not at all |
| Tweet + mailing-list mail via IronMQ / Swiftmailer | dropped; both integrations were already commented out |

The legacy commands still work if you need something the workflow does not
cover (`aura repos`, `aura packagist`, `aura packages-json`, `aura travis`,
`aura create-changelog`, `aura readme`). Note that their dependencies —
`swiftmailer/swiftmailer`, `ricardoper/twitteroauth`, `monolog` 1.x, `aura/di`
2.x — are all abandoned or EOL, so treat that side of the repo as frozen.

## Rollout

Adopt the workflow as each package next comes up for release, rather than all
at once. Suggested first candidates: **Aura.Html** or **Aura.Input** — small,
single-job CI, change logs already in the right shape. **Aura.SqlQuery** is a
good second test because its change log currently heads with
`## 6.0.0 (unreleased)`, which the workflow correctly refuses to release.

To rehearse without publishing anything, run the workflow with a version whose
change log entry does not exist yet: it will fail at the change-log check, well
before the tag step, and you can confirm from the run summary that no tag was
created.

To rehearse the whole thing *including* the publish, cut a pre-release:
dispatch `6.0.0-alpha1` against a branch whose change log heads with
`## 6.0.0`. It goes out as a GitHub pre-release, so it does not become
"latest", and Composer treats it as unstable — you get a real end-to-end run
without committing to the final version.

## Related: producer/producer

Several packages — `Aura.Di` among them — already carry
[`producer/producer`](https://github.com/pmjones/producer) as a dev dependency.
It is Paul M. Jones' general-purpose successor to this repo's release commands
and covers much the same ground (`producer validate`, `producer release`).

Producer is a reasonable alternative, but it is another PHP package to keep
installed and current — the same shape of dependency that aged this repo's
commands out. The workflow has nothing to rot: it is GitHub releasing GitHub's
own artifacts, and the checks stay Aura-specific and easy to adjust here.

## Ideas not implemented here

- A `changelog` check in the per-package CI, so a PR that changes `src/` without
  touching the change log is flagged at review time rather than at release time.
- A `composer.json` metadata check in the workflow, enforcing the `name`,
  `type`, `license`, `homepage` and `authors` conventions the legacy command
  used to rewrite by hand.
