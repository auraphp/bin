# Unix CLI Tools for Aura Project Management

These are for making releases, checking documentation, etc. Unlikely to work
on Windows.

First, create a Github application token at
<https://github.com/settings/applications>. This will take the place of a
password.

Next, copy `_env` to `.env`. Add your usernames and tokens so you can interact
with various APIs. (You should leave empty, the "optional" values for now.)

Finally, symlink the `cli/console.php` file to someplace useful in your $PATH;
call it `aura`.

You should now be able to issue commands; e.g., `aura repos`:

    $ aura repos
    Aura.Autoload
    Aura.Cli
    ...
    system
    $

Note that this is a not a "regular" repo for Aura; it's for internal project
work, not for general use outside the project.

## Releases

The release process is not as smooth or as unified as I'd like. You'll need to
install PHPUnit and PHPDocumentor globally for the release process to work.  In
the past I've had the release tool automatically Tweet and queue an email to the
list, but I have turned those off for now. That should make for an easier
release experience for everyone else.

There is a different release process for each major version of Aura. So, if
you're making a 2.x release, the release command is `aura release2`.

### Making A 2.x Release

1. Go to the directory of the package you're going to release; e.g., `cd /path/to/Aura.Foo`.

2. Make sure you are on the right branch (`git checkout 2.x`).

3. Do a dry-run/preflight by issuing `aura release2`.  This should tell you
   everything that's wrong with the package; make corrections, commit them,
   and repeat more dry-runs/preflights until everything works.

4. Look at the tag list for previous versions (`git tag -l`) and pick an
   appropriate next-version number for the release (we'll call it `{$VERSION}`).
   If there are only bugfixes, increment the "patch" number; if there are any
   feature additions of any kind, increment the "minor" number.

5. To actually make the release, issue `aura release2 {$VERSION}`. That will do
   the dry-run/preflight, and then actually use the GitHub API to tag a release.

6. After it's done, send an email to the list and tweet about it.
