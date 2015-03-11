<?php
namespace Aura\Bin\Command;

class Docs extends AbstractCommand
{
    protected $phpdoc;

    public function setPhpdoc($phpdoc)
    {
        $this->phpdoc = $phpdoc;
    }

    public function __invoke()
    {
        $package = basename(getcwd());
        $this->phpdoc->validate($package);
    }
}
