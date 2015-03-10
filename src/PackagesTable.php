<?php
namespace Aura\Bin;

class PackagesTable extends AbstractCommand
{
    protected $composer;
    protected $readme;

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
        return "| Package | Composer | Release | Description | " . PHP_EOL
             . "| ------- | -------- | ------- | ----------- | ";
    }

    protected function getTableLine($repo)
    {
        $this->composer = $this->getComposer($repo);
        $this->readme = $this->getReadme($repo);

        $package = $this->getPackage($repo);
        $composer = $this->getPackagist($repo);
        $release = $this->getRelease($repo);
        $description = $this->getDescription($repo);
        $quality = $this->getBadges($repo);

        return "| $package | $composer | $release | $description<br />$quality |";
    }

    protected function getPackage($repo)
    {
        return "[{$repo->name}](https://github.com/auraphp/{$repo->name})";
    }

    protected function getPackagist($repo)
    {
        $name = str_replace('-', '&#8209;', $this->composer->name);
        $path = $this->composer->name;
        return "[{$name}](https://packagist.org/packages/{$path})";
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
        $stack = $this->api("GET", "/repos/auraphp/{$repo->name}/releases");
        foreach ($stack as $json) {
            foreach ($json as $release) {
                $versions[] = $release->tag_name;
            }
        }
        return $versions;
    }

    protected function getBadges($repo)
    {
        $text = $this->readme;
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
        return $this->composer->description;
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
