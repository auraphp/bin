<?php
namespace Aura\Bin\Command;

use Aura\Bin\Exception;

class CreateChangelog extends AbstractCommand
{
    public function __invoke()
    {
        $tags = $this->gitTags();
        $changes = "# CHANGELOG \n\n";
        foreach ($tags as $key => $tag) {
            if (basename(getcwd()) == "Aura.Router") {
                if (in_array($tag, ['3.0.0-alpha1','3.0.0-alpha2'])) {
                    // skip for these versions. There is no changelog
                    continue;
                }
            }
            $changes .= '## ' . $tag . "\n\n";
            $changes .= implode("\n", $this->getChanges($tag)) . "\n\n";
        }

        file_put_contents('CHANGELOG.md', $changes);

        $this->stdio->outln('Changelog file created from older versions.');
    }

    protected function gitTags()
    {
        exec('git tag --list', $versions);
        usort($versions, 'version_compare');

        return array_reverse($versions);
    }

    protected function getChanges($version)
    {
        if (version_compare($version, '2.0.0-beta', '>=')) {
            // >= 2.0.0
            $file = 'CHANGES.md';
        } else {
            $file = 'meta/changes.txt';
        }

        exec("git show {$version}:./{$file}", $output, $return);

        return $output;
    }
}
