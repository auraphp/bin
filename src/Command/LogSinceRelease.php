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

        $branch = $this->gitCurrentBranch();
        $version = $this->gitLastVersion($branch);

        exec("git show {$version}", $output, $return);
        $date = date('r', $this->gitDateToTimestamp($output) + 1);

        $package = basename(getcwd());
        $message = "Last {$branch} release was {$version} on {$date}";

        $this->stdio->outln("$package $branch");
        $this->stdio->outln($message);
        $this->stdio->outln(str_pad('', strlen($message), '-'));
        $this->stdio->outln();
        passthru("git log --name-status --reverse --after='{$date}'");

        if ($orig) {
            chdir($orig);
        }
    }

    protected function gitLastVersion($branch)
    {
        $branch = (int) $branch;

        exec('git tag --list', $versions);
        usort($versions, 'version_compare');
        rsort($versions);
        foreach ($versions as $version) {
            if ((int) $version === $branch) {
                return $version;
            }
        }
    }
}
