<?php
abstract class AbstractCommand
{
    protected $github_auth;
    
    public function __construct($github_auth)
    {
        $this->github_auth = $github_auth;
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
        $cmd = str_replace('; ', ';\\' . PHP_EOL . '    ', $cmd);
        $this->outln('    ' . $cmd);
        $output = null;
        $result = exec($cmd, $output, $return);
        $output = '    ' . implode(PHP_EOL . '    ', $output);
        $this->outln($output);
        return $result;
    }
    
    protected function api($method, $path, $body = null)
    {
        $api = "https://{$this->github_auth}@api.github.com";
        $url = $api . $path;
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", [
                    'User-Agent: php/5.4',
                    'Accept: application/json'
                ]),
            ],
        ]);
        $json = file_get_contents($url, FALSE, $context);
        $data = json_decode($json);
        return $data;
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
