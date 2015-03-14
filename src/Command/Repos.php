<?php
namespace Aura\Bin\Command;

class Repos extends AbstractCommand
{
    public function __invoke()
    {
        $list = $this->github->getRepos();
        foreach ($list as $repo) {
            $this->stdio->out($repo->name);
            $tags = $this->github->getTags($repo->name);
            $tag = end($tags);
            if (! $tag) {
                $this->stdio->outln(' (no releases)');
                continue;
            }
            $commit = $this->github->getCommit($repo->name, $tag->commit->sha);
            $date = date('Y-m-d', strtotime($commit->committer->date));
            $this->stdio->outln(" {$date} {$tag->name}");
        }
    }
}
