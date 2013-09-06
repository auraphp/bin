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
        $url = $api . $path;
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
        $json = file_get_contents($url, FALSE, $context);
        $data = json_decode($json);
        return $data;
    }
    
    protected function isReadableFile($file)
    {
        return file_exists($file) && is_readable($file);
    }
    
    protected function apiGetRepos()
    {
        $data = $this->api('GET', '/orgs/auraphp/repos');
        $repos = [];
        foreach ($data as $repo) {
            $repos[$repo->name] = $repo;
        }
        ksort($repos);
        return $repos;
    }
    
    protected function apiGetIssues($name)
    {
        return $this->api('GET', "/repos/auraphp/{$name}/issues");
    }
}
