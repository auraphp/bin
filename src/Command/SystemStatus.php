<?php
namespace Aura\Bin\Command;

/**
 * Outputs the status of "system-v1" skeleton and packages.
 * Only invoke this from a system directory.
 */
class SystemStatus extends AbstractCommand
{
    public function __invoke()
    {
        // system as a whole
        $this->stdio->outln('------------------------------');
        $this->stdio->outln();
        $this->stdio->outln('system');
        $this->stdio->outln();
        passthru('git status');

        // the package directory
        $glob = getcwd() . '/package/Aura.*';
        $dirs = glob($glob, GLOB_ONLYDIR);

        // for each of the repositories ...
        foreach ($dirs as $dir) {
            $this->stdio->outln('------------------------------');
            $this->stdio->outln();
            $this->stdio->outln();
            $this->stdio->outln(basename($dir));
            $this->stdio->outln();
            passthru("cd $dir; git status");
        }

        // done!
        $this->stdio->outln('------------------------------');
        $this->stdio->outln();
        $this->stdio->outln('Done!');
    }
}
