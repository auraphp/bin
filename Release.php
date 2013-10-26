<?php
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
class Release extends AbstractCommand
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
    
    public function __invoke($argv)
    {
        $this->prep($argv);
        
        $this->gitCheckout();
        $this->gitPull();
        $this->runTests();
        $this->validateDocs($this->package);
        $this->touchSupportFiles();
        $this->checkChanges();
        $this->gitStatus();
        
        // check travis
        // check packagist
        
        if (! $this->version) {
            $this->outln('No version specified; done.');
            exit(0);
        }
        
        $this->outln("Prepare composer.json file for {$this->version}.");
        $this->fetchMeta();
        $this->fetchReadme();
        $this->gitBranchVersion();
        $this->writeComposer();
        
        if (! $this->commit == 'commit') {
            $this->outln('Not committing to the release.');
            $this->outln('Currently on version branch.');
            $this->outln('To kill off the branch, issue:');
            $this->outln('    git reset --hard HEAD; \\');
            $this->outln('    git checkout develop; \\');
            $this->outln("    git branch -D {$this->version}; \\");
            $this->outln('Done!');
            exit(0);
        }
        
        $this->outln('Commit to release: merge, tag, and push.');
        $this->commit();
        $this->outln('Done!');
    }
    
    protected function prep($argv)
    {
        $this->package = basename(getcwd());
        $this->outln("Package: {$this->package}");
        
        $this->branch = array_shift($argv);
        if (! $this->branch) {
            $this->branch = $this->gitCurrentBranch();
        }
        $this->outln("Branch: {$this->branch}");
        
        $this->version = array_shift($argv);
        if (! $this->version) {
            $this->outln('Pre-flight.');
            return;
        }
        
        if (! $this->isValidVersion($this->version)) {
            $this->outln("Version '{$this->version}' invalid.");
            $this->outln("Please use the format '0.1.5(-dev|-alpha0|-beta1|-RC5)'.");
            exit(1);
        }
        
        $this->outln("Version: {$this->version}");
        
        $this->commit = array_shift($argv);
    }
    
    protected function gitCheckout()
    {
        if ($this->branch == $this->gitCurrentBranch()) {
            $this->outln("Already on branch {$this->branch}.");
            return;
        }
        
        $this->outln("Checkout {$this->branch}.");
        $this->shell("git checkout {$this->branch}", $output, $return);
        if ($return) {
            exit($return);
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
        $this->outln('Run tests.');
        $cmd = 'cd tests; phpunit';
        $line = $this->shell($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->outln($line);
            exit(1);
        }
    }
    
    protected function validateDocs()
    {
        $this->outln('Validate API docs.');
        
        // remove previous validation records
        $target = "/tmp/phpdoc/{$this->package}";
        $this->shell("rm -rf {$target}");
        
        // validate
        $cmd = "phpdoc -d src/ -t {$target} --force --verbose --template=checkstyle";
        $line = $this->shell($cmd, $output, $return);
        
        // remove logs
        $this->shell('rm -f phpdoc-*.log');
        
        // validity checks don't seem to work with phpdoc. check output.
        // lines with 2 space indents look like errors.
        foreach ($output as $line) {
            if (substr($line, 0, 2) == '  ') {
                $this->outln('API docs not valid.');
                exit(1);
            }
        }
        
        // guess they're valid
        $this->outln('API docs look valid.');
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
            $this->outln('Please create a composer.json file.');
            exit(1);
        }
        
        if (! $this->isReadableFile('.travis.yml')) {
            $this->outln('Please create a .travis.yml file.');
            exit(1);
        }
    }
    
    protected function checkChanges()
    {
        $this->outln('Checking the change log.');
        
        // read the log for the src dir
        $this->outln('Last log on src/:');
        $this->shell('git log -1 src', $output, $return);
        $src_timestamp = $this->gitDateToTimestamp($output);
        
        // now read the log for meta/changes.txt
        $this->outln('Last log on meta/changes.txt:');
        $this->shell('git log -1 meta/changes.txt', $output, $return);
        $changes_timestamp = $this->gitDateToTimestamp($output);
        
        // which is older?
        if ($src_timestamp > $changes_timestamp) {
            $this->outln('');
            $this->outln('File meta/changes.txt is older than last src file.');
            $this->outln("Check the log using 'git log --name-only'");
            $this->outln('and note changes back to ' . date('D M j H:i:s Y', $src_timestamp));
            $this->outln('Then commit the meta/changes.txt file.');
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
    
    protected function fetchMeta()
    {
        $this->outln('Reading meta files.');
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
        
        $base = array(
            'type'      => null,
            'handle'    => null,
            'name'      => null,
            'email'     => null,
            'homepage'  => null,
        );
        
        $keys = array_keys($base);
        
        $authors = [];
        
        $fh = fopen($file, 'rb');
        while (($data = fgetcsv($fh, 8192, ',')) !== FALSE) {
            $author = $base;
            foreach ($data as $i => $val) {
                $author[$keys[$i]] = $val;
            }
            $authors[] = $author;
        }
        fclose($fh);
        
        if (! $authors) {
            $this->outln('not OK.');
            $this->outln('Authors file is empty. Please add at least one author.');
            exit(1);
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
            $this->outln('not OK.');
            $this->outln('Summary file is empty. Please add a one-line summary.');
            exit(1);
        }
    }
    
    protected function fetchDescription()
    {
        $file = 'meta/description.txt';
        $this->description = trim(file_get_contents($file));
        if (! $this->description) {
            $this->outln('not OK.');
            $this->outln('Description file is empty. Please add a full description.');
            exit(1);
        }
    }
    
    protected function fetchChanges()
    {
        $file = 'meta/changes.txt';
        $this->changes = file_get_contents($file);
        if (! $this->changes) {
            $this->outln('not OK.');
            $this->outln('Changes file is empty. Please add change notes.');
            exit(1);
        }
    }
    
    protected function fetchKeywords()
    {
        $file = 'meta/keywords.csv';
        $data = trim(file_get_contents($file));
        if (! $data) {
            $this->outln('not OK.');
            $this->outln('Keywords file is empty. Please add at least one keyword.');
            exit(1);
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
            $this->outln('not OK.');
            $this->outln('Require file is empty. Please add at least one require line.');
            exit(1);
        }
        
        $this->require = $require;
    }
    
    protected function fetchReadme()
    {
        $file = 'README.md';
        $this->readme = trim(file_get_contents($file));
        if (! $this->readme) {
            $this->outln('not OK.');
            $this->outln('Readme file is empty. Please add a README.md file.');
            exit(1);
        }
    }
    
    protected function writeComposer()
    {
        $this->outln('Writing composer.json ... ');
        
        $data = new \StdClass;
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
        $data->autoload['psr-0'] = [$namespace => 'src/'];
        
        // convert to json and save
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents('composer.json', $json);
        
        // validate it
        $cmd = 'composer validate';
        $result = $this->shell($cmd, $output, $return);
        if ( $return) {
            $this->outln('Not OK.');
            $this->outln('Composer file is not valid.');
            $this->outln('Still on version branch.');
            exit(1);
        }
        
        // commit it
        $cmd = 'git add composer.json; '
             . "git commit composer.json --message='updated composer'";
        
        $this->shell($cmd);
        
        // done!
        $this->composer_json = $json;
        $this->outln('OK.');
    }
    
    protected function gitStatus()
    {
        $this->outln('Checking repo status.');
        $this->shell('git status', $output, $return);
        $output = implode(PHP_EOL, $output) . PHP_EOL;
        $ok = "# On branch {$this->branch}" . PHP_EOL
            . 'nothing to commit (working directory clean)' . PHP_EOL;
        
        if ($return || $output != $ok) {
            $this->outln('Not ready.');
            exit(1);
        }
        
        $this->outln('Status OK.');
    }
    
    protected function gitBranchVersion()
    {
        $this->outln("Branching for {$this->version}.");
        $cmd = "git checkout -b {$this->version}";
        $last = $this->shell($cmd, $output, $return);
        if ($return) {
            $this->outln('Failure.');
            exit(1);
        }
        $this->outln('Success.');
    }

    protected function commit()
    {
        // switch to master
        $this->shell('git checkout master');
        
        // copy over existing composer.json because we get so many merge 
        // conflicts each time, then commit it
        file_put_contents('composer.json', $this->composer_json);
        $this->shell("git commit composer.json --message='update composer with version {$this->version}'");
        
        // now merge from the version branch
        $this->shell("git merge --no-ff {$this->version}", $output, $return);
        if ($return) {
            $this->outln('Something went wrong.');
            exit($return);
        }
        
        // delete the version branch
        $this->shell("git branch -d {$this->version}", $output, $return);
        if ($return) {
            $this->outln('Something went wrong.');
            exit($return);
        }
        
        // tag the version
        $this->shell("git tag -a '{$this->version}' -m '{$this->version}'");
        
        // push tagged version
        $this->shell('git push --tags');
        
        // push master
        $this->shell('git push');
        
        // back to original branch
        $this->shell("git checkout {$this->branch}");
    }
}
