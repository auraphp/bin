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

        $di->params['Aura\Bin\Github']= array(
            'user' => $_ENV['AURA_BIN_GITHUB_USER'],
            'token' => $_ENV['AURA_BIN_GITHUB_TOKEN'],
        );

        $di->params['Aura\Bin\AbstractCommand'] = array(
            'config' => $di->lazyNew('Aura\Bin\Config'),
            'context' => $di->lazyGet('aura/cli-kernel:context'),
            'stdio' => $di->lazyGet('aura/cli-kernel:stdio'),
            'github' => $di->lazyNew('Aura\Bin\Github'),
        );

        $di->setter['Aura\Bin\Command\AbstractCommand']['setPhpdoc'] = $di->lazyNew('Aura\Bin\Shell\Phpdoc');
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
        $di->get('aura/cli-kernel:dispatcher')->addObjects(array(
            'docs'              => $di->lazyNew('Aura\Bin\Command\Docs'),
            'issues'            => $di->lazyNew('Aura\Bin\Command\Issues'),
            'log-since-release' => $di->lazyNew('Aura\Bin\Command\LogSinceRelease'),
            'packagist'         => $di->lazyNew('Aura\Bin\Command\Packagist'),
            'packages-table'    => $di->lazyNew('Aura\Bin\Command\PackagesTable'),
            'release1'          => $di->lazyNew('Aura\Bin\Command\Release'),
            'release2'          => $di->lazyNew('Aura\Bin\Command\Release2'),
            'release1-pages'    => $di->lazyNew('Aura\Bin\Command\ReleasePages'),
            'repos'             => $di->lazyNew('Aura\Bin\Command\Repos'),
            'system-release'    => $di->lazyNew('Aura\Bin\Command\SystemRelease'),
            'system-status'     => $di->lazyNew('Aura\Bin\Command\SystemStatus'),
            'system-update'     => $di->lazyNew('Aura\Bin\Command\SystemUpdate'),
            'travis'            => $di->lazyNew('Aura\Bin\Command\Travis'),
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
