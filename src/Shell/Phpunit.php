<?php
namespace Aura\Bin\Shell;

class Phpunit extends AbstractShell
{
    public function v1()
    {
        $this->stdio->outln('Run tests.');
        $cmd = 'cd tests; phpunit';
        $line = $this($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->stdio->outln($line);
            exit(1);
        }
    }

    public function v2($package)
    {
        if (substr($package, -7) == '_Kernel') {
            return $this->v2kernel();
        }

        if (substr($package, -8) == '_Project') {
            return $this->v2project();
        }

        return $this->v2library();
    }

    public function v2library()
    {
        $this->stdio->outln("Running library unit tests.");
        $cmd = 'phpunit -c tests/unit/';
        $line = $this($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->stdio->errln($line);
            exit(1);
        }

        $dir = getcwd() . '/tests/container';
        if (! is_dir($dir)) {
            $this->stdio->outln("No library container tests.");
            return;
        }

        $this->stdio->outln("Running library container tests.");
        $cmd = 'cd tests/container; ./phpunit.sh';
        $line = $this($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->stdio->errln($line);
            exit(1);
        }

        $this('cd tests/container; rm -rf composer.* vendor');
    }

    public function v2kernel()
    {
        $this->stdio->outln("Running kernel tests.");
        $cmd = 'cd tests/kernel; ./phpunit.sh';
        $line = $this->shell($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->stdio->errln($line);
            exit(1);
        }
    }

    public function v2project()
    {
        $this->stdio->outln("Running project tests.");
        $this->shell('composer install');
        $cmd = 'cd tests/project; ./phpunit.sh';
        $line = $this->shell($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->stdio->outln($line);
            exit(1);
        }
        $this->shell('rm -rf composer.lock vendor tmp/log/*.log');
    }

}
