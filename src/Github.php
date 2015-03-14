<?php
namespace Aura\Bin;

class Github
{
    protected $github;

    public function __construct($user, $token)
    {
        $this->github = "https://{$user}:{$token}@api.github.com";
    }

    public function api($method, $path, $body = null, $one = false)
    {
        if (strpos($path, '?') === false) {
            $path .= '?';
        } else {
            $path .= '&';
        }

        $page = 1;
        $stack = array();

        do {

            $url = $this->github . $path . "page={$page}";
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

            // for POST etc, do not try additional pages
            $one_page_only = strtolower($method) !== 'get' || $one == true;
            if ($one_page_only) {
                return $json;
            }

            // add results to the stack
            if ($json) {
                $stack[] = $json;
            }

            // next page!
            $page ++;

        } while ($json);

        return $stack;
    }

    public function getRepos()
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

    public function getTags($repoName)
    {
        $stack = $this->api('GET', "/repos/auraphp/{$repoName}/tags");
        $tags = [];
        foreach ($stack as $json) {
            foreach ($json as $tag) {
                $tags[$tag->name] = $tag;
            }
        }
        uksort($tags, 'version_compare');
        return $tags;
    }

    public function getCommit($repoName, $sha)
    {
        return $this->api('GET', "/repos/auraphp/{$repoName}/git/commits/{$sha}", null, true);
    }

    public function getIssues($repoName)
    {
        $stack = $this->api('GET', "/repos/auraphp/{$repoName}/issues?sort=created&direction=asc");
        $issues = [];
        foreach ($stack as $json) {
            foreach ($json as $issue) {
                $issues[] = $issue;
            }
        }
        return $issues;
    }

    public function getVersions($repo)
    {
        $versions = array();
        $stack = $this->api("GET", "/repos/auraphp/{$repo->name}/releases");
        foreach ($stack as $json) {
            foreach ($json as $release) {
                $versions[] = $release->tag_name;
            }
        }
        return $versions;
    }

    public function getHooks($repoName)
    {
        $hooks = array();
        $stack = $this->api('GET', "/repos/auraphp/{$repoName}/hooks");
        foreach ($stack as $json) {
            foreach ($json as $hook) {
                $hooks[] = $hook;
            }
        }
        return $hooks;
    }

    public function postHook($repoName, $hook)
    {
        $body = json_encode($hook);
        return $this->api('POST', "/repos/auraphp/{$repoName}/hooks", $body);
    }

    public function postRelease($repoName, $release)
    {
        $body = json_encode($release);
        return $this->api('POST', "/repos/auraphp/{$repoName}/releases", $body);
    }
}
