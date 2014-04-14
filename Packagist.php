<?php
// invoke from inside the package dir
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
            $this->outln('Composer file exists.');
            return;
        }
        
        $this->out('Creating composer file ... ');
        $json = $this->getJson();
        file_put_contents($file, $json);
        $this->outln('done.');
        $this->outln('You should add, commit, and push the new file.');
        $this->outln('You should also merge it to master.');
        $this->outln('Then re-run this command.');
        exit(1);
    }
    
    protected function validateFile()
    {
        $this->shell('composer validate', $output, $return);
        if ($return) {
            $this->outln('Composer file is not valid.');
            exit(1);
        }
        $this->outln('Composer file is valid.');
    }
    
    protected function checkHook()
    {
        // the repo name
        $repo = basename(getcwd());
        $this->out("Checking hook on {$repo} ... ");
        
        $data = $this->api('GET', "/repos/auraphp/{$repo}/hooks");
        foreach ($data as $hook) {
            if ($hook->name == 'packagist') {
                $this->outln('already exists.');
                return;
            }
        }
        $this->outln(' does not exist.');
        $this->out('Creating hook ... ');
        
        $hook = new StdClass;
        $hook->name = 'packagist';
        $hook->active = true;
        $hook->config = [
            'user' => $this->config->packagist_user,
            'token' => $this->config->packagist_token,
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
