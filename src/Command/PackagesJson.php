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
        $this->stdio->outln('Reading from ' . $file);
        $this->json = json_decode(file_get_contents($file), true);

        $this->stdio->out('Getting repos ... ');
        $repos = $this->getRepos($only_name);
        $this->stdio->outln('done.');

        foreach ($repos as $repo) {
            $this->stdio->out("Updating {$repo->name} ... ");
            $this->updatePackages($repo);
            $this->stdio->outln('done.');
        }

        foreach ($this->json as $branch => &$packages) {
            ksort($packages);
        }
        $json = json_encode($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->stdio->outln('Writing to ' . $file);
        file_put_contents($file, $json . PHP_EOL);
        $this->stdio->outln('Done!');
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

        $shortName = substr($repo->name, 5);
        $branches = array_keys($this->json);
        foreach ($branches as $branch) {
            unset($this->json[$branch][$shortName]);
        }

        foreach ($tags as $tag) {
            $this->updatePackage($repo, $tag);
        }
    }

    protected function updatePackage($repo, $tag)
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
