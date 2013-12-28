<?php
/**
 * 
 * Works always and only on the current branch.
 * 
 * - `aura release2` to pre-flight
 * 
 * - `aura release2 $version` to dry-run and check composer
 * 
 * - `aura release2 $version commit` to release $version on GitHub
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
        $this->validateDocs($this->package);
        $this->checkChanges();
        $this->gitStatus();
        
        if (! $this->version) {
            $this->outln('No version specified; done.');
            exit(0);
        }
        
        $this->outln("Prepare composer.json file for {$this->version}.");
        $this->updateComposer();
        
        if (! $this->commit == 'commit') {
            $this->outln('Not committing to the release.');
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
        
        $this->branch = $this->gitCurrentBranch();
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
        $this->shell('rm -rf composer.lock vendor');
        $this->shell('composer install');
        $cmd = 'cd tests; phpunit';
        $line = $this->shell($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->outln($line);
            exit(1);
        }
        $this->shell('rm -rf composer.lock vendor');
    }
    
    protected function checkSupportFiles()
    {
        $files = array(
            '.travis.yml',
            'CHANGES.md',
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
    
    protected function checkChanges()
    {
        $this->outln('Checking the change log.');
        
        // read the log for the src dir
        $this->outln('Last log on src, tests, config:');
        $this->shell('git log -1 src tests config', $output, $return);
        $src_timestamp = $this->gitDateToTimestamp($output);
        
        // now read the log for meta/changes.txt
        $this->outln('Last log on CHANGES.md:');
        $this->shell('git log -1 CHANGES.md', $output, $return);
        $changes_timestamp = $this->gitDateToTimestamp($output);
        
        // which is older?
        if ($src_timestamp > $changes_timestamp) {
            $this->outln('');
            $this->outln('File CHANGES.md is older than last commit.');
            $this->outln("Check the log using 'git log --name-only'");
            $this->outln('and note changes back to ' . date('D M j H:i:s Y', $src_timestamp));
            $this->outln('Then commit the CHANGES.md file.');
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
        if (! in_array($aura_type , array('bundle', 'package', 'kernel'))) {
            $aura_type = 'library';
        }
        
        // force the composer type
        $composer->type = $aura_type;
        if ($composer->type == 'kernel' || $composer->type == 'bundle') {
            $composer->type = 'library';
        }
        
        // force the license
        $composer->license = 'BSD-2-Clause';
        
        // force the homepage
        $composer->homepage = "http://auraphp.com/{$this->package}";
        
        // force the authors
        $composer->authors = array(
            array(
                'name' => "{$this->package} Contributors",
                'homepage' => "https://github.com/auraphp/{$this->package}/contributors",
            )
        );
        
        // force the autoload
        $composer->autoload->files = array('autoload.php');
        
        // force the *aura* type
        $composer->extra->aura->type = $aura_type;
        
        // convert to json and save
        $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents('composer.json', $json . PHP_EOL);
        
        // validate it
        $cmd = 'composer validate';
        $result = $this->shell($cmd, $output, $return);
        if ( $return) {
            $this->outln('Not OK.');
            $this->outln('Composer file is not valid.');
            exit(1);
        }
        
        // // commit it
        // $cmd = "git commit composer.json --message='updated composer'";
        // $this->shell($cmd);
        
        // done!
        $this->outln('OK.');
    }
    
    protected function gitStatus()
    {
        $this->outln('Checking repo status.');
        $this->shell('git status', $output, $return);
        $output = implode(PHP_EOL, $output) . PHP_EOL;
        $ok = "# On branch {$this->branch}" . PHP_EOL
            . 'nothing to commit, working directory clean' . PHP_EOL;
        
        if ($return || $output != $ok) {
            $this->outln('Not ready.');
            exit(1);
        }
        
        $this->outln('Status OK.');
    }
    
    protected function commit()
    {
       $this->outln("Use Github Releases API to do this.");
    }
}
