<?php

namespace craftyfm\filemakerproxy\integrations\formie;

use Craft;
use craftyfm\filemakerproxy\FmProxy;
use GuzzleHttp\Exception\GuzzleException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use verbb\formie\base\Integration;
use verbb\formie\base\Miscellaneous;
use verbb\formie\elements\Submission;
use yii\base\Exception;

class Filemaker extends Miscellaneous
{
    public ?int $profileId = null;
    public static function displayName(): string
    {
        return Craft::t('filemaker-proxy', 'Filemaker');
    }

    public function getDescription(): string
    {
        return Craft::t('filemaker-proxy', 'This is an integration with filemaker .');
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['profileId'], 'required', 'on' => [Integration::SCENARIO_FORM]];

        return $rules;
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws Exception
     * @throws LoaderError
     */
    public function getSettingsHtml(): string
    {
        $variables = $this->getSettingsHtmlVariables();

        return Craft::$app->getView()->renderTemplate('filemaker-proxy/components/_settings', $variables);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws Exception
     * @throws LoaderError
     */
    public function getFormSettingsHtml($form): string
    {
        $variables = $this->getFormSettingsHtmlVariables($form);
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

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function sendPayload(Submission $submission): bool
    {
        $payload = $this->generatePayloadValues($submission);
        if ($this->profileId === null) {
            return false;
        }
        $profile = FmProxy::getInstance()->profiles->getProfileById($this->profileId);
        if (!$profile || $profile->enabled === false) {
            return false;
        }

        $body = [
            "fieldData" => [
                "webhook_payload" => json_encode($payload)
            ]
        ];
        $response = FmProxy::getInstance()->api->makeRequest($profile, 'POST', $body);

        return true;
    }
}