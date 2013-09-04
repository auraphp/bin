<?php
class Repos extends AbstractCommand
{
    public function __invoke()
    {
        $list = $this->apiGetRepos();
        foreach ($list as $repo) {
            $this->outln($repo->name);
        }
    }
}
