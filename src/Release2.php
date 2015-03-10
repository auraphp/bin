<?php
namespace Aura\Bin;

/**
 *
 * Works always and only on the current branch.
 *
 * - `aura release2` to dry-run
 *
 * - `aura release2 $version` to release $version via GitHub
 *
 */
class Release2 extends AbstractCommand
{
    protected $package;

    protected $branch;

    protected $version;

    protected $composer = array(
        'name' => null,
        'type' => null,
        'description' => null,
        'keywords' => array(),
        'homepage' => null,
        'license' => null,
        'authors' => array(),
        'require' => array(),
        'autoload' => array(),
        'extra' => array(),
    );

    public function __invoke($argv)
    {
        $this->prep($argv);

        $this->gitPull();
        $this->checkSupportFiles();
        $this->runTests();
        if (substr($this->package, -8) !== '_Project') {
            $this->validateDocs($this->package);
        }
        $this->checkChangeLog();
        $this->checkIssues();
        $this->updateComposer();
        $this->gitStatus();
        $this->release();
        $this->outln('Done!');
    }

    protected function prep($argv)
    {
        $this->package = basename(getcwd());
        $this->outln("Package: {$this->package}");

        $this->branch = $this->gitCurrentBranch();
        $this->outln("Branch: {$this->branch}");

        $this->version = array_shift($argv);
        if ($this->version && ! $this->isValidVersion($this->version)) {
            $this->outln("Version '{$this->version}' invalid.");
            $this->outln("Please use the format '0.1.5(-dev|-alpha0|-beta1|-RC5)'.");
            exit(1);
        }
    }

    protected function gitPull()
    {
        $this->outln("Pull {$this->branch}.");
        $this->shell('git pull', $output, $return);
        if ($return) {
            exit($return);
        }
    }

    protected function runTests()
    {
        if (substr($this->package, -7) == '_Kernel') {
            return $this->runKernelTests();
        }

        if (substr($this->package, -8) == '_Project') {
            return $this->runProjectTests();
        }

        return $this->runLibraryTests();

    }

    protected function runLibraryTests()
    {
        $this->outln("Running library unit tests.");
        $cmd = 'phpunit -c tests/unit/';
        $line = $this->shell($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->outln($line);
            exit(1);
        }

        $dir = getcwd() . '/tests/container';
        if (! is_dir($dir)) {
            $this->outln("No library container tests.");
            return;
        }

        $this->outln("Running library container tests.");
        $cmd = 'cd tests/container; ./phpunit.sh';
        $line = $this->shell($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->outln($line);
            exit(1);
        }
        $this->shell('cd tests/container; rm -rf composer.* vendor');
    }

    protected function runKernelTests()
    {
        $this->outln("Running kernel tests.");
        $cmd = 'cd tests/kernel; ./phpunit.sh';
        $line = $this->shell($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->outln($line);
            exit(1);
        }
    }

    protected function runProjectTests()
    {
        $this->outln("Running project tests.");
        $this->shell('composer install');
        $cmd = 'cd tests/project; ./phpunit.sh';
        $line = $this->shell($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->outln($line);
            exit(1);
        }
        $this->shell('rm -rf composer.lock vendor tmp/log/*.log');
    }

    protected function checkSupportFiles()
    {
        $files = array(
            '.travis.yml',
            'CHANGES.md',
            'CONTRIBUTING.md',
            'README.md',
            'composer.json',
        );

        foreach ($files as $file) {
            if (! $this->isReadableFile($file)) {
                $this->outln("Please create a '{$file}' file.");
                exit(1);
            }
        }
    }

    protected function checkChangeLog()
    {
        $this->outln('Checking the change log.');

        // read the log for the src dir
        $this->outln('Last log on src/ :');
        $this->shell('git log -1 src', $output, $return);
        $src_timestamp = $this->gitDateToTimestamp($output);

        // now read the log for meta/changes.txt
        $this->outln('Last log on CHANGES.md:');
        $this->shell('git log -1 CHANGES.md', $output, $return);
        $changes_timestamp = $this->gitDateToTimestamp($output);

        // which is older?
        if ($src_timestamp > $changes_timestamp) {
            $since = date('D M j H:i:s Y', $changes_timestamp);
            $this->outln('');
            $this->outln('File CHANGES.md is older than src/ .');
            $this->outln("Add changes from the log ...");
            $this->outln("    git log --name-only --since='$since' --reverse");
            $this->outln('... then commit the CHANGES.md file.');
            exit(1);
        }

        $this->outln('Change log looks up to date.');
    }

    protected function gitDateToTimestamp($output)
    {
        foreach ($output as $line) {
            if (substr($line, 0, 5) == 'Date:') {
                $date = trim(substr($line, 5));
                return strtotime($date);
            }
        }
        $this->outln('No date found in log.');
        exit(1);
    }

    protected function checkIssues()
    {
        $issues = $this->apiGetIssues($this->package);
        if (! $issues) {
            $this->outln('No outstanding issues.');
            return;
        }

        $this->outln('Outstanding issues:');
        foreach ($issues as $issue) {
            // $this->outln('    ' . $issue->number . '. ' . $issue->title);
            $this->outln("    {$issue->html_url} ({$issue->title})");
        }
    }

    protected function updateComposer()
    {
        $this->outln('Updating composer.json ... ');

        // get composer data and normalize order of elements
        $composer = json_decode(file_get_contents('composer.json'));
        $composer = (object) array_merge(
            (array) $this->composer,
            (array) $composer
        );

        // force the name
        $composer->name = str_replace(
            array('.', '_'),
            array('/', '-'),
            strtolower($this->package)
        );

        // find the *aura* type
        $pos = strrpos($composer->name, '-');
        $aura_type = substr($composer->name, $pos + 1);
        if (! in_array($aura_type, array('bundle', 'project', 'kernel'))) {
            $aura_type = 'library';
        }

        // leave project composer files alone
        if ($aura_type == 'project') {
            $this->validateComposer($composer);
            return;
        }

        // force the composer type
        $composer->type = 'library';

        // force the license
        $composer->license = 'BSD-2-Clause';

        // force the homepage
        $composer->homepage = "https://github.com/auraphp/{$this->package}";

        // force the authors
        $composer->authors = array(
            array(
                'name' => "{$this->package} Contributors",
                'homepage' => "https://github.com/auraphp/{$this->package}/contributors",
            )
        );

        // force the *aura* type
        $composer->extra->aura->type = $aura_type;

        // validate it and done
        $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents('composer.json', $json . PHP_EOL);
        $this->validateComposer();
    }

    protected function validateComposer()
    {
        $cmd = 'composer validate';
        $result = $this->shell($cmd, $output, $return);
        if ( $return) {
            $this->outln('Not OK.');
            $this->outln('Composer file is not valid.');
            exit(1);
        }
        $this->outln('OK.');
    }

    protected function gitStatus()
    {
        $this->outln('Checking repo status.');
        $this->shell('git status --porcelain', $output, $return);
        if ($return || $output) {
            $this->outln('Not ready.');
            exit(1);
        }

        $this->outln('Status OK.');
    }

    protected function release()
    {
        if (! $this->version) {
            $this->outln('Not making a release.');
            return;
        }

        $this->outln("Releasing version {$this->version} via GitHub.");
        $response = $this->api(
            'POST',
            "/repos/auraphp/{$this->package}/releases",
            json_encode(array(
                'tag_name' => $this->version,
                'target_commitish' => $this->branch,
                'name' => $this->version,
                'body' => file_get_contents('CHANGES.md'),
                'draft' => false,
                'prerelease' => false,
            ))
        );

        $this->shell('git pull');
    }
}
