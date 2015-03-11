<?php
namespace Aura\Bin\Shell;

use Aura\Cli\Stdio;

abstract class AbstractShell
{
    protected $stdio;

    public function __construct(Stdio $stdio)
    {
        $this->stdio = $stdio;
    }

    public function __invoke($cmd, &$output = null, &$return = null)
    {
        $cmd = str_replace('; ', ';\\' . PHP_EOL, $cmd);
        $this->stdio->outln($cmd);
        $output = null;
        $result = exec($cmd, $output, $return);
        foreach ($output as $line) {
            $this->stdio->outln($line);
        }
        return $result;
    }
}
