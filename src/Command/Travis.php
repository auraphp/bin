<?php
namespace Aura\Bin\Command;

use Aura\Bin\Exception;
use StdClass;

class Travis extends AbstractCommand
{
    public function __invoke()
    {
        if (! $this->checkFile()) {
            return 1;
        }

        $this->checkHook();
    }

    protected function checkFile()
    {
        $dir = getcwd();
        $file = $dir . '/.travis.yml';
        if ($this->isReadableFile($file)) {
            $this->stdio->outln('Travis file exists.');
            return true;
        }

        $this->stdio->out('Creating Travis file ... ');
        $yaml = $this->getYaml();
        file_put_contents($file, $yaml);
        $this->stdio->outln('done.');
        $this->stdio->outln('You should add, commit, and push the new file.');
        $this->stdio->outln('You should also merge it to master.');
        $this->stdio->outln('Then re-run this command.');
        return false;
    }

    protected function checkHook()
    {
        $repo = basename(getcwd());
        $this->stdio->out("Checking hook on {$repo} ... ");

        $hooks = $this->github->getHooks($repo);
        foreach ($hooks as $hook) {
            if ($hook->name == 'travis') {
                $this->stdio->outln('already exists.');
                return true;
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
            $message = var_export((array) $response, true);
            throw new Exception($message);
        }

        $this->stdio->outln('success.');
        return true;
    }

    protected function getYaml()
    {
        return <<<YAML
language: php
php:
  - 5.4
  - 5.5
  - 5.6
  - 7
  - hhvm
before_script:
  - composer self-update
  - composer install
script:
  - phpunit --coverage-clover=coverage.clover
after_script:
  - wget https://scrutinizer-ci.com/ocular.phar
  - php ocular.phar code-coverage:upload --format=php-clover coverage.clover

YAML;
    }
}
