<?php
namespace Aura\Bin\Shell;

use Aura\Bin\Exception;

class Phpunit extends AbstractShell
{
    public function v1()
    {
        $this->stdio->outln('Run tests.');
        $cmd = 'cd tests; phpunit';
        $line = $this($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            throw new Exception($line);
        }
    }

    public function v2($package)
    {
        $this->stdio->outln("Running package tests.");

        $composer = json_decode(file_get_contents('./composer.json'));

        $install = isset($composer->{'require-dev'})
                || substr($package, -7) == '_Kernel'
                || substr($package, -8) == '_Project';

        if ($install && ! file_exists('./vendor/autoload.php')) {
            $this('composer install');
        }

        $phpunit = 'phpunit';
        if (file_exists('./phpunit.sh')) {
            $phpunit = './phpunit.sh';
        }

        $line = $this($phpunit, $output, $return);
        if ($return) {
            throw new Exception($line);
        }
        $this('rm -rf composer.lock vendor');
    }
}
