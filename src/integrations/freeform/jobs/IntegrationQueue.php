<?php

namespace craftyfm\filemakerproxy\integrations\freeform\jobs;

use craftyfm\filemakerproxy\integrations\freeform\Filemaker;
use Solspace\Freeform\Events\Integrations\FailedRequestEvent;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Integrations\IntegrationInterface;
use Solspace\Freeform\Library\Integrations\PushableInterface;
use yii\base\Event;

class IntegrationQueue extends \craft\queue\BaseJob
{
    public ?int $formId = null;

    public array $postedData = [];

    public ?string $type = null;

    public function execute($queue): void
    {
        $freeform = Freeform::getInstance();

        $form = $freeform->forms->getFormById($this->formId);
        if (!$form) {
            return;
        }

        $form->valuesFromArray($this->postedData);

        $integrations = Freeform::getInstance()->integrations->getForForm($form, Filemaker::class);
        foreach ($integrations as $integration) {
            try {
                $integration->trigger($form);
            } catch (\Exception $exception) {
                $event = new FailedRequestEvent($form, $integration, $exception);
                Event::trigger(
                    IntegrationInterface::class,
                    IntegrationInterface::EVENT_ON_FAILED_REQUEST,
                    $event
                );
            }
        }

    }

    public function defaultDescription(): ?string
    {
        return 'Freeform: Processing Integrations';
    }

}