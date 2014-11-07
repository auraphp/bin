<?php
// invoke from inside the pacakge dir
class Travis extends AbstractCommand
{
    public function __invoke()
    {
        $this->checkFile();
        $this->checkHook();
    }

    protected function checkFile()
    {
        $dir = getcwd();
        $file = $dir . '/.travis.yml';
        if ($this->isReadableFile($file)) {
            $this->outln('Travis file exists.');
            return;
        }

        $this->out('Creating Travis file ... ');
        $yaml = $this->getYaml();
        file_put_contents($file, $yaml);
        $this->outln('done.');
        $this->outln('You should add, commit, and push the new file.');
        $this->outln('You should also merge it to master.');
        $this->outln('Then re-run this command.');
        exit(1);
    }

    protected function checkHook()
    {
        $repo = basename(getcwd());
        $this->out("Checking hook on {$repo} ... ");

        $stack = $this->api('GET', "/repos/auraphp/{$repo}/hooks");
        foreach ($stack as $json) {
            foreach ($json as $hook) {
                if ($hook->name == 'travis') {
                    $this->outln('already exists.');
                    return;
                }
            }
        }

        $this->outln(' does not exist.');
        $this->out('Creating hook ... ');

        $hook = new StdClass;
        $hook->name = 'travis';
        $hook->active = true;
        $hook->config = [
            'user' => $this->config->travis_user,
            'token' => $this->config->travis_token,
        ];

        $body = json_encode($hook);
        $response = $this->api('POST', "/repos/auraphp/{$repo}/hooks", $body);

        if (! isset($response->id)) {
            $this->outln('failure.');
            $this->outln(var_export((array) $response, true));
            exit(1);
        }

        $this->outln('success.');
    }

    protected function getYaml()
    {
        return <<<YAML
language: php
php:
  - 5.4
  - 5.5
before_script:
  - cd tests
script: phpunit

YAML;
    }
}
