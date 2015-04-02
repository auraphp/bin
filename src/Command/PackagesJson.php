<?php
namespace Aura\Bin\Command;

class PackagesJson extends AbstractCommand
{
    protected $json = array();

    public function __invoke()
    {
        $repos = $this->getRepos();
        foreach ($repos as $repo) {
            $this->addPackage($repo);
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

    protected function addPackage($repo)
    {
        $composer = $this->getComposer($repo);
        $key = substr($repo->name, 5);
        $this->json[$key] = array(
            'type' => $this->getType($repo->name),
            'version' => $this->getVersion($repo->name),
            'github' => "https://github.com/auraphp/{$repo->name}",
            'composer' => $composer->name,
            'description' => $composer->description
        );
    }

    protected function getComposer($repo)
    {
        $name = $repo->name;
        $branch = $repo->default_branch;
        return json_decode(file_get_contents(
            "https://raw.github.com/auraphp/{$name}/{$branch}/composer.json"
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
