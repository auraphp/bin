<?php
namespace Aura\Bin\Command;

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
            $this->stdio->outln('Travis file exists.');
            return;
        }

        $this->stdio->out('Creating Travis file ... ');
        $yaml = $this->getYaml();
        file_put_contents($file, $yaml);
        $this->stdio->outln('done.');
        $this->stdio->outln('You should add, commit, and push the new file.');
        $this->stdio->outln('You should also merge it to master.');
        $this->stdio->outln('Then re-run this command.');
        exit(1);
    }

    protected function checkHook()
    {
        $repo = basename(getcwd());
        $this->stdio->out("Checking hook on {$repo} ... ");

        $hooks = $this->github->getHooks($repo);
        foreach ($hooks as $hook) {
            if ($hook->name == 'travis') {
                $this->stdio->outln('already exists.');
                return;
            }
        }

        $this->stdio->outln(' does not exist.');
        $this->stdio->out('Creating hook ... ');

        $hook = new StdClass;
        $hook->name = 'travis';
        $hook->active = true;
        $hook->config = [
            'user' => $this->config->travis_user,
            'token' => $this->config->travis_token,
        ];

        $response = $this->github->postHook($repo, $hook);
        if (! isset($response->id)) {
            $this->stdio->outln('failure.');
            $this->stdio->outln(var_export((array) $response, true));
            exit(1);
        }

        $this->stdio->outln('success.');
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
