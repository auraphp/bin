<?php
namespace Aura\Bin\Command;

// aura packages-json
// aura packages-json Aura.OnlyThisRepo
class PackagesJson extends AbstractCommand
{
    protected $json = array();

    public function __invoke()
    {
        $argv = $this->getArgv();
        $only_name = array_shift($argv);

        $file = $this->config->site_dir . '/packages.json';
        $this->json = json_decode(file_get_contents($file));

        $repos = $this->getRepos($only_name);
        foreach ($repos as $repo) {
            $this->updatePackages($repo);
        }
        $this->stdio->outln(json_encode($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function getRepos($only_name)
    {
        $repos = $this->github->getRepos();

        if ($only_name) {
            if (isset($repos[$only_name])) {
                return array($only_name => $repos[$only_name]);
            }
            // only repo does not exist
            return;
        }

        foreach ($repos as $name => $repo) {
            $is_package = substr($name, 0, 5) == 'Aura.';
            if (! $is_package) {
                unset($repos[$name]);
            }
        }

        return $repos;
    }

    protected function updatePackages($repo)
    {
        $tags = $this->github->getTags($repo->name);
        arsort($tags);
        foreach ($tags as $tag) {
            $this->updatePackage($repo, $tag);
        }
    }

    protected function updatePackage($repo, $tag)
    {
        $shortName = substr($repo->name, 5);
        $version = $tag->name;
        $branch = ((int) $version) . '.x';
        if ($branch == '0.x' || isset($this->json->$branch->$shortName)) {
            return;
        }

        $composer = $this->getComposer($repo, $version);
        $this->json->$branch->$shortName = (object) array(
            'type' => $this->getType($repo->name),
            'version' => $version,
            'github' => "https://github.com/auraphp/{$repo->name}",
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
