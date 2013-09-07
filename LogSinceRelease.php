<?php
class LogSinceRelease extends AbstractCommand
{
    public function __invoke(array $argv)
    {
        $version = $this->shell('git tag --list');
        $last = $this->shell("git show {$version} --pretty=format:'%H %ci'");
        list($hash, $date, $time, $zone) = explode(' ', $last);
        $message = "Last release was {$version} on {$date} at {$time} {$zone}";
        $this->outln($message);
        $this->outln('');
        $this->outln('--------');
        $this->outln('');
        $after = "$date $time $zone";
        $this->shell("git log --name-status --reverse --after='{$after}'");
    }
}
