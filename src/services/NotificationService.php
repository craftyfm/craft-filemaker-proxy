<?php

namespace craftyfm\filemakerproxy\services;

use Craft;
use craft\base\Component;
use craftyfm\filemakerproxy\FmProxy;

class NotificationService extends Component
{
    public function sendEmail(string $to, string $subject, string $htmlBody, string $textBody): void
    {

        $message = Craft::$app->mailer
            ->compose()
            ->setTo($to)
            ->setSubject($subject)
            ->setTextBody($textBody)
            ->setHtmlBody($htmlBody);

        if ($message->send()) {
            Craft::info('Email sent successfully', __METHOD__);
        } else {
            Craft::error('Email sending failed', __METHOD__);
        }
    }

    public function sendErrorNotification($html, $plain): void
    {
        $adminEmail = FmProxy::getInstance()->getSettings()->adminEmail;
        if (!$adminEmail) {
            return;
        }
        $this->sendEmail($adminEmail, "Notification Failed", $html, $plain);
    }
}