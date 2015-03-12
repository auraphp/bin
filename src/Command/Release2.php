<?php
namespace Aura\Bin\Command;

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

    protected $phpdoc;

    protected $phpunit;

    protected $mailer;

    public function setPhpdoc($phpdoc)
    {
        $this->phpdoc = $phpdoc;
    }

    public function setPhpunit($phpunit)
    {
        $this->phpunit = $phpunit;
    }

    public function setMailer($mailer)
    {
        $this->mailer = $mailer;
    }

    public function __invoke()
    {
        $this->prep();

        // $this->gitPull();
        // $this->checkSupportFiles();
        // $this->phpunit->v2($this->package);
        // if (substr($this->package, -8) !== '_Project') {
        //     $this->phpdoc->validate($this->package);
        // }
        // $this->checkChangeLog();
        // $this->checkIssues();
        // $this->updateComposer();
        // $this->gitStatus();
        // $this->release();
        $this->followup();
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
            $this->stdio->outln("Version '{$this->version}' invalid.");
            $this->stdio->outln("Please use the format '0.1.5(-dev|-alpha0|-beta1|-RC5)'.");
            exit(1);
        }
    }

    protected function gitPull()
    {
        $this->stdio->outln("Pull {$this->branch}.");
        $this->shell('git pull', $output, $return);
        if ($return) {
            exit($return);
        }
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
                $this->stdio->outln("Please create a '{$file}' file.");
                exit(1);
            }
        }
    }

    protected function checkChangeLog()
    {
        $this->stdio->outln('Checking the change log.');

        // read the log for the src dir
        $this->stdio->outln('Last log on src/ :');
        $this->shell('git log -1 src', $output, $return);
        $src_timestamp = $this->gitDateToTimestamp($output);

        // now read the log for meta/changes.txt
        $this->stdio->outln('Last log on CHANGES.md:');
        $this->shell('git log -1 CHANGES.md', $output, $return);
        $changes_timestamp = $this->gitDateToTimestamp($output);

        // which is older?
        if ($src_timestamp > $changes_timestamp) {
            $since = date('D M j H:i:s Y', $changes_timestamp);
            $this->stdio->outln('');
            $this->stdio->outln('File CHANGES.md is older than src/ .');
            $this->stdio->outln("Add changes from the log ...");
            $this->stdio->outln("    git log --name-only --since='$since' --reverse");
            $this->stdio->outln('... then commit the CHANGES.md file.');
            exit(1);
        }

        $this->stdio->outln('Change log looks up to date.');
    }

    protected function gitDateToTimestamp($output)
    {
        foreach ($output as $line) {
            if (substr($line, 0, 5) == 'Date:') {
                $date = trim(substr($line, 5));
                return strtotime($date);
            }
        }
        $this->stdio->outln('No date found in log.');
        exit(1);
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
            $this->stdio->outln('Not OK.');
            $this->stdio->outln('Composer file is not valid.');
            exit(1);
        }
        $this->stdio->outln('OK.');
    }

    protected function gitStatus()
    {
        $this->stdio->outln('Checking repo status.');
        $this->shell('git status --porcelain', $output, $return);
        if ($return || $output) {
            $this->stdio->outln('Not ready.');
            exit(1);
        }

        $this->stdio->outln('Status OK.');
    }

    protected function release()
    {
        if (! $this->version) {
            $this->stdio->outln('Not making a release.');
            return;
        }

        $this->stdio->outln("Releasing version {$this->version} via GitHub ... ");
        $release = (object) array(
            'tag_name' => $this->version,
            'target_commitish' => $this->branch,
            'name' => $this->version,
            'body' => file_get_contents('CHANGES.md'),
            'draft' => false,
            'prerelease' => false,
        );

        $response = $this->github->postRelease($this->package, $release);
        if (! isset($response->id)) {
            $this->stdio->outln('failure.');
            $this->stdio->outln(var_export((array) $response, true));
            exit(1);
        }

        $this->outln('success!');
    }

    protected function followup()
    {
        $this->stdio->outln('Getting the tagged release.');
        $this->shell('git pull');
        $this->followupEmail();
    }

    protected function followupEmail()
    {
        $this->stdio->out('Notifying the mailing list ... ');

        $to = 'auraphp@googlegroups.com';
        $subject = "New Release: {$this->package} {$this->version}";
        $changes = trim(file_get_contents('CHANGES.md'));
        $body = <<<BODY
Hi everyone!

We have just released {$this->package} version {$this->version}. You can get it from the usual location at <https://github.com/auraphp/{$this->package}/releases>, or update `composer.json` with the new version number. Notes from the change log follow.

* * *

{$changes}

* * *

Please let us know if you have any questions, comments, or concerns about this or any other release.  And thanks, as always, for supporting Aura!

BODY;
        $result = $this->mailer->send($to, $subject, $body);
        if (! $result) {
            $this->stdio->outln('failure.');
        } else {
            $this->stdio->outln('success.');
        }
    }
}
