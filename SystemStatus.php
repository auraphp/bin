<?php
/**
 * Outputs the status of "system-v1" skeleton and packages.
 * Only invoke this from a system directory.
 */
class SystemStatus extends AbstractCommand
{
	public function __invoke()
	{
		// system as a whole
		$this->outln('------------------------------');
		$this->outln();
		$this->outln('system');
		$this->outln();
		passthru('git status');

		// the package directory
		$glob = getcwd() . '/package/Aura.*';
        $dirs = glob($glob, GLOB_ONLYDIR);
        
		// for each of the repositories ...
		foreach ($dirs as $dir) {
            $this->outln('------------------------------');
            $this->outln();
            $this->outln();
	        $this->outln(basename($dir));
	        $this->outln();
	        passthru("cd $dir; git status");
		}

		// done!
        $this->outln('------------------------------');
        $this->outln();
		$this->outln('Done!');
	}
}
