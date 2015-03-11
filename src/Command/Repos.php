<?php
namespace Aura\Bin\Command;

class Repos extends AbstractCommand
{
    public function __invoke()
    {
        $list = $this->github->getRepos();
        foreach ($list as $repo) {
            $tags = $this->github->getTags($repo->name);
            $last = end($tags);
            $this->stdio->outln("$repo->name $last");
        }
    }
}
