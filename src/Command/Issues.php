<?php
namespace Aura\Bin\Command;

class Issues extends AbstractCommand
{
    public function __invoke()
    {
        $list = [];
        $argv = $this->getArgv();

        $name = array_shift($argv);
        if ($name) {
            $list[] = $name;
        } else {
            $repos = $this->github->getRepos();
            foreach ($repos as $repo) {
                $list[] = $repo->name;
            }
        }

        $issues = [];
        foreach ($list as $name) {
            $issues[$name] = $this->github->getIssues($name);
        }

        if (! $issues) {
            $this->stdio->outln("No issues found.");
            exit(0);
        }

        foreach ($list as $name) {
            if (! $issues[$name]) {
                continue;
            }
            $this->stdio->outln($name . ':');
            foreach ($issues[$name] as $issue) {
                $this->stdio->outln('    ' . $issue->number . '. ' . $issue->title);
                $this->stdio->outln('    ' . $issue->html_url);
                $this->stdio->outln();
            }
        }
    }
}
