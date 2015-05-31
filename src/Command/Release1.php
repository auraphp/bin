<?php
namespace Aura\Bin\Command;

use Aura\Bin\Exception;

/**
 *
 * - `aura release` to preflight on the current branch
 *
 * - `aura release $branch` to preflight on $branch
 *
 * - `aura release $branch $version` to dry run and check composer
 *
 * - `aura release $branch $version commit` to merge $version to master and
 *   tag it as $version.  HEY NOW: GITHUB RELEASES. Does this mean we can
 *   avoid the whole merge-to-master thing?  What does 'master' mean in that
 *   case anyway?
 *
 */
class Release1 extends AbstractCommand
{
    protected $package;

    protected $branch;

    protected $version;

    protected $authors;

    protected $summary;

    protected $description;

    protected $changes;

    protected $require;

    protected $keywords;

    protected $readme;

    protected $composer_json;

    protected $phpdoc;

    protected $phpunit;

    public function setPhpdoc($phpdoc)
    {
        $this->phpdoc = $phpdoc;
    }

    public function setPhpunit($phpunit)
    {
        $this->phpunit = $phpunit;
    }

    public function __invoke()
    {
        $this->prep();
        $this->gitCheckout();
        $this->gitPull();
        $this->phpunit->v1();
        $this->phpdoc->validate($this->package);
        $this->touchSupportFiles();

        if (! $this->checkChanges()) {
            return 1;
        }

        $this->gitStatus();

        // check travis
        // check packagist

        if (! $this->version) {
            $this->stdio->outln('No version specified; done.');
            return;
        }

        $this->stdio->outln("Prepare composer.json file for {$this->version}.");
        $this->fetchMeta();
        $this->fetchReadme();
        $this->gitBranchVersion();
        $this->writeComposer();

        if (! $this->commit == 'commit') {
            $this->stdio->outln('Not committing to the release.');
            $this->stdio->outln('Currently on version branch.');
            $this->stdio->outln('To kill off the branch, issue:');
            $this->stdio->outln('    git reset --hard HEAD; \\');
            $this->stdio->outln('    git checkout develop; \\');
            $this->stdio->outln("    git branch -D {$this->version}; \\");
            $this->stdio->outln('Done!');
            return;
        }

        $this->stdio->outln('Commit to release: merge, tag, and push.');
        $this->commit();
        $this->stdio->outln('Done!');
    }

    protected function prep()
    {
        $argv = $this->getArgv();

        $this->package = basename(getcwd());
        $this->stdio->outln("Package: {$this->package}");

        $this->branch = array_shift($argv);
        if (! $this->branch) {
            $this->branch = $this->gitCurrentBranch();
        }
        $this->stdio->outln("Branch: {$this->branch}");

        $this->version = array_shift($argv);
        if (! $this->version) {
            $this->stdio->outln('Pre-flight.');
            return;
        }

        if (! $this->isValidVersion($this->version)) {
            $message = "Version '{$this->version}' invalid. "
                     . "Please use the format '0.1.5(-dev|-alpha0|-beta1|-RC5)'.";
            throw new Exception($message);
        }

        $this->stdio->outln("Version: {$this->version}");
        $this->commit = array_shift($argv);
    }

    protected function gitCheckout()
    {
        if ($this->branch == $this->gitCurrentBranch()) {
            $this->stdio->outln("Already on branch {$this->branch}.");
            return;
        }

        $this->stdio->outln("Checkout {$this->branch}.");
        $this->shell("git checkout {$this->branch}", $output, $return);
        if ($return) {
            throw new Exception('', $return);
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

    protected function touchSupportFiles()
    {
        $file = 'README.md';
        if (! $this->isReadableFile($file)) {
            touch($file);
        }

        $meta = 'meta';
        if (! is_dir($meta)) {
            mkdir($meta, 0755, true);
        }

        $files = array(
            'meta/authors.csv',
            'meta/changes.txt',
            'meta/description.txt',
            'meta/keywords.csv',
            'meta/require.csv',
            'meta/summary.txt',
        );

        foreach ($files as $file) {
            if (! $this->isReadableFile($file)) {
                touch($file);
            }
        }

        if (! $this->isReadableFile('composer.json')) {
            throw new Exception('Please create a composer.json file.');
        }

        if (! $this->isReadableFile('.travis.yml')) {
            throw new Exception('Please create a .travis.yml file.');
        }
    }

    protected function checkChanges()
    {
        $this->stdio->outln('Checking the change log.');

        // read the log for the src dir
        $this->stdio->outln('Last log on src/:');
        $this->shell('git log -1 src', $output, $return);
        $src_timestamp = $this->gitDateToTimestamp($output);

        // now read the log for meta/changes.txt
        $this->stdio->outln('Last log on meta/changes.txt:');
        $this->shell('git log -1 meta/changes.txt', $output, $return);
        $changes_timestamp = $this->gitDateToTimestamp($output);

        // which is older?
        if ($src_timestamp > $changes_timestamp) {
            $this->stdio->outln('');
            $this->stdio->outln('File meta/changes.txt is older than last src file.');
            $this->stdio->outln("Check the log using 'git log --name-only'");
            $this->stdio->outln('and note changes back to ' . date('D M j H:i:s Y', $src_timestamp));
            $this->stdio->outln('Then commit the meta/changes.txt file.');
            return false;
        }

        $this->stdio->outln('Change log looks up to date.');
        return true;
    }

    protected function fetchMeta()
    {
        $this->stdio->outln('Reading meta files.');
        $this->fetchAuthors();
        $this->fetchSummary();
        $this->fetchDescription();
        $this->fetchChanges();
        $this->fetchKeywords();
        $this->fetchRequire();
    }

    protected function fetchAuthors()
    {
        $file = 'meta/authors.csv';

        $keys = array(
            'type',
            'handle',
            'name',
            'email',
            'homepage',
        );

        $authors = [];

        $fh = fopen($file, 'rb');
        while (($data = fgetcsv($fh, 8192, ',')) !== FALSE) {
            $author = array();
            foreach ($data as $i => $val) {
                if ($val) {
                    $author[$keys[$i]] = $val;
                }
            }
            $authors[] = $author;
        }
        fclose($fh);

        if (! $authors) {
            $this->stdio->outln('not OK.');
            throw new Exception('Authors file is empty. Please add at least one author.');
        }

        $base = [[
            'name'     => "{$this->package} Contributors",
            'homepage' => "https://github.com/auraphp/{$this->package}/contributors",
        ]];

        $this->authors = array_merge($base, $authors);
    }

    protected function fetchSummary()
    {
        $file = 'meta/summary.txt';
        $this->summary = trim(file_get_contents($file));
        if (! $this->summary) {
            $this->stdio->outln('not OK.');
            throw new Exception('Summary file is empty. Please add a one-line summary.');
        }
    }

    protected function fetchDescription()
    {
        $file = 'meta/description.txt';
        $this->description = trim(file_get_contents($file));
        if (! $this->description) {
            $this->stdio->outln('not OK.');
            throw new Exception('Description file is empty. Please add a full description.');
        }
    }

    protected function fetchChanges()
    {
        $file = 'meta/changes.txt';
        $this->changes = file_get_contents($file);
        if (! $this->changes) {
            $this->stdio->outln('not OK.');
            throw new Exception('Changes file is empty. Please add change notes.');
        }
    }

    protected function fetchKeywords()
    {
        $file = 'meta/keywords.csv';
        $data = trim(file_get_contents($file));
        if (! $data) {
            $this->stdio->outln('not OK.');
            throw new Exception('Keywords file is empty. Please add at least one keyword.');
        }
        $words = explode(',', $data);
        foreach ($words as $word) {
            $this->keywords[] = trim($word);
        }
    }

    protected function fetchRequire()
    {
        $file = 'meta/require.csv';
        $require = array();

        $fh = fopen($file, 'rb');
        while (($data = fgetcsv($fh, 8192, ',')) !== FALSE) {
            $require[$data[0]] = $data[1];
        }
        fclose($fh);

        if (! $require) {
            $this->stdio->outln('not OK.');
            throw new Exception('Require file is empty. Please add at least one require line.');
        }

        $this->require = $require;
    }

    protected function fetchReadme()
    {
        $file = 'README.md';
        $this->readme = trim(file_get_contents($file));
        if (! $this->readme) {
            $this->stdio->outln('not OK.');
            throw new Exception('Readme file is empty. Please add a README.md file.');
        }
    }

    protected function writeComposer()
    {
        $this->stdio->outln('Updating composer.json ... ');

        $data = json_decode(file_get_contents('composer.json'));
        $data->name         = str_replace('.', '/', strtolower($this->package));
        $data->version      = $this->version;
        $data->type         = 'aura-package';
        $data->description  = $this->description;
        $data->keywords     = $this->keywords;
        $data->homepage     = "http://auraphp.com/{$this->package}";
        $data->time         = date('Y-m-d');
        $data->license      = 'BSD-2-Clause';

        $data->authors = [];
        foreach ($this->authors as $author) {
            unset($author['type']);
            unset($author['handle']);
            $data->authors[] = $author;
        }

        $data->require = $this->require;

        $namespace = str_replace('.', '\\', $this->package);

        // convert to json and save
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents('composer.json', $json);

        // validate it
        $cmd = 'composer validate';
        $result = $this->shell($cmd, $output, $return);
        if ( $return) {
            $this->stdio->outln('Not OK.');
            $this->stdio->outln('Still on version branch.');
            throw new Exception('Composer file is not valid.');
        }

        // commit it
        $cmd = 'git add composer.json; '
             . "git commit composer.json --message='updated composer'";

        $this->shell($cmd);

        // done!
        $this->composer_json = $json;
        $this->stdio->outln('OK.');
    }

    protected function gitStatus()
    {
        $this->stdio->outln('Checking repo status.');
        $this->shell('git status', $output, $return);
        $output = implode(PHP_EOL, $output) . PHP_EOL;
        $ok = "On branch {$this->branch}" . PHP_EOL
            . "Your branch is up-to-date with 'origin/{$this->branch}'." . PHP_EOL
            . 'nothing to commit, working directory clean' . PHP_EOL;

        if ($return || $output != $ok) {
            throw new Exception('Not ready.');
        }

        $this->stdio->outln('Status OK.');
    }

    protected function gitBranchVersion()
    {
        $this->stdio->outln("Branching for {$this->version}.");
        $cmd = "git checkout -b {$this->version}";
        $last = $this->shell($cmd, $output, $return);
        if ($return) {
            throw new Exception('Failure.', $return);
        }
        $this->stdio->outln('Success.');
    }

    protected function commit()
    {
        // switch to 1.x
        $this->shell('git checkout 1.x');

        // copy over existing composer.json because we get so many merge
        // conflicts each time, then commit it
        file_put_contents('composer.json', $this->composer_json);
        $this->shell("git commit composer.json --message='update composer with version {$this->version}'");

        // now merge from the version branch
        $this->shell("git merge --no-ff {$this->version}", $output, $return);
        if ($return) {
            throw new Exception('Something went wrong.');
        }

        // delete the version branch
        $this->shell("git branch -d {$this->version}", $output, $return);
        if ($return) {
            throw new Exception('Something went wrong.');
        }

        // tag the version
        $this->shell("git tag -a '{$this->version}' -m '{$this->version}'");

        // push tagged version
        $this->shell('git push --tags');

        // push 1.x
        $this->shell('git push');

        // back to original branch
        $this->shell("git checkout {$this->branch}");
    }
}
