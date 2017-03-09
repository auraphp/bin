<?php
namespace Aura\Bin\Command;

use Aura\Bin\Config;
use Aura\Bin\Exception;
use Aura\Bin\Github;
use Aura\Cli\Stdio;
use Aura\Cli\Context;

abstract class AbstractCommand
{
    protected $config;
    protected $context;
    protected $stdio;
    protected $github;

    public function __construct(
        Config $config,
        Context $context,
        Stdio $stdio,
        Github $github
    ) {
        $this->config = $config;
        $this->context = $context;
        $this->stdio = $stdio;
        $this->github = $github;
    }

    protected function getArgv()
    {
        $argv = $this->context->argv->get();
        array_shift($argv); // cli/console.php
        array_shift($argv); // command
        return $argv;
    }

    protected function shell($cmd, &$output = null, &$return = null)
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

    protected function isReadableFile($file)
    {
        return file_exists($file) && is_readable($file);
    }


    protected function gitCurrentBranch()
    {
        $branch = exec('git rev-parse --abbrev-ref HEAD', $output, $return);
        if ($return) {
            throw new Exception(implode(PHP_EOL, $output), $return);
        }
        return trim($branch);
    }

    protected function gitDateToTimestamp($output)
    {
        foreach ($output as $line) {
            if (substr($line, 0, 5) == 'Date:') {
                $date = trim(substr($line, 5));
                return strtotime($date);
            }
        }
        throw new Exception('No date found in log.');
    }

    protected function isValidVersion($version)
    {
        $format = '^(\d+.\d+.\d+)(-(dev|alpha\d+|beta\d+|RC\d+))?$';
        preg_match("/$format/", $version, $matches);
        return (bool) $matches;
    }

    protected function getReleaseNotes()
    {
        if (preg_match('/##\s(\d+.\d+.\d+)(-(dev|alpha\d+|beta\d+|RC\d+))?([\s\S]*?)##\s(\d+.\d+.\d+)(-(dev|alpha\d+|beta\d+|RC\d+))?/s', file_get_contents('CHANGELOG.md'), $matches, PREG_OFFSET_CAPTURE, 1)) {
            return trim($matches[4][0]) . PHP_EOL;
        }
    }
}
