<?php
namespace Aura\Bin\Command;

/**
 * # do this for each new release:
 * cd $PACKAGE
 * git checkout $VERSION
 * aura release-pages
 * git checkout develop
 * # then add, commit, and push the aura site
 */
class ReleasePages extends AbstractCommand
{
    protected $site_dir;
    protected $composer;
    protected $package;
    protected $package_dir;
    protected $version;
    protected $version_dir;
    protected $commit;

    public function __invoke()
    {
        $this->prep();
        $this->readComposer();
        $this->makePackageDir();
        $this->makeVersionDir();
        $this->writeVersionIndex();
        $this->phpdoc->writeVersionApi($this->version_dir);
        $this->writePackageIndex();

        if ($this->commit != 'commit') {
            $this->stdio->outln('Not committing the new pages.');
            exit(0);
        }

        $this->commit();
        $this->stdio->outln('Pages committed.');
    }

    protected function prep()
    {
        $argv = $this->getArgv();

        // where's the site directory?
        $this->site_dir = $this->config->site_dir;
        if (! $this->site_dir) {
            $this->stdio->outln('Please set the site_dir in your .aurarc file.');
            exit(1);
        }

        if (! is_dir($this->site_dir)) {
            $this->stdio->outln('Base site directory does not exist:');
            $this->stdio->outln($this->site_dir);
            exit(1);
        }

        // the package name
        $this->package = basename(getcwd());

        // commit flag
        $this->commit = array_shift($argv);
    }

    protected function readComposer()
    {
        if (! $this->isReadableFile('composer.json')) {
            $this->stdio->outln('No composer file available.');
            exit(1);
        }
        $this->composer = json_decode(file_get_contents('composer.json'));
    }

    protected function makePackageDir()
    {
        $this->package_dir = $this->site_dir . "/packages/{$this->package}";
        if (is_dir($this->package_dir)) {
            $this->stdio->outln('Package dir exists.');
            return;
        }

        $this->stdio->outln('Make package dir.');
        $this->shell("mkdir -p $this->package_dir", $output, $return);
        if ($return) {
            $this->stdio->outln('Failed.');
            exit(1);
        }
    }

    protected function makeVersionDir()
    {
        $this->version = $this->composer->version;
        $this->stdio->outln("Version: $this->version");

        $this->version_dir = $this->package_dir . "/{$this->version}";

        // don't overwrite an existing version dir
        if (is_dir($this->version_dir)) {
            $this->stdio->outln('Package version directory already exists.');
            exit(1);
        }

        $this->stdio->outln('Creating package version directory.');
        $this->shell("mkdir $this->version_dir", $output, $return);
        if ($return) {
            $this->stdio->outln('Failed.');
            exit(1);
        }
    }

    protected function writeVersionIndex()
    {
        $this->stdio->outln("Writing version index.");
        if (! $this->isReadableFile('README.md')) {
            $this->stdio->outln('No README.md file.');
            exit(1);
        }

        $text = trim(file_get_contents('README.md'));
        $text = preg_replace('/^\[\!\[Build Status.*$/', '', $text);
        $text = preg_replace("/```php/", "{% highlight php %}", $text);
        $text = preg_replace("/```/", "{% endhighlight %}", $text);
        $text = "---" . PHP_EOL
                . "title: Aura for PHP -- {$this->package} {$this->version}" . PHP_EOL
                . "layout: site" . PHP_EOL
                . "active: packages" . PHP_EOL
                . "---" . PHP_EOL
                . PHP_EOL
                . $text . PHP_EOL;

        $file = $this->version_dir . '/index.md';
        $ok = file_put_contents($file, $text);
        if (! $ok) {
            $this->stdio->outln('Failed.');
            exit(1);
        }
    }

    protected function writePackageIndex()
    {
        $this->stdio->outln("Writing package index.");

        $text = [
            "---",
            "title: Aura for PHP -- {$this->package}",
            "layout: site",
            "active: packages",
            "---",
            "",
            $this->package,
            str_repeat("=", strlen($this->package)),
            "",
            $this->composer->description,
            "",
            "Branches",
            "--------",
            "",
            "- `develop` : <https://github.com/auraphp/{$this->package}/tree/develop>",
            "",
            "- `master` : <https://github.com/auraphp/{$this->package}/tree/master>",
            "",
            "Releases",
            "--------",
            "",
        ];

        $versions = $this->readSortedVersions();
        foreach ($versions as $version) {
            $list[] = "- `{$version}` : "
                    . "[.zip](https://github.com/auraphp/{$this->package}/zipball/{$version}), "
                    . "[.tar.gz](https://github.com/auraphp/{$this->package}/tarball/{$version}), "
                    . "[readme]({$version}/), "
                    . "[api]({$version}/api/)" . PHP_EOL;
        }

        $text = array_merge($text, $list);

        // write to file
        $file = $this->package_dir . "/index.md";
        $ok = file_put_contents($file, implode(PHP_EOL, $text));
        if (! $ok) {
            $this->stdio->outln('Failed.');
            exit(1);
        }
    }

    protected function readSortedVersions()
    {
        $dirs = glob("{$this->package_dir}/*", GLOB_ONLYDIR);
        $versions = [];
        foreach ($dirs as $dir) {
            $versions[] = basename($dir);
        }
        usort($versions, 'version_compare');
        return array_reverse($versions);
    }

    protected function commit()
    {
        $this->stdio->outln('Committing the new pages.');
        $orig = getcwd();

        chdir($this->site_dir);
        $this->shell("git pull");

        $dir = "packages/{$this->package}/{$this->version}";
        $this->shell("git add $dir");
        $this->shell("git commit $dir --message='add {$this->package} {$this->version}'");

        $file = "packages/{$this->package}/index.md";
        $this->shell("git add $file");
        $this->shell("git commit $file --message='update {$this->package} index'");

        $this->shell("git push");

        chdir($orig);
    }
}
