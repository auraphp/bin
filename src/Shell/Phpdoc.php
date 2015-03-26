<?php
namespace Aura\Bin\Shell;

class Phpdoc extends AbstractShell
{
    public function validate($package)
    {
        $this->stdio->outln('Validate API docs.');

        // remove previous validation records
        $target = "/tmp/phpdoc/{$package}";
        $this("rm -rf {$target}");

        // validate
        $cmd = "phpdoc -d src/ -t {$target} --force --verbose --template=xml";
        $line = $this($cmd, $output, $return);

        // remove logs
        $this('rm -f phpdoc-*.log');

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

            // class-level tag (don't care about file-level)
            $actual = $file->class['package'] . $file->interface['package'];
            if ($actual && $actual != $expect) {
                $missing = true;
                $this->stdio->errln("  Expected @package {$expect}, actual @package {$actual}, for class {$class}");
            }
        }

        if ($missing) {
            $this->stdio->errln('API docs not valid.');
            exit(1);
        }

        // are there other invalidities?
        foreach ($output as $line) {
            // invalid lines have 2-space indents
            if (substr($line, 0, 2) == '  ') {
                $this->stdio->errln('API docs not valid.');
                exit(1);
            }
        }

        // guess they're valid
        $this->stdio->outln('API docs look valid.');
    }

    public function writeVersionApi($version_dir)
    {
        $this->stdio->outln("Writing version API docs.");
        $api_dir = "{$version_dir}/api";
        $cmd = "phpdoc -d ./src -t $api_dir --force --validate";
        $this($cmd, $output, $return);

        $this->stdio->outln('Remove API cache files.');
        $this("rm -rf $api_dir/structure.xml");
        $this("rm -rf $api_dir/phpdoc-cache*");
    }
}
