<?php

namespace craftyfm\filemakerproxy\integrations\formbuilder;

use Craft;
use craftyfm\filemakerproxy\FmProxy;
use craftyfm\formbuilder\integrations\base\BaseIntegration;
use craftyfm\formbuilder\models\IntegrationResult;
use craftyfm\formbuilder\models\Submission;

class FilemakerIntegration extends BaseIntegration
{
    public ?int $profileId = null;

    public static function displayName(): string
    {
        return 'Filemaker';
    }

    public function defineFormSettingAttributes(): array
    {
       $attributes = parent::defineFormSettingAttributes();
       $attributes[] = 'profileId';
       return $attributes;

    }

    public function defineFormSettingRules(): array
    {
        $rules = parent::defineFormSettingRules();
        $rules[] = [['profileId'], 'required'];
        return $rules;
    }


    public function getFormSettingsHtml(): string
    {
        $variables = ['integration' => $this];
        $profiles = FmProxy::getInstance()->profiles->getEnabledProfiles();
        $profileOptions = [];
        foreach ($profiles as $profile) {
            $profileOptions[] = [
                'value' => $profile->id, 'label' => $profile->name,
            ];
        }
        $variables['profileOptions'] = $profileOptions;
        return Craft::$app->getView()->renderTemplate('filemaker-proxy/components/_form-settings', $variables);

    }

    protected function executeIntegration(Submission $submission): IntegrationResult
    {
        $result = new IntegrationResult();

        $profile = FmProxy::getInstance()->profiles->getProfileById($this->profileId);
        if (!$profile || $profile->enabled === false) {
            $result->success = false;
            $result->message = Craft::t('form-builder', 'No profile selected.');
            return $result;
        }
        try {

            $payload = $this->generateSubmissionPayload($submission);
            $body = [
                "fieldData" => [
                    "webhook_payload" => json_encode($payload)
                ]
            ];

            $response =  FmProxy::getInstance()->api->makeRequest($profile, 'POST', $body);
            if (!$response) {
                $result->success = false;
                return $result;
            }
            if ($response->getStatusCode() !== 200) {
                $result->success = false;
                $result->message = $response->getBody()->getContents();
                return $result;
            }
            $result->success = true;
            $result->data = json_decode($response->getBody()->getContents(), true);
            return $result;
        } catch (\Throwable $e) {
            Craft::error("Webhook integration error: " . $e->getMessage(), __METHOD__);
            $result->success = false;
            $result->exception = $e;
            return $result;
        }
    }
}