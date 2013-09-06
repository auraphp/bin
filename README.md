Unix CLI Tools for Aura Project Management
==========================================

These are for making releases, checking documentation, etc. Unlikely to work
on Windows.

First, create a Github application token at
<https://github.com/settings/applications>. This will take the place of a
password.

Next, create a file in your $HOME called `.aurarc`. Add your usernames and
tokens so you can interact with various APIs.

Finally, symlink the `console` file to someplace useful in your $PATH; call it
`aura`.

For example:

    $ touch ~/.aurarc
    $ echo "
    github_user = username
    github_token = token
    travis_user = ...
    travis_token = ...
    packagist_user = ...
    packagist_token =...
    " >> ~/.aurarc
    $ ln -s /path/to/auraphp/bin/console /usr/local/bin/aura

You should now be able to issue commands; e.g., `aura repos`:

    $ aura repos
    Aura.Autoload
    Aura.Cli
    ...
    system
    $

Note that this is a not a "regular" repo for Aura; it's for internal project
work, not for general use outside the project.
