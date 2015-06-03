<?php
namespace Aura\Bin\Command;

class PackagesJson extends AbstractCommand
{
    protected $json = array(
        '3.x' => array(),
        '2.x' => array(),
        '1.x' => array(),
    );

    public function __invoke()
    {
        $repos = $this->getRepos();
        foreach ($repos as $repo) {
            $this->addPackages($repo);
        }
        $this->stdio->outln(json_encode($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function getRepos()
    {
        $repos = $this->github->getRepos();
        foreach ($repos as $name => $repo) {
            $is_package = substr($name, 0, 5) == 'Aura.';
            if (! $is_package) {
                unset($repos[$name]);
            }
        }
        return $repos;
    }

    protected function addPackages($repo)
    {
        $tags = $this->github->getTags($repo->name);
        arsort($tags);
        foreach ($tags as $tag) {
            $this->addPackage($repo, $tag);
        }
    }

    protected function addPackage($repo, $tag)
    {
        $shortName = substr($repo->name, 5);
        $version = $tag->name;
        $branch = ((int) $version) . '.x';
        if ($branch == '0.x' || isset($this->json[$branch][$shortName])) {
            return;
        }

        $composer = $this->getComposer($repo, $version);
        $this->json[$branch][$shortName] = array(
            'type' => $this->getType($repo->name),
            'version' => $version,
            'github' => "https://github.com/auraphp/{$repo->name}/tree/{$branch}",
            'composer' => $composer->name,
            'description' => $composer->description
        );
    }

    protected function getComposer($repo, $version)
    {
        $name = $repo->name;
        return json_decode(file_get_contents(
            "https://raw.github.com/auraphp/{$name}/{$version}/composer.json"
        ));
    }

    protected function getVersion($name)
    {
        $tags = $this->github->getTags($name);
        $tag = array_pop($tags);
        return $tag->name;
    }

    protected function getType($name)
    {
        if ($name == 'Aura.Framework' || $name == 'Aura.Framework_Demo') {
            return 'framework';
        }

        $type = strrchr($name, '_');
        if (! $type) {
            return 'library';
        }

        return strtolower(trim($type, '_'));
    }
}
