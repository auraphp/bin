Unix CLI Tools for Aura Project Management
==========================================

These are for making releases, checking documentation, etc. Unlikely to work
on Windows.

First, create a Github application token at
<https://github.com/settings/applications>. This will take the place of a
password.

Next, copy `_env` to `.env`. Add your usernames and tokens so you can interact
with various APIs.

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
