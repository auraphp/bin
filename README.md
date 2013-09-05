Unix CLI Tools for Aura Project Management
==========================================

These are for making releases, checking documentation, etc. Unlikely to work
on Windows.

Create a file in your $HOME called `.aurarc`; add your Github username and
password so you can interact with the Github API. Then symlink the `console`
file to someplace useful in your $PATH; call it `aura`. For example:

    $ touch ~/.aurarc
    $ echo "github_user = username
    github_pass = password
    " >> ~/.aurarc
    $ ln -s /path/to/auraphp/bin/console /usr/local/bin/aura

You should now be able to issue commands; e.g., `aura repos`:

    $ aura repos
    Aura.Autoload
    Aura.Cli
    ...
    system
    $

This is a not a "regular" repo for Aura; it's for internal project work, not
for general use outside the project.
