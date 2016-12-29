<?php
namespace Aura\Bin\Command;

use Aura\Bin\Exception;

/**
 *
 * Works always and only on the current branch.
 *
 * - `aura release3` to dry-run
 *
 * - `aura release2 $version` to release $version via GitHub
 *
 */
class Release3 extends AbstractCommand
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
    );

    protected $phpdoc;

    protected $phpunit;

    protected $ironmq;

    protected $tweeter;

    public function setPhpdoc($phpdoc)
    {
        $this->phpdoc = $phpdoc;
    }

    public function setPhpunit($phpunit)
    {
        $this->phpunit = $phpunit;
    }

    public function setIronMQ($ironmq)
    {
        $this->ironmq = $ironmq;
    }

    public function setTweeter($tweeter)
    {
        $this->tweeter = $tweeter;
    }

    public function __invoke()
    {
        $this->prep();

        $this->gitPull();
        $this->checkSupportFiles();
        $this->phpunit->v2($this->package);
        if (substr($this->package, -8) !== '_Project') {
            $this->phpdoc->validate($this->package);
        }

        if (! $this->checkChangeLog()) {
            return 1;
        }

        $this->checkIssues();
        $this->updateComposer();
        $this->gitStatus();
        $this->release();
        $this->stdio->outln('Done!');
    }

    protected function prep()
    {
        $argv = $this->getArgv();

        $this->package = basename(getcwd());
        $this->stdio->outln("Package: {$this->package}");

        $this->branch = $this->gitCurrentBranch();
        $this->stdio->outln("Branch: {$this->branch}");

        $this->version = array_shift($argv);
        if ($this->version && ! $this->isValidVersion($this->version)) {
            $message = "Version '{$this->version}' invalid. "
                     . "Please use the format '0.1.5(-dev|-alpha0|-beta1|-RC5)'.";
            throw new Exception($message);
        }
    }

    protected function gitPull()
    {
        $this->stdio->outln("Pull {$this->branch}.");
        $this->shell('git pull', $output, $return);
        if ($return) {
            throw new Exception('', $return);
        }
    }

    protected function checkSupportFiles()
    {
        $files = array(
            '.travis.yml',
            'CHANGELOG.md',
            'CONTRIBUTING.md',
            'LICENSE',
            'README.md',
            'composer.json',
        );

        foreach ($files as $file) {
            if (! $this->isReadableFile($file)) {
                throw new Exception("Please create a '{$file}' file.");
            }
        }

        $license = file_get_contents('LICENSE');
        $year = date('Y');
        if (strpos($license, $year) === false) {
            throw new Exception('The LICENSE copyright year looks out-of-date.');
        }
    }

    protected function checkChangeLog()
    {
        $this->stdio->outln('Checking the change log.');

        // read the log for the src dir
        $this->stdio->outln('Last log :');
        $this->shell('git log -1', $output, $return);
        $src_timestamp = $this->gitDateToTimestamp($output);

        // now read the log for meta/changes.txt
        $this->stdio->outln('Last log on CHANGELOG.md:');
        $this->shell('git log -1 CHANGELOG.md', $output, $return);
        $changes_timestamp = $this->gitDateToTimestamp($output);

        // which is older?
        if ($src_timestamp > $changes_timestamp) {
            $since = date('D M j H:i:s Y', $changes_timestamp);
            $this->stdio->outln('');
            $this->stdio->outln('File CHANGELOG.md is older than last commit.');
            $this->stdio->outln("Add changes from the log ...");
            $this->stdio->outln("    git log --name-only --since='$since' --reverse");
            $this->stdio->outln('... then commit the CHANGELOG.md file.');
            return false;
        }

        $this->stdio->outln('Change log looks up to date.');
        return true;
    }

    protected function checkIssues()
    {
        $issues = $this->github->getIssues($this->package);
        if (! $issues) {
            $this->stdio->outln('No outstanding issues.');
            return;
        }

        $this->stdio->outln('Outstanding issues:');
        foreach ($issues as $issue) {
            // $this->stdio->outln('    ' . $issue->number . '. ' . $issue->title);
            $this->stdio->outln("    {$issue->html_url} ({$issue->title})");
        }
    }

    protected function updateComposer()
    {
        $this->stdio->outln('Updating composer.json ... ');

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

        // force the composer type
        $composer->type = 'library';

        // force the license
        $composer->license = 'MIT';

        // force the homepage
        $composer->homepage = "https://github.com/auraphp/{$this->package}";

        // force the authors
        $composer->authors = array(
            array(
                'name' => "{$this->package} Contributors",
                'homepage' => "https://github.com/auraphp/{$this->package}/contributors",
            )
        );

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
            $this->stdio->outln('Not OK.');
            throw new Exception('Composer file is not valid.');
        }
        $this->stdio->outln('OK.');
    }

    protected function gitStatus()
    {
        $this->stdio->outln('Checking repo status.');
        $this->shell('git status --porcelain', $output, $return);
        if ($return || $output) {
            throw new Exception('Not ready.');
        }

        $this->stdio->outln('Status OK.');
    }

    protected function release()
    {
        if (! $this->version) {
            $this->stdio->outln('Not making a release.');
            return;
        }

        $this->stdio->out("Releasing version {$this->version} via GitHub ... ");
        $release = (object) array(
            'tag_name' => $this->version,
            'target_commitish' => $this->branch,
            'name' => $this->version,
            'body' => $this->getChangeLogContents();,
            'draft' => false,
            'prerelease' => false,
        );

        $response = $this->github->postRelease($this->package, $release);
        if (! isset($response->id)) {
            $this->stdio->outln('failure.');
            $message = var_export((array) $response, true);
            throw new Exception($message);
        }

        $this->stdio->outln('success!');

        $this->stdio->outln('Getting the tagged release.');
        $this->shell('git pull');

        // $this->followupEmailToQueue();
        // $this->followupTweet();
    }

    protected function followupEmailToQueue()
    {
        $this->stdio->out('Queuing an email to the mailing list ... ');
        $changes = trim($this->getChangeLogContents(););
        $data = array(
            'package' => $this->package,
            'version' => $this->version,
            'changes' => $changes
        );
        $this->ironmq->postMessage('notifications', json_encode($data));
        $this->stdio->outln('done.');
    }

    protected function followupTweet()
    {
        $this->stdio->out('Tweeting about the release ... ');
        $status = "We just released {$this->package} {$this->version}! "
                . "https://github.com/auraphp/{$this->package}/releases";
        $this->tweeter->postStatusesUpdate($status);
        $this->stdio->outln("success.");
    }
}
