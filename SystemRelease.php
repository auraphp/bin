<?php
/**
 *
 * Usage:
 *
 * - `aura system-release 1.0.0-beta2` is essentially a dry run; you release
 *   by adding a link and doing a push at the end.
 *
 */
class SystemRelease extends AbstractCommand
{
    protected $repo;

    protected $version;

    protected $composer_json = [
        'name' => 'aura/system',
        'type' => 'project',
        'description' => 'A full-stack framework system built from Aura library packages.',
        'keywords' => [
            'aura',
            'auraphp',
            'system',
            'framework'
        ],
        'license' => 'BSD-2-Clause',
        'authors' => [
            [
                'name' => 'Aura Contributors',
                'email' => 'auraphp@googlegroups.com',
                'homepage' => 'https://github.com/auraphp/system/contributors'
            ]
        ],
        'minimum-stability' => 'dev',
        'require' => [
            'aura/installer-system'  => '1.0.0',
            'aura/framework'         => '1.0.0-beta7',
            'aura/demo'              => '1.0.0-beta2',
        ],
    ];

    public function exec()
    {
        $this->setArgs();
        $this->setRepo();
        $this->removeOldStuff();
        $this->gitClone();
        $this->removeFiles();
        $this->writeComposerJson();
        $this->installViaComposer();
        $this->tarball();
        $this->moveTarballToDownloads();

        $message = "Change to the 'system' directory, "
                 . "add a link in downloads/index.md, "
                 . "commit, and push.";
        $this->outln($message);
        exit(0);
    }

    protected function setArgs()
    {
        if (! isset($_SERVER["argv"][1])) {
            $this->outln("Please specify a system version.");
            exit(1);
        }

        $this->version = $_SERVER["argv"][1];
        if (! $this->validateVersion()) {
            $this->outln("System version invalid.");
            $this->outln("Please use the format '1.2.3-rc4'.");
            exit(1);
        }

        $this->outln("System version: '{$this->version}'.");
    }

    protected function validateVersion()
    {
        $format = '^(\d+.\d+.\d+)(-(dev|alpha\d+|beta\d+|RC\d+))?$';
        preg_match("/$format/", $this->version, $matches);
        return (bool) $matches;
    }

    protected function setRepo()
    {
        $this->repo = __DIR__ . "/auraphp-system-{$this->version}";
    }

    protected function removeOldStuff()
    {
        $this->outln("Removing old systems and tarballs ... ");
        $glob = __DIR__ . "/auraphp-system-*";
        $this->shell("rm -rf $glob");
        $glob = __DIR__ . "/system";
        $this->shell("rm -rf $glob");
        $this->outln("done.");
    }

    protected function gitClone()
    {
        $this->outln("Cloning from Github ... ");
        $cmd = "git clone git@github.com:auraphp/system.git {$this->repo}";
        $this->shell($cmd);
        $this->outln("OK.");
    }

    protected function moveTarballToDownloads()
    {
        $this->outln("Adding tarball to system downloads.");
        
        // clone the system
        $cmd = "git clone git@github.com:auraphp/system.git";
        $this->shell($cmd);
        
        // check out pages
        $cmd = "cd system; git checkout gh-pages;";
        $this->shell($cmd);
        
        // move tarball to downloads
        $cmd = "mv auraphp-system-{$this->version}.tgz system/downloads/";
        $this->shell($cmd);
        
        // add to git and commit
        $cmd = "cd system; git add downloads; "
             . "git commit -a --message='added {$this->version} tarball'";
        $this->shell($cmd);
        
        $this->outln("Done.");
    }

    protected function removeFiles()
    {
        $this->outln("Removing dev files ... ");

        $files = [
            "{$this->repo}/.git",
            "{$this->repo}/.gitignore",
            "{$this->repo}/.travis.yml",
            "{$this->repo}/README.md",
            "{$this->repo}/config/_packages",
            "{$this->repo}/include/.placeholder",
            "{$this->repo}/package/.placeholder",
            "{$this->repo}/tmp/.placeholder",
            "{$this->repo}/web/cache/.placeholder",
            "~/.composer/cache*",
        ];

        foreach ($files as $file) {
            $this->shell("rm -rf {$file}");
        }
    }

    protected function writeComposerJson()
    {
        $this->outln("Writing system composer.json ... ");
        $file = $this->repo . '/composer.json';
        $json = json_encode($this->composer_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($file, $json);
        $this->outln('OK.');
    }

    protected function installViaComposer()
    {
        $this->outln("Installing packages via Composer ...");
        $cmd = "cd {$this->repo}; composer --prefer-dist install";
        $this->shell($cmd);
        $this->outln("Done.");
    }

    protected function tarball()
    {
        $this->outln("Tarballing the system ... ");
        $dir = dirname($this->repo);
        $name = basename($this->repo);
        $this->shell("cd {$dir}; tar -zcf {$name}.tgz {$name}");
        $this->outln("OK.");
    }
}
