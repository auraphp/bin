Unix CLI Tools for Aura Project Management
==========================================

These are for making releases, checking documentation, etc. Unlikely to work
on Windows.

You will need to create a directory in your $HOME called '.aura' and place a
file in it called `github-auth` with your Github username and password. This
is so you can interact with the Github API. Then symlink the `console` file to
someplace useful in your $PATH; call it `aura`.

    $ mkdir ~/.aura
    $ echo 'username:password' > ~/.aura/github-auth
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
