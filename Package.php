<?php
/**
 * 
 * USE ONLY FROM THE RELEASE REPO:
 * 
 * - `aura package Vendor.Package` to run tests and docs
 * 
 * - `aura package Vendor.Package 1.0.0-beta2` to do a packaging dry-run
 * 
 * - `aura package Vendor.Package 1.0.0-beta2 release` to package, make docs,
 *   commit, tag, push, and release
 * 
 */
class Package extends AbstractCommand
{
    protected $package;
    
    protected $repo;
    
    protected $docs;
    
    protected $version;
    
    protected $release = false;
    
    protected $authors;
    
    protected $summary;
    
    protected $description;
    
    protected $changes;
    
    protected $require;
    
    protected $keywords;
    
    protected $readme;
    
    protected $composer_json;
    
    public function exec()
    {
        $this->setArgs();
        $this->setRepo();
        $this->setDocs();
        
        $this->execPrelim();
        if (! $this->version) {
          $this->outln("No packaging, no release.");
          $this->outln("Done!");
          exit(0);
        }
        
        $this->outln("Package version '{$this->version}' with pages.");
        $this->execPackage();
        $this->execPages();
        $this->outln("Done with package and pages.");
        
        if (! $this->release) {
            $this->outln("Release not requested.");
            $this->outln("Package and pages remain for inspection.");
            $this->outln("To kill off the package and pages, issue:");
            $this->outln("    cd {$this->repo}; \\");
            $this->outln("    git reset --hard HEAD; \\");
            $this->outln("    git checkout develop; \\");
            $this->outln("    git branch -D {$this->version}; \\");
            $this->outln("    cd ../..");
            $this->outln("Done!");
            exit(0);
        }
        
        $this->outln("Release: commit pages, merge master, tag, and push.");
        $this->execRelease();
        $this->outln("Done!");
        exit(0);
    }
    
    protected function execPrelim()
    {
        $this->gitCloneOrPull();
        $this->gitDevelop();
        $this->runTests();
        $this->writeDocs();
        $this->checkMeta();
        $this->checkReadme();
        $this->checkChanges();
    }
    
    protected function execPackage()
    {
        $this->fetchMeta();
        $this->fetchReadme();
        $this->gitStatus();
        $this->gitBranchVersion();
        $this->writeComposer();
    }
    
    protected function execPages()
    {
        $this->gitBranchPages();
        $this->moveVersionDocs();
        $this->writeVersionIndex();
        $this->gitAddVersionDocsAndIndex();
        $this->writePagesIndex();
    }
    
    protected function execRelease()
    {
        // currently on gh-pages; commit them
        $this->shell("cd {$this->repo}; git commit -a --message='updated docs for {$this->version}'");
        
        // switch to master
        $this->shell("cd {$this->repo}; git checkout master");
        
        // copy over existing composer.json because we get so many merge 
        // conflicts each time, then commit it
        $file = $this->repo . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($file, $this->composer_json);
        $this->shell("cd {$this->repo}; git commit composer.json --message=\"update composer with new version\"");
        
        // now merge from the version branch
        $this->shell("cd {$this->repo}; git merge --no-ff {$this->version}", $output, $return);
        if ($return) {
            $this->outln('Something went wrong.');
            exit($return);
        }
        
        // delete the version branch
        $this->shell("cd {$this->repo}; git branch -d {$this->version}", $output, $return);
        if ($return) {
            $this->outln('Something went wrong.');
            exit($return);
        }
        
        // tag the version
        $this->shell("cd {$this->repo}; git tag -a '{$this->version}' -m '{$this->version}'");
        
        // push tagged version
        $this->shell("cd {$this->repo}; git push --tags");
        
        // push master
        $this->shell("cd {$this->repo}; git push");
        
        // push pages
        $this->shell("cd {$this->repo}; git push origin gh-pages");
        
        // back to develop
        $this->shell("cd {$this->repo}; git checkout develop");
    }
    
    protected function setArgs($argv)
    {
        $this->package = array_shift($argv);
        if (! $this->package) {
            $this->outln("Please enter a Vendor.Package name.");
            exit(0);
        }
        
        $this->outln("Package: '{$this->package}'.");
        
        $this->version = array_shift($argv);
        if (! $this->version) {
            $this->outln("No package version specified; validate only.");
            return;
        }
        
        if (! $this->validateVersion()) {
            $this->outln("Package version invalid.");
            $this->outln("Please use the format '0.1.5(-dev|-alpha0|-beta1|-RC5).");
            exit(0);
        }
        
        $this->outln("Package version: '{$this->version}'.");
        
        $release = array_shift($argv);
        if ($release == 'release') {
            $this->release = true;
            $this->outln("Release requested; will package, tag, and release.");
        } else {
            $this->release = false;
            $this->outln("Release not requested; package only.");
        }
    }
    
    protected function validateVersion()
    {
        $format = '^(\d+.\d+.\d+)(-(dev|alpha\d+|beta\d+|RC\d+))?$';
        preg_match("/$format/", $this->version, $matches);
        return (bool) $matches;
    }
    
    protected function setRepo()
    {
        $this->repo = __DIR__ . "/package/{$this->package}";
    }
    
    protected function setDocs()
    {
        $this->docs = __DIR__ . "/docs/{$this->package}";
    }
    
    protected function gitCloneOrPull()
    {
        if (is_dir($this->repo)) {
            $this->outln("Pulling 'develop' branch from Github ... ");
            $cmd = "cd {$this->repo}; git pull";
        } else {
            $repo_base = dirname($this->repo);
            $this->outln("Cloning 'develop' branch from Github ... ");
            $cmd = "cd {$repo_base}; "
                 . "git clone git@github.com:auraphp/{$this->package}.git; "
                 . "cd {$this->package}; "
                 . "git checkout gh-pages; "
                 . "git checkout develop; ";
        }
        
        $this->shell($cmd, $output, $return);
        $this->outln("OK.");
    }
    
    protected function gitDevelop()
    {
        $this->outln("Checking branch ... ");
        $cmd = "cd {$this->repo}; git status";
        $last = $this->shell($cmd, $output, $return);
        if (strpos($output[0], "branch develop") === false) {
            $this->outln("not OK.");
            $this->outln("Repo is not on branch develop.");
            exit(1);
        }
        $this->outln("OK.");
    }
    
    protected function runTests()
    {
        $this->outln("Running tests ... ");
        $cmd = "cd {$this->repo}/tests; phpunit";
        $line = $this->shell($cmd, $output, $return);
        if ($return == 1 || $return == 2) {
            $this->outln("not OK.");
            $this->outln($line);
            exit(1);
        }
        
        $this->outln("OK.");
    }
    
    protected function writeDocs()
    {
        $this->outln("Writing inline API docs ... ");
        
        $this->shell("rm -rf {$this->docs}");
        $cmd = "phpdoc -d {$this->repo}/src -t {$this->docs} --force --validate --ignore=*/views/,*/layouts/";
        $line = $this->shell($cmd, $output, $return);
        
        // docblox does not use a return code or a last line, so we need to
        // count output lines. errors means there will be more than 6 lines.
        if (count($output) > 6) {
            $this->outln("not OK.");
            $this->outln("Run '$cmd -v' for more information.");
            exit(1);
        }
        
        $this->outln("OK.");
    }
    
    protected function checkMeta()
    {
        $meta = "{$this->repo}/meta";
        if (! is_dir($meta)) {
            mkdir($meta, 0755, true);
        }
        
        $files = array(
            "{$meta}/authors.csv",
            "{$meta}/changes.txt",
            "{$meta}/description.txt",
            "{$meta}/keywords.csv",
            "{$meta}/require.csv",
            "{$meta}/summary.txt",
        );
        
        foreach ($files as $file) {
            if (! is_readable($file)) {
                touch($file);
            }
        }
    }
    
    protected function checkReadme()
    {
        $file = "{$this->repo}/README.md";
        if (! is_readable($file)) {
            touch($file);
        }
    }
    
    protected function checkChanges()
    {
        $this->outln("Checking the change log ...");
        
        // read the log for the src dir
        $this->shell("cd {$this->repo}; git log -1 src", $output, $return);
        $src_timestamp = $this->gitDateToTimestamp($output);
        
        $this->outln('');
        
        // now read the log for meta/changes.txt
        $this->shell("cd {$this->repo}; git log -1 meta/changes.txt", $output, $return);
        $changes_timestamp = $this->gitDateToTimestamp($output);
        
        // which is older?
        if ($src_timestamp > $changes_timestamp) {
            $this->outln('');
            $this->outln('NOTICE: Have you updated meta/changes.txt? Check the log:');
            $this->outln('');
            $this->outln("    cd {$this->repo}; git log --name-only");
            $this->outln('');
            sleep(3);
        } else {
            $this->outln('OK.');
        }
    }
    
    protected function gitDateToTimestamp($output)
    {
        foreach ($output as $line) {
            if (substr($line, 0, 5) == 'Date:') {
                $date = trim(substr($line, 5));
                return strtotime($date);
            }
        }
        $this->outln("No date found in log.");
        exit(1);
    }
    
    protected function fetchMeta()
    {
        $this->outln("Reading meta files ... ");
        $this->fetchAuthors();
        $this->fetchSummary();
        $this->fetchDescription();
        $this->fetchChanges();
        $this->fetchKeywords();
        $this->fetchRequire();
        $this->outln("OK.");
    }
    
    protected function fetchAuthors()
    {
        $file = "{$this->repo}/meta/authors.csv";
        
        $base = array(
            "type"      => null,
            "handle"    => null,
            "name"      => null,
            "email"     => null,
            "homepage"  => null,
        );
        
        $keys = array_keys($base);
        
        $authors = [];
        
        $fh = fopen($file, "rb");
        while (($data = fgetcsv($fh, 8192, ",")) !== FALSE) {
            $author = $base;
            foreach ($data as $i => $val) {
                $author[$keys[$i]] = $val;
            }
            $authors[] = $author;
        }
        fclose($fh);
        
        if (! $authors) {
            $this->outln("not OK.");
            $this->outln("Authors file is empty. Please add at least one author.");
            exit(1);
        }
        
        $base = [[
            "name"     => "{$this->package} Contributors",
            "homepage" => "https://github.com/auraphp/{$this->package}/contributors",
        ]];

        $this->authors = array_merge($base, $authors);
    }
    
    protected function fetchSummary()
    {
        $file = "{$this->repo}/meta/summary.txt";
        $this->summary = trim(file_get_contents($file));
        if (! $this->summary) {
            $this->outln("not OK.");
            $this->outln("Summary file is empty. Please add a one-line summary.");
            exit(1);
        }
    }
    
    protected function fetchDescription()
    {
        $file = "{$this->repo}/meta/description.txt";
        $this->description = trim(file_get_contents($file));
        if (! $this->description) {
            $this->outln("not OK.");
            $this->outln("Description file is empty. Please add a full description.");
            exit(1);
        }
    }
    
    protected function fetchChanges()
    {
        $file = "{$this->repo}/meta/changes.txt";
        $this->changes = file_get_contents($file);
        if (! $this->changes) {
            $this->outln("not OK.");
            $this->outln("Changes file is empty. Please add change notes.");
            exit(1);
        }
    }
    
    protected function fetchKeywords()
    {
        $file = "{$this->repo}/meta/keywords.csv";
        $data = trim(file_get_contents($file));
        if (! $data) {
            $this->outln("not OK.");
            $this->outln("Keywords file is empty. Please add at least one keyword.");
            exit(1);
        }
        $words = explode(",", $data);
        foreach ($words as $word) {
            $this->keywords[] = trim($word);
        }
    }
    
    protected function fetchRequire()
    {
        $file = "{$this->repo}/meta/require.csv";
        $require = array();
        
        $fh = fopen($file, "rb");
        while (($data = fgetcsv($fh, 8192, ",")) !== FALSE) {
            $require[$data[0]] = $data[1];
        }
        fclose($fh);
        
        if (! $require) {
            $this->outln("not OK.");
            $this->outln("Require file is empty. Please add at least one require line.");
            exit(1);
        }
        
        $this->require = $require;
    }
    
    protected function fetchReadme()
    {
        $file = "{$this->repo}/README.md";
        $this->readme = trim(file_get_contents($file));
        if (! $this->readme) {
            $this->outln("not OK.");
            $this->outln("Readme file is empty. Please add a README.md file.");
            exit(1);
        }
    }
    
    protected function writeComposer()
    {
        $this->outln("Writing composer.json ... ");
        
        $data = new \StdClass;
        $data->name         = str_replace(".", "/", strtolower($this->package));
        $data->version      = $this->version;
        $data->type         = "aura-package";
        $data->description  = $this->description;
        $data->keywords     = $this->keywords;
        $data->homepage     = "http://auraphp.com/{$this->package}";
        $data->time         = date("Y-m-d");
        $data->license      = "BSD-2-Clause";
        
        $data->authors = [];
        foreach ($this->authors as $author) {
            unset($author["type"]);
            unset($author["handle"]);
            $data->authors[] = $author;
        }
        
        $data->require = $this->require;
        
        $namespace = str_replace('.', '\\', $this->package);
        $data->autoload["psr-0"] = [$namespace => "src/"];
        
        // convert to json and save
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $file = "{$this->repo}/composer.json";
        file_put_contents($file, $json);
        
        // validate it
        $cmd = "cd {$this->repo}; composer validate";
        $result = $this->shell($cmd);
        if (trim($result) != './composer.json is valid') {
            $this->outln('Not OK.');
            $this->outln('Composer file is not valid.');
            exit(1);
        }
        
        // commit it
        $cmd = "cd {$this->repo}; "
             . "git add composer.json; "
             . "git commit composer.json --message='updated composer'";
        
        $this->shell($cmd);
        
        // done!
        $this->composer_json = $json;
        $this->outln("OK.");
    }
    
    protected function gitStatus()
    {
        $this->outln("Checking status ... ");
        $this->shell("cd {$this->repo}; git status", $output, $return);
        $output = implode(PHP_EOL, $output) . PHP_EOL;
        $ok = "# On branch develop" . PHP_EOL
            . "nothing to commit (working directory clean)" . PHP_EOL;
        
        if ($return || $output != $ok) {
            $this->outln("not OK.");
            $this->out($output);
            exit(1);
        }
        
        // done
        $this->outln("OK.");
    }
    
    protected function gitBranchVersion()
    {
        $this->outln("Branching for {$this->version} ... ");
        $cmd = "cd {$this->repo}; git branch {$this->version}; git checkout {$this->version}";
        $last = $this->shell($cmd, $output, $return);
        if ($return) {
            $this->outln("not OK.");
            $this->outln($last);
            exit(1);
        }
        $this->outln("OK.");
    }
    
    protected function gitBranchPages()
    {
        $this->shell("cd {$this->repo}; git checkout gh-pages");
    }
    
    protected function moveVersionDocs()
    {
        $this->outln("Moving version API docs ... ");
        $source = "{$this->docs}/*";
        $target = "{$this->repo}/version/{$this->version}/api/";
        $this->shell("mkdir -p $target");
        $this->shell("mv $source $target");
        $this->outln(" OK.");
    }
    
    protected function writeVersionIndex()
    {
        $this->outln("Writing version index ... ");
        $file = "{$this->repo}/version/{$this->version}/index.md";
        $text = [
            "---",
            "title: Aura for PHP -- {$this->summary}",
            "layout: default",
            "---",
            "",
            $this->changeHighlightingTags($this->readme),
        ];
        file_put_contents($file, implode(PHP_EOL, $text));
        $this->outln("OK.");
    }
    
    protected function changeHighlightingTags($text)
    {
        $text = preg_replace("/```php/", "{% highlight php %}", $text);
        $text = preg_replace("/```/", "{% endhighlight %}", $text);
        return $text;
    }
    
    protected function gitAddVersionDocsAndIndex()
    {
        $this->outln("Adding version API docs and index ... ");
        $this->shell("cd {$this->repo}; git add index.md; git add version/{$this->version}");
        $this->outln("OK.");
    }
    
    protected function writePagesIndex()
    {
        $this->outln("Writing pages index ... ");
        
        $text = [
            "---",
            "title: Aura for PHP -- {$this->summary}",
            "layout: default",
            "---",
            "",
            $this->package,
            str_repeat("=", strlen($this->package)),
            "",
            $this->description,
            "",
            "Versions",
            "--------",
            "",
            "- `develop` : <https://github.com/auraphp/{$this->package}/tree/develop>",
            "",
            "- `master` : <https://github.com/auraphp/{$this->package}>",
            "",
        ];
        
        // look for versions
        $items = glob("{$this->repo}/version/*", GLOB_ONLYDIR);
        $list = [];
        
        // make a list of versions
        foreach ($items as $item) {
            $version = basename($item);
            $list[] = "- `{$version}` : "
                    . "[.zip](https://github.com/auraphp/{$this->package}/zipball/{$version}), "
                    . "[.tar.gz](https://github.com/auraphp/{$this->package}/tarball/{$version}), "
                    . "[readme](version/{$version}/), "
                    . "[api](version/{$version}/api/)" . PHP_EOL;
        }
        
        $list = array_reverse($list);
        $text = array_merge($text, $list);
        $text[] = "";
        
        // write to file
        $file = "{$this->repo}/index.md";
        file_put_contents($file, implode(PHP_EOL, $text));
        
        // done
        $this->outln("OK.");
    }
}
