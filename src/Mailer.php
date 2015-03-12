<?php
namespace Aura\Bin;

use Swift_Mailer;
use Swift_Message;

class Mailer
{
    public function __construct(
        Config $config,
        Swift_Mailer $mailer,
        Swift_Message $message
    ) {
        $this->config = $config;
        $this->mailer = $mailer;
        $this->message = $message;
    }

    public function send($to, $subject, $body)
    {
        $body = trim($body) . PHP_EOL . PHP_EOL
            . '-- ' . PHP_EOL
            . 'The team at the Aura project' . PHP_EOL
            . 'http://auraphp.com' . PHP_EOL;

        $message = clone $this->message;
        $message->setFrom($this->config->email_from);
        $message->setTo($to);
        $message->setSubject($subject);
        $message->setBody($body);
        return $this->mailer->send($message);
    }
}
