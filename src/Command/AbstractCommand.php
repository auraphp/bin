<?php
namespace Aura\Bin\Command;

use Aura\Bin\Config;
use Aura\Bin\Github;
use Aura\Cli\Stdio;
use Aura\Cli\Context;

abstract class AbstractCommand
{
    protected $config;
    protected $context;
    protected $stdio;

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
            $this->stdio->outln(implode(PHP_EOL, $output));
            exit($return);
        }
        return trim($branch);
    }

    protected function isValidVersion($version)
    {
        $format = '^(\d+.\d+.\d+)(-(dev|alpha\d+|beta\d+|RC\d+))?$';
        preg_match("/$format/", $version, $matches);
        return (bool) $matches;
    }

    protected function validateDocs($package)
    {
        $this->stdio->outln('Validate API docs.');

        // remove previous validation records
        $target = "/tmp/phpdoc/{$package}";
        $this->shell("rm -rf {$target}");

        // validate
        $cmd = "phpdoc -d src/ -t {$target} --force --verbose --template=xml";
        $line = $this->shell($cmd, $output, $return);

        // remove logs
        $this->shell('rm -f phpdoc-*.log');

        // get the XML file and look for errors
        $xml = simplexml_load_file("{$target}/structure.xml");

        // are there missing @package tags?
        $missing = false;
        foreach ($xml->file as $file) {

            // get the expected package name
            $class  = $file->class->full_name . $file->interface->full_name;
            $parts  = explode('\\', ltrim($class, '\\'));
            $expect = array_shift($parts) . '.' . array_shift($parts);
            $path = $file['path'];

            // skip traits
            if (substr($path, -9) == 'Trait.php') {
                continue;
            }

            // class-level tag
            $actual = $file->class['package'] . $file->interface['package'];
            if ($actual != $expect) {
                $missing = true;
                $this->stdio->outln("  Expected @package {$expect}, actual @package {$actual}, for class {$class}");
            }
        }

        if ($missing) {
            $this->stdio->outln('API docs not valid.');
            exit(1);
        }

        // are there other invalidities?
        foreach ($output as $line) {
            // invalid lines have 2-space indents
            if (substr($line, 0, 2) == '  ') {
                $this->stdio->outln('API docs not valid.');
                exit(1);
            }
        }

        // guess they're valid
        $this->stdio->outln('API docs look valid.');
    }
}
