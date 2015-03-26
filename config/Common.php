<?php
namespace Aura\Cli_Project\_Config;

use Aura\Di\Config;
use Aura\Di\Container;

class Common extends Config
{
    public function define(Container $di)
    {
        /**
         * Services
         */
        $di->set('aura/project-kernel:logger', $di->newInstance('Monolog\Logger'));

        /**
         * Aura\Bin\Command\AbstractCommand
         */
        $di->params['Aura\Bin\Command\AbstractCommand'] = array(
            'config' => $di->lazyNew('Aura\Bin\Config'),
            'context' => $di->lazyGet('aura/cli-kernel:context'),
            'stdio' => $di->lazyGet('aura/cli-kernel:stdio'),
            'github' => $di->lazyNew('Aura\Bin\Github'),
        );

        /**
         * Aura\Bin\Command\Docs
         */
        $di->setter['Aura\Bin\Command\Docs']['setPhpdoc'] = $di->lazyNew('Aura\Bin\Shell\Phpdoc');

        /**
         * Aura\Bin\Command\Release
         */
        $di->setter['Aura\Bin\Command\Release']['setPhpdoc'] = $di->lazyNew('Aura\Bin\Shell\Phpdoc');
        $di->setter['Aura\Bin\Command\Release']['setPhpunit'] = $di->lazyNew('Aura\Bin\Shell\Phpunit');

        /**
         * Aura\Bin\Command\Release2
         */
        $di->setter['Aura\Bin\Command\Release2']['setPhpdoc'] = $di->lazyNew('Aura\Bin\Shell\Phpdoc');
        $di->setter['Aura\Bin\Command\Release2']['setPhpunit'] = $di->lazyNew('Aura\Bin\Shell\Phpunit');
        $di->setter['Aura\Bin\Command\Release2']['setMailer'] = $di->lazyNew('Aura\Bin\Mailer');
        $di->setter['Aura\Bin\Command\Release2']['setTweeter'] = $di->lazyNew('Aura\Bin\Tweeter');

        /**
         * Aura\Bin\Config
         */
        $di->params['Aura\Bin\Config']['env'] = $_ENV;

        /**
         * Aura\Bin\Github
         */
        $di->params['Aura\Bin\Github']= array(
            'user' => $_ENV['AURA_BIN_GITHUB_USER'],
            'token' => $_ENV['AURA_BIN_GITHUB_TOKEN'],
        );

        /**
         * Aura\Bin\Mailer
         */
        $di->params['Aura\Bin\Mailer']['config'] = $di->lazyNew('Aura\Bin\Config');

        $di->params['Aura\Bin\Mailer']['mailer'] = $di->lazy(function () {
            $transport = \Swift_SmtpTransport::newInstance(
                $_ENV['AURA_BIN_SMTP_HOST'],
                $_ENV['AURA_BIN_SMTP_PORT'],
                $_ENV['AURA_BIN_SMTP_SECURITY']
            );
            $transport->setUsername($_ENV['AURA_BIN_SMTP_USERNAME']);
            $transport->setPassword($_ENV['AURA_BIN_SMTP_PASSWORD']);
            return \Swift_Mailer::newInstance($transport);
        });

        $di->params['Aura\Bin\Mailer']['message'] = $di->lazy(
            array('Swift_Message', 'newInstance')
        );

        /**
         * Aura\Bin\Tweeter
         */
        $di->params['Aura\Bin\Tweeter']['config'] = $di->lazyNew('Aura\Bin\Config');
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
            'release1'          => $di->lazyNew('Aura\Bin\Command\Release1'),
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
