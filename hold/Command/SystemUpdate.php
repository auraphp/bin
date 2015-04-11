<?php
namespace Aura\Bin\Command;

/**
 * Updates the git repos in the system/package directory.
 * Only invoke this from a system directory.
 */
class SystemUpdate extends AbstractCommand
{
    public function __invoke()
    {
        if (! ini_get('allow_url_fopen')) {
            $this->stdio->outln("Cannot update when 'allow_url_fopen' is turned off.");
            exit(1);
        }

        $dir = getcwd() . DIRECTORY_SEPARATOR . 'package';
        if (! is_dir($dir)) {
            $this->stdio->outln("No package directory; is this an Aura system?");
            exit(1);
        }

        // pull changes the system as a whole
        passthru('git pull');

        // update the library packages
        $repos = $this->github->getRepos();
        foreach ($repos as $repo) {

            // only use 'Aura.Package' repositories as packages
            if (! preg_match('/Aura\.[A-Z0-9_]+/', $repo->name)) {
                continue;
            }

            // does the package exist locally ?
            $sub = $dir . DIRECTORY_SEPARATOR . $repo->name;
            if (is_dir($sub)) {
                // pull changes to existing package
                $this->stdio->outln("Pulling package '{$repo->name}'.");
                passthru("cd $sub; git pull --all");
            } else {
                // clone new package for installation
                $this->stdio->outln("Cloning package '{$repo->name}'.");
                passthru("cd $dir; git clone {$repo->clone_url}");
            }
        }

        // done!
        $this->stdio->outln('Done!');
    }
}
