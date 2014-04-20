<?php
class PackagesTable extends AbstractCommand
{
    public function __invoke()
    {
        $repos = $this->getRepos();
        $this->outln($this->getTableHead());
        foreach ($repos as $repo) {
            $this->outln($this->getTableLine($repo));
        }
    }

    protected function getRepos()
    {
        $repos = $this->apiGetRepos();
        foreach ($repos as $name => $repo) {
            $is_package = substr($name, 0, 5) == 'Aura.';
            $is_v2 = $repo->default_branch == 'develop-2'
                  || substr($name, -7) == '_Kernel'
                  || substr($name, -8) == '_Project';
            if (! $is_package || ! $is_v2) {
                unset($repos[$name]);
            }
        }
        return $repos;
    }

    protected function getTableHead()
    {
        return "| Package | Release | Description | " . PHP_EOL
             . "| ------- | ------- | ----------- | ";
    }

    protected function getTableLine($repo)
    {
        $package = "[{$repo->name}](https://github.com/auraphp/{$repo->name})"; 
        $release = $this->getRelease($repo);
        $description = $this->getDescription($repo);
        $quality = $this->getBadges($repo);
        return "| $package | $release | $description<br />$quality |";
    }

    protected function getRelease($repo)
    {
        $versions = $this->getVersions($repo);
        $version = array_shift($versions);
        if (! $version) {
            return "-";
        }
        $version = str_replace('-', '&#8209;', $version);
        return "[$version](https://github.com/auraphp/{$repo->name}/releases)";
    }

    protected function getVersions($repo)
    {
        $versions = array();
        $releases = $this->api("GET", "/repos/auraphp/{$repo->name}/releases");
        if ($releases) {
            foreach ($releases as $release) {
                $versions[] = $release->tag_name;
            }
        }
        return $versions;
    }

    protected function getBadges($repo)
    {
        $text = $this->getReadme($repo);
        $pos = strpos($text, '[!');
        $text = substr($text, $pos);
        $pos = strpos($text, PHP_EOL . PHP_EOL);
        $text = substr($text, 0, $pos);
        return str_replace(PHP_EOL, ' ', $text);
    }

    protected function getReadme($repo)
    {
        $name = $repo->name;
        $branch = $repo->default_branch;
        return file_get_contents(
            "https://raw.github.com/auraphp/{$name}/{$branch}/README.md"
        );
    }

    protected function getDescription($repo)
    {
        $composer = $this->getComposer($repo);
        return $composer->description;
    }

    protected function getComposer($repo)
    {
        $name = $repo->name;
        $branch = $repo->default_branch;
        return json_decode(file_get_contents(
            "https://raw.github.com/auraphp/{$name}/{$branch}/composer.json"
        ));
    }

}
