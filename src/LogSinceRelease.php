<?php
class LogSinceRelease extends AbstractCommand
{
    public function __invoke(array $argv)
    {
        $orig = null;
        $dir = array_shift($argv);
        if ($dir) {
            $orig = getcwd();
            chdir($dir);
        }

        $version = $this->gitLastVersion();
        $last = exec("git show {$version} --pretty=format:'%H %ci'");
        list($hash, $date, $time, $zone) = explode(' ', $last);
        $package = basename(getcwd());
        $branch = $this->gitCurrentBranch();
        $message = "Last release was {$version} on {$date} at {$time} {$zone}";
        $after = "$date $time $zone";

        $this->outln("$package $branch");
        $this->outln($message);
        $this->outln();
        passthru("git log --name-status --reverse --after='{$after}'");

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
