<?php
namespace Aura\Bin\Command;

use Aura\Bin\Exception;

class ShowReleaseNotes extends AbstractCommand
{
    public function __invoke()
    {
        $this->stdio->outln($this->getReleaseNotes());
    }
}
