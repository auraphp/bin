<?php
namespace Aura\Bin\Command;

class LogSinceRelease extends AbstractCommand
{
    public function __invoke()
    {
        exec("git pull");
        $branch = $this->gitCurrentBranch();
        $version = $this->gitLastVersion($branch);

        exec("git show {$version}", $output, $return);
        $date = date('r', $this->gitDateToTimestamp($output) + 1);

        $package = basename(getcwd());
        $message = "Last {$branch} release was {$version} on {$date}";

        $this->stdio->outln("============================================================");
        $this->stdio->outln("$package $branch");
        $this->stdio->outln($message);
        $this->stdio->outln(str_pad('', strlen($message), '-'));
        $this->stdio->outln();
        passthru("git log --name-status --reverse --after='{$date}'");
        $this->stdio->outln();
    }

    protected function gitLastVersion($branch)
    {
        $branch = (int) $branch;

        exec('git tag --list', $versions);
        usort($versions, 'version_compare');
        $versions = array_reverse($versions);
        foreach ($versions as $version) {
            if ((int) $version === $branch) {
                return $version;
            }
        }
    }
}
