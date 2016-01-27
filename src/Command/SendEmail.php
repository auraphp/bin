<?php
namespace Aura\Bin\Command;

/**
 * Sending email notification via IronMQ and later fetching the result and sending
 */
class SendEmail extends AbstractCommand
{
    protected $ironmq;

    protected $mailer;

    public function __invoke()
    {
        $queue_name = 'notifications';

        $messages = $this->ironmq->getMessages($queue_name, 30);
        $message_ids = array();
        $emailbody = '';
        if (! empty($messages)) {
            foreach ($messages as $message) {
                $data = json_decode($message->body);
                $emailbody .= "### {$data->package} version {$data->version}, with these changes:" . PHP_EOL;
                $emailbody .= PHP_EOL . $data->changes . PHP_EOL . PHP_EOL;
                $message_ids[] = $message->id;
            }
            if ($this->sendEmail($emailbody)) {
                $this->ironmq->deleteMessages($queue_name, $messages);
            }
        }
    }

    protected function sendEmail($content)
    {
        $this->stdio->out('Notifying the mailing list ... ');

        $to = 'auraphp@googlegroups.com';
        $subject = "New Releases";
        $body = <<<BODY
Hi everyone!

We have released these new package versions:

{$content}

Please let us know if you have any questions, comments, or concerns about this or any other release.  And thanks, as always, for supporting Aura!

BODY;
        $result = $this->mailer->send($to, $subject, $body);
        if (! $result) {
            $this->stdio->outln('failure.');
            return false;
        } else {
            $this->stdio->outln('success.');
            return true;
        }
    }

    public function setIronMQ($ironmq)
    {
        $this->ironmq = $ironmq;
    }

    public function setMailer($mailer)
    {
        $this->mailer = $mailer;
    }
}
