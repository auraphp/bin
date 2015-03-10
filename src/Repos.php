<?php
namespace Aura\Bin;

class Repos extends AbstractCommand
{
    public function __invoke()
    {
        $list = $this->apiGetRepos();
        foreach ($list as $repo) {
            $tags = $this->apiGetTags($repo->name);
            $last = end($tags);
            $this->stdio->outln("$repo->name $last");
        }
    }
}
