<?php
namespace Aura\Bin\Command;

use StdClass;

class Packagist extends AbstractCommand
{
    public function __invoke()
    {
        $this->checkFile();
        $this->validateFile();
        $this->checkHook();
    }

    protected function checkFile()
    {
        $dir = getcwd();
        $file = $dir . '/composer.json';
        if ($this->isReadableFile($file)) {
            $this->stdio->outln('Composer file exists.');
            return;
        }

        $this->stdio->out('Creating composer file ... ');
        $json = $this->getJson();
        file_put_contents($file, $json);
        $this->stdio->outln('done.');
        $this->stdio->outln('You should add, commit, and push the new file.');
        $this->stdio->outln('You should also merge it to master.');
        $this->stdio->outln('Then re-run this command.');
        exit(1);
    }

    protected function validateFile()
    {
        $this->shell('composer validate', $output, $return);
        if ($return) {
            $this->stdio->outln('Composer file is not valid.');
            exit(1);
        }
        $this->stdio->outln('Composer file is valid.');
    }

    protected function checkHook()
    {
        // the repo name
        $repo = basename(getcwd());
        $this->stdio->out("Checking hook on {$repo} ... ");

        $hooks = $this->github->getHooks($repo);
        foreach ($hooks as $hook) {
            if ($hook->name == 'packagist') {
                $this->stdio->outln('already exists.');
                return;
            }
        }

        $this->stdio->outln(' does not exist.');
        $this->stdio->out('Creating hook ... ');

        $hook = new StdClass;
        $hook->name = 'packagist';
        $hook->active = true;
        $hook->config = [
            'user' => $this->config->packagist_user,
            'token' => $this->config->packagist_token,
        ];

        $response = $this->github->postHook($repo, $hook);
        if (! isset($response->id)) {
            $this->stdio->outln('failure.');
            $this->stdio->outln(var_export((array) $response, true));
            exit(1);
        }

        $this->stdio->outln('success.');
    }

    protected function getJson()
    {
        $repo = basename(getcwd());
        $name = str_replace('.', '/', strtolower($repo));
        return <<<JSON
{
    "name": "{$name}",
    "type": "library",
    "description": "DESCRIPTION",
    "keywords": ["FOO", "BAR", "BAZ"],
    "homepage": "http://auraphp.com/package/{$repo}",
    "license": "BSD-2-Clause",
    "authors": [
        {
            "name": "{$repo} Contributors",
            "homepage": "https://github.com/auraphp/{$repo}/contributors"
        }
    ],
    "require": {
        "php": ">=5.4.0"
    },
    "autoload": {
        "psr-4": {
            "{$namespace}": "src/"
        }
    },
    "extra": {
        "aura": {
            "type": "library"
        },
        "branch-alias": {
            "dev-develop-2": "2.0.x-dev"
        }
    }
}

JSON;
    }
}
