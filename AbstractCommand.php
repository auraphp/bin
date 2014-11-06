<?php
abstract class AbstractCommand
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    protected function out($str = null)
    {
        echo $str;
    }

    protected function outln($str = null)
    {
        $this->out($str . PHP_EOL);
    }

    protected function shell($cmd, &$output = null, &$return = null)
    {
        $cmd = str_replace('; ', ';\\' . PHP_EOL, $cmd);
        $this->outln($cmd);
        $output = null;
        $result = exec($cmd, $output, $return);
        foreach ($output as $line) {
            $this->outln($line);
        }
        return $result;
    }

    protected function api($method, $path, $body = null)
    {
        $github_auth = $this->config->github_user
                     . ':'
                     . $this->config->github_token;

        $api = "https://{$github_auth}@api.github.com";
        $page = 1;
        $stack = array();

        do {
            $url = $api . $path . "?page={$page}";
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", [
                        'User-Agent: php/5.4',
                        'Accept: application/json',
                        'Content-Type: application/json',
                    ]),
                    'content' => $body,
                ],
            ]);
            $data = file_get_contents($url, FALSE, $context);
            $json = json_decode($data);
            if ($json) {
                $stack[] = $json;
            }
            $page ++;
        } while ($json);

        return $stack;
    }

    protected function isReadableFile($file)
    {
        return file_exists($file) && is_readable($file);
    }

    protected function apiGetRepos()
    {
        $stack = $this->api('GET', '/orgs/auraphp/repos');
        $repos = [];
        foreach ($stack as $json) {
            foreach ($json as $repo) {
                $repos[$repo->name] = $repo;
            }
        }
        ksort($repos);
        return $repos;
    }

    protected function apiGetTags($repo)
    {
        $stack = $this->api('GET', "/repos/auraphp/$repo/tags");
        $tags = [];
        foreach ($stack as $json) {
            foreach ($json as $tag) {
                $tags[] = $tag->name;
            }
        }
        usort($tags, 'version_compare');
        return $tags;
    }

    protected function apiGetIssues($name)
    {
        $stack = $this->api('GET', "/repos/auraphp/{$name}/issues");
        $issues = [];
        foreach ($stack as $json) {
            foreach ($json as $issue) {
                $issues[] = $issue;
            }
        }
        return $issues;
    }

    protected function gitCurrentBranch()
    {
        $branch = exec('git rev-parse --abbrev-ref HEAD', $output, $return);
        if ($return) {
            $this->outln(implode(PHP_EOL, $output));
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
        $this->outln('Validate API docs.');

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

            // file-level tag
            $actual = $file['package'];
            if ($actual != $expect) {
                $missing = true;
                $this->outln("  Expected @package {$expect}, actual @package {$actual}, for {$path}");
            }

            // class-level tag
            $actual = $file->class['package'] . $file->interface['package'];
            if ($actual != $expect) {
                $missing = true;
                $this->outln("  Expected @package {$expect}, actual @package {$actual}, for {$class}");
            }
        }

        if ($missing) {
            $this->outln('API docs not valid.');
            exit(1);
        }

        // are there other invalidities?
        foreach ($output as $line) {
            // invalid lines have 2-space indents
            if (substr($line, 0, 2) == '  ') {
                $this->outln('API docs not valid.');
                exit(1);
            }
        }

        // guess they're valid
        $this->outln('API docs look valid.');
    }
}
