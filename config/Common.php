<?php
namespace Aura\Cli_Project\_Config;

use Aura\Di\Config;
use Aura\Di\Container;

class Common extends Config
{
    public function define(Container $di)
    {
        $di->set('aura/project-kernel:logger', $di->newInstance('Monolog\Logger'));

        $di->params['Aura\Bin\Config']['env'] = $_ENV;
        $di->params['Aura\Bin\AbstractCommand']['config'] = $di->lazyNew('Aura\Bin\Config');
    }

    public function modify(Container $di)
    {
        $this->modifyLogger($di);
        $this->modifyCliDispatcher($di);
        $this->modifyCliHelpService($di);
    }

    protected function modifyLogger(Container $di)
    {
        $project = $di->get('project');
        $mode = $project->getMode();
        $file = $project->getPath("tmp/log/{$mode}.log");

        $logger = $di->get('aura/project-kernel:logger');
        $logger->pushHandler($di->newInstance(
            'Monolog\Handler\StreamHandler',
            array(
                'stream' => $file,
            )
        ));
    }

    protected function modifyCliDispatcher(Container $di)
    {
        $dispatcher = $di->get('aura/cli-kernel:dispatcher');
        $dispatcher->addObjects(array(
            'docs'              => $di->lazyNew('Aura\Bin\Docs'),
            'issues'            => $di->lazyNew('Aura\Bin\Issues'),
            'log-since-release' => $di->lazyNew('Aura\Bin\LogSinceRelease'),
            'packagist'         => $di->lazyNew('Aura\Bin\Packagist'),
            'packages-table'    => $di->lazyNew('Aura\Bin\PackagesTable'),
            'release1'          => $di->lazyNew('Aura\Bin\Release'),
            'release2'          => $di->lazyNew('Aura\Bin\Release2'),
            'release1-pages'    => $di->lazyNew('Aura\Bin\ReleasePages'),
            'repos'             => $di->lazyNew('Aura\Bin\Repos'),
            'system-release'    => $di->lazyNew('Aura\Bin\SystemRelease'),
            'system-status'     => $di->lazyNew('Aura\Bin\SystemStatus'),
            'system-update'     => $di->lazyNew('Aura\Bin\SystemUpdate'),
            'travis'            => $di->lazyNew('Aura\Bin\Travis'),
        ));
    }

    protected function modifyCliHelpService(Container $di)
    {
        $help_service = $di->get('aura/cli-kernel:help_service');

        $help = $di->newInstance('Aura\Cli\Help');
        $help_service->set('hello', function () use ($help) {
            $help->setUsage(array('', '<noun>'));
            $help->setSummary("A demonstration 'hello world' command.");
            return $help;
        });
    }
}
