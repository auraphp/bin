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
         * Aura\Bin\Command\Release1
         */
        $di->setter['Aura\Bin\Command\Release1']['setPhpdoc'] = $di->lazyNew('Aura\Bin\Shell\Phpdoc');
        $di->setter['Aura\Bin\Command\Release1']['setPhpunit'] = $di->lazyNew('Aura\Bin\Shell\Phpunit');

        /**
         * Aura\Bin\Command\Release2
         */
        $di->setter['Aura\Bin\Command\Release2']['setPhpdoc'] = $di->lazyNew('Aura\Bin\Shell\Phpdoc');
        $di->setter['Aura\Bin\Command\Release2']['setPhpunit'] = $di->lazyNew('Aura\Bin\Shell\Phpunit');
        $di->setter['Aura\Bin\Command\Release2']['setIronMQ'] = $di->lazyNew('IronMQ\IronMQ');
        $di->setter['Aura\Bin\Command\Release2']['setTweeter'] = $di->lazyNew('Aura\Bin\Tweeter');

        /**
         * Aura\Bin\Command\Release3
         */
        $di->setter['Aura\Bin\Command\Release3']['setPhpdoc'] = $di->lazyNew('Aura\Bin\Shell\Phpdoc');
        $di->setter['Aura\Bin\Command\Release3']['setPhpunit'] = $di->lazyNew('Aura\Bin\Shell\Phpunit');
        $di->setter['Aura\Bin\Command\Release3']['setIronMQ'] = $di->lazyNew('IronMQ\IronMQ');
        $di->setter['Aura\Bin\Command\Release3']['setTweeter'] = $di->lazyNew('Aura\Bin\Tweeter');

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

        // IronMQ
        $di->params['IronMQ\IronMQ']['config'] = array(
            "token" => $_ENV['AURA_BIN_IRON_TOKEN'],
            "project_id" => $_ENV['AURA_BIN_IRON_PROJECT_ID'],
            "host" => $_ENV['AURA_BIN_IRON_HOST'],
        );

        // Send email notifications
        $di->setter['Aura\Bin\Command\SendEmail']['setIronMQ'] = $di->lazyNew('IronMQ\IronMQ');
        $di->setter['Aura\Bin\Command\SendEmail']['setMailer'] = $di->lazyNew('Aura\Bin\Mailer');
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
            'docs'               => $di->lazyNew('Aura\Bin\Command\Docs'),
            'issues'             => $di->lazyNew('Aura\Bin\Command\Issues'),
            'log-since-release'  => $di->lazyNew('Aura\Bin\Command\LogSinceRelease'),
            'packages-json'      => $di->lazyNew('Aura\Bin\Command\PackagesJson'),
            'packagist'          => $di->lazyNew('Aura\Bin\Command\Packagist'),
            'release1'           => $di->lazyNew('Aura\Bin\Command\Release1'),
            'release2'           => $di->lazyNew('Aura\Bin\Command\Release2'),
            'release3'           => $di->lazyNew('Aura\Bin\Command\Release3'),
            'readme'             => $di->lazyNew('Aura\Bin\Command\Readme'),
            'repos'              => $di->lazyNew('Aura\Bin\Command\Repos'),
            'send-email'         => $di->lazyNew('Aura\Bin\Command\SendEmail'),
            'travis'             => $di->lazyNew('Aura\Bin\Command\Travis'),
            'create-changelog'   => $di->lazyNew('Aura\Bin\Command\CreateChangelog'),
            'show-release-notes' => $di->lazyNew('Aura\Bin\Command\ShowReleaseNotes'),
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
