<?php
namespace Aura\Bin\Shell;

class Git extends AbstractShell
{
    protected function currentBranch()
    {
        $branch = exec('git rev-parse --abbrev-ref HEAD', $output, $return);
        if ($return) {
            $this->stdio->outln(implode(PHP_EOL, $output));
            exit($return);
        }
        return trim($branch);
    }


    protected function lastVersion()
    {
        exec('git tag --list', $versions);
        usort($versions, 'version_compare');
        return end($versions);
    }

    protected function checkout()
    {
        if ($this->branch == $this->gitCurrentBranch()) {
            $this->stdio->outln("Already on branch {$this->branch}.");
            return;
        }

        $this->stdio->outln("Checkout {$this->branch}.");
        $this("git checkout {$this->branch}", $output, $return);
        if ($return) {
            exit($return);
        }
    }

    protected function pull()
    {
        $this->stdio->outln("Pull {$this->branch}.");
        $this('git pull', $output, $return);
        if ($return) {
            exit($return);
        }
    }

    public function lastLogDate()
    {

    }

    protected function dateToTimestamp($output)
    {
        foreach ($output as $line) {
            if (substr($line, 0, 5) == 'Date:') {
                $date = trim(substr($line, 5));
                return strtotime($date);
            }
        }
        $this->stdio->outln('No date found in log.');
        exit(1);
    }
}
