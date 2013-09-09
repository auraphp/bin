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
        
        $version = exec('git tag --list');
        $last = exec("git show {$version} --pretty=format:'%H %ci'");
        list($hash, $date, $time, $zone) = explode(' ', $last);
        $package = basename(getcwd());
        $message = "$package: last release was {$version} on {$date} at {$time} {$zone}";
        $this->outln($message);
        $this->outln();
        $after = "$date $time $zone";
        passthru("git log --name-status --reverse --after='{$after}'");
        
        if ($orig) {
            chdir($orig);
        }
    }
}
