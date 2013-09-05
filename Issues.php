<?php
class Issues extends AbstractCommand
{
    public function __invoke(array $argv)
    {
        $list = [];
        $name = array_shift($argv);
        if ($name) {
            $list[] = $name;
        } else {
            $repos = $this->apiGetRepos();
            foreach ($repos as $repo) {
                $list[] = $repo->name;
            }
        }
        
        $issues = [];
        foreach ($list as $name) {
            $issues[$name] = $this->apiGetIssues($name);
        }
        
        if (! $issues) {
            $this->outln("No issues found.");
            exit(0);
        }
        
        foreach ($list as $name) {
            if (! $issues[$name]) {
                continue;
            }
            $this->outln($name . ':');
            foreach ($issues[$name] as $issue) {
                $this->outln('    ' . $issue->number . '. ' . $issue->title);
                $this->outln('    ' . $issue->html_url);
                $this->outln();
            }
        }
    }
}
