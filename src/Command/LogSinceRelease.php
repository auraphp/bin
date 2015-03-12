<?php
namespace Aura\Bin\Command;

class LogSinceRelease extends AbstractCommand
{
    public function __invoke()
    {
        $argv = $this->getArgv();

        $orig = null;
        $dir = array_shift($argv);
        if ($dir) {
            $orig = getcwd();
            chdir($dir);
        }

        $version = $this->gitLastVersion();

        exec("git show {$version}", $output, $return);
        $date = date('r', $this->gitDateToTimestamp($output) + 1);

        $package = basename(getcwd());
        $branch = $this->gitCurrentBranch();
        $message = "Last release was {$version} on {$date}";

        $this->stdio->outln("$package $branch");
        $this->stdio->outln($message);
        $this->stdio->outln(str_pad('', strlen($message), '-'));
        $this->stdio->outln();
        passthru("git log --name-status --reverse --after='{$date}'");

        if ($orig) {
            chdir($orig);
        }
    }

    protected function gitLastVersion()
    {
        exec('git tag --list', $versions);
        usort($versions, 'version_compare');
        return end($versions);
    }
}
