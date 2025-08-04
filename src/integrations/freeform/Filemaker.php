<?php

namespace craftyfm\filemakerproxy\integrations\freeform;

use craftyfm\filemakerproxy\FmProxy;
use GuzzleHttp\Exception\GuzzleException;
use Solspace\Freeform\Attributes\Property\Flag;
use Solspace\Freeform\Attributes\Property\Validators\Required;
use Solspace\Freeform\Fields\Implementations\FileUploadField;
use Solspace\Freeform\Form\Form;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Integrations\BaseIntegration;
use Solspace\Freeform\Attributes\Integration\Type;
use Solspace\Freeform\Attributes\Property\Input;
use Solspace\Freeform\Library\Integrations\IntegrationInterface;
use Solspace\Freeform\Library\Integrations\Types\Webhooks\WebhookIntegration;
use Solspace\Freeform\Library\Integrations\Types\Webhooks\WebhookIntegrationInterface;
use yii\base\Exception;

#[Type(
    name: 'Filemaker',
    type: Type::TYPE_OTHER,
)]
class Filemaker extends BaseIntegration
{
    #[Required]
    #[Flag(self::FLAG_INSTANCE_ONLY)]
    #[Input\Select(
        label: 'Select Layout',
        instructions: 'Choose a layout from FileMaker.',
        order: 1,
        options: ProfileOptions::class,
    )]
    public ?int $profileId = null;

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function trigger(Form $form): void
    {
        if (!$this->profileId) {
            return;
        }
        $profile = FmProxy::getInstance()->profiles->getProfileById($this->profileId);
        if (!$profile || $profile->enabled === false) {
            return;
        }
        $submission = $form->getSubmission();
        $json = [
            'form' => [
                'id' => $form->getId(),
                'name' => $form->getName(),
                'handle' => $form->getHandle(),
                'color' => $form->getColor(),
                'description' => $form->getDescription(),
                'returnUrl' => $form->getReturnUrl(),
            ],
        ];

        if ($submission) {
            $json['id'] = $submission->id;
            $json['dateCreated'] = $submission->dateCreated;
            $json['uid'] = $submission->uid;
        }

        foreach ($form->getLayout()->getFields()->getStorableFields() as $field) {
            $value = $field->getValue();
            if ($field instanceof FileUploadField) {
                $value = Freeform::getInstance()->files->getAssetMetadataFromIds($value);
            }

            $json[$field->getHandle()] = $value;
        }

        $body = [
            "fieldData" => [
                "webhook_payload" => json_encode($json)
            ]
        ];

        $response = FmProxy::getInstance()->api->makeRequest($profile, 'POST', $body);

        $this->logger->info('Filemaker integration triggered', ['form' => $form->getHandle(), 'submission' => $submission->id]);
        $this->logger->debug('With Payload', $body);
    }
}