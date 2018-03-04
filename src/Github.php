<?php
namespace Aura\Bin;

use Milo\Github\Api;
use Milo\Github\OAuth\Token;

class Github
{
    protected $api;

    public function __construct($user, $token)
    {
        // $this->github = "https://{$user}:{$token}@api.github.com";
        $this->api = new Api;
        $this->api->setToken(new Token($token));
    }

    public function getRepos()
    {
        $repos = [];

        foreach ($this->api->paginator('/orgs/auraphp/repos')->limit(10) as $response) {
            $json = $this->api->decode($response);
            foreach ($json as $repo) {
                $repos[$repo->name] = $repo;
            }
        }

        ksort($repos);

        return $repos;
    }

    public function getTags($repoName)
    {
        $tags = [];

        foreach ($this->api->paginator("/repos/auraphp/{$repoName}/tags")->limit(10) as $response) {
            $json = $this->api->decode($response);
            foreach ($json as $tag) {
                $tags[$tag->name] = $tag;
            }
        }

        uksort($tags, 'version_compare');
        return $tags;
    }

    public function getCommit($repoName, $sha)
    {
        $response = $this->api->get("/repos/auraphp/{$repoName}/git/commits/{$sha}");

        return $this->api->decode($response);
    }

    public function getIssues($repoName)
    {
        $issues = [];

        foreach ($this->api->paginator("/repos/auraphp/{$repoName}/issues?sort=created&direction=asc")->limit(10) as $response) {
            $json = $this->api->decode($response);
            foreach ($json as $issue) {
                $issues[] = $issue;
            }
        }
        
        return $issues;
    }

    public function getVersions($repo)
    {
        $versions = array();

        foreach ($this->api->paginator("/repos/auraphp/{$repo->name}/releases")->limit(10) as $response) {
            $json = $this->api->decode($response);
            foreach ($json as $release) {
                $versions[] = $release->tag_name;
            }
        }

        return $versions;
    }

    public function getHooks($repoName)
    {
        $hooks = array();

        foreach ($this->api->paginator("/repos/auraphp/{$repoName}/hooks")->limit(10) as $response) {
            $json = $this->api->decode($response);
            foreach ($json as $hook) {
                $hooks[] = $hook;
            }
        }
        
        return $hooks;
    }

    public function postHook($repoName, $hook)
    {
        $response = $this->api->post("/repos/auraphp/{$repoName}/hooks", $hook);
        
        return $this->api->decode($response);
    }

    public function postRelease($repoName, $release)
    {
        $response = $this->api->post("/repos/auraphp/{$repoName}/releases", $release);

        return $this->api->decode($response);
    }
}
