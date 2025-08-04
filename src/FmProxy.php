<?php

namespace craftyfm\filemakerproxy;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craftyfm\filemakerproxy\integrations\freeform\Filemaker;
use craftyfm\filemakerproxy\integrations\freeform\jobs\IntegrationQueue;
use craftyfm\filemakerproxy\models\Settings;
use craftyfm\filemakerproxy\services\ApiService;
use craftyfm\filemakerproxy\services\Connections;
use craftyfm\filemakerproxy\services\NotificationService;
use craftyfm\filemakerproxy\services\Profiles;
use Solspace\Freeform\Events\Integrations\FailedRequestEvent;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Integrations\IntegrationInterface;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Filemaker Middleware plugin
 *
 * @method static FmProxy getInstance()
 * @method Settings getSettings()
 * @property ApiService $api
 * @property NotificationService $notification
 * @property Connections $connections
 * @property Profiles $profiles
 * @author craftyfm
 * @copyright craftyfm
 * @license https://craftcms.github.io/license/ Craft License
 */
class FmProxy extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
               'api' => ApiService::class,
               'notification' => NotificationService::class,
               'connections' => Connections::class,
               'profiles' => Profiles::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();

        Craft::$app->getProjectConfig()
            ->onAdd(Connections::CONFIG_KEY.'.{uid}', [$this->connections, 'handleChangedConnection'])
            ->onUpdate(Connections::CONFIG_KEY.'.{uid}', [$this->connections, 'handleChangedConnection'])
            ->onRemove(Connections::CONFIG_KEY.'.{uid}', [$this->connections, 'handleDeletedConnection']);

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
    }

    /**
     * @throws InvalidConfigException
     */
    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    public function getSettingsResponse(): mixed
    {
        $url = UrlHelper::cpUrl('filemaker-proxy/settings');

        return \Craft::$app->controller->redirect($url);
    }

    public function getCpNavItem(): array
    {
        $nav = parent::getCpNavItem();
        if (Craft::$app->getUser()->checkPermission('fmProxy-view-connection')) {
            $nav['label'] = 'Fm Proxy'; // Label in the nav
            $nav['url'] = 'filemaker-proxy'; // Where it links to

            if(Craft::$app->getUser()->checkPermission('fmProxy-view-profile')) {
                $nav['subnav'] = [
                    'profiles' => ['label' => 'Profiles', 'url' => 'filemaker-proxy/profiles'],
                ];
            }
            if (Craft::$app->getUser()->getIsAdmin()) {
                $nav['subnav']['settings'] = ['label' => 'Settings', 'url' => 'filemaker-proxy/settings'];
            }
        }
        return $nav;
    }

    private function attachEventHandlers(): void
    {
        $this->_setUpCpRoutes();
        $this->_setUpPermissions();

        if (Craft::$app->plugins->isPluginInstalled('feed-me')) {
            $this->_setUpFeedMe();
        }

        if (Craft::$app->plugins->isPluginInstalled('formie')) {
            $this->_setupFormie();
        }

        if (Craft::$app->plugins->isPluginInstalled('freeform')) {
            $this->_setupFreeform();
        }

        if(Craft::$app->plugins->isPluginInstalled('form-builder')) {
            $this->_setupFormBuilder();
        }
    }

    private function _setUpCpRoutes():void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['filemaker-proxy'] = 'filemaker-proxy/profiles/index';
                $event->rules['filemaker-proxy/profiles'] = 'filemaker-proxy/profiles/index';
                $event->rules['filemaker-proxy/profiles/new'] = 'filemaker-proxy/profiles/edit';
                $event->rules['filemaker-proxy/profiles/<id:\d+>'] = 'filemaker-proxy/profiles/edit';


                $event->rules['filemaker-proxy/settings'] = 'filemaker-proxy/settings';
                $event->rules['filemaker-proxy/settings/connections'] = 'filemaker-proxy/connections/index';
                $event->rules['filemaker-proxy/settings/connections/new'] = 'filemaker-proxy/connections/edit';
                $event->rules['filemaker-proxy/settings/connections/<id:\d+>'] = 'filemaker-proxy/connections/edit';
            }
        );
    }

    private function _setUpPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Filemaker Proxy',
                    'permissions' => [
                        'fmProxy-view-profile' => [
                            'label' => 'View Filemaker Profile',
                        ],
                        'fmProxy-update-profile' => [
                            'label' => 'Update a Filemaker Profile',
                        ],
                        'fmProxy-delete-profile' => [
                            'label' => 'Delete a new Filemaker Profile',
                        ],
                    ],
                ];
            }
        );
    }


    private function _setUpFeedMe(): void
    {
        Event::on(
            \craft\feedme\services\DataTypes::class,
            \craft\feedme\services\DataTypes::EVENT_BEFORE_FETCH_FEED,
            function(\craft\feedme\events\FeedDataEvent  $event) {
                $url = $event->url;
                $options = \craft\feedme\Plugin::$plugin->service->getRequestOptions($event->feedId);
                $response = FmProxy::getInstance()->api->handleRequestedUrl($url, 'GET', $options);
                if ($response) {
                    $event->response = ['success' => true, 'data' => $response];;
                }
            }
        );
    }

    private function _setupFormie(): void
    {
        Event::on(\verbb\formie\services\Integrations::class, \verbb\formie\services\Integrations::EVENT_REGISTER_INTEGRATIONS,
            function(\verbb\formie\events\RegisterIntegrationsEvent $event) {
                $event->miscellaneous[] = \craftyfm\filemakerproxy\integrations\formie\Filemaker::class;
        });

    }

    private function _setupFreeform(): void
    {
        Event::on(
            \Solspace\Freeform\Services\Integrations\IntegrationsService::class,
            \Solspace\Freeform\Services\Integrations\IntegrationsService::EVENT_REGISTER_INTEGRATION_TYPES,
            function (\Solspace\Freeform\Events\Integrations\RegisterIntegrationTypesEvent $event) {
                $event->addType(\craftyfm\filemakerproxy\integrations\freeform\Filemaker::class);
            }
        );

        Event::on(
            \Solspace\Freeform\Elements\Submission::class,
            \Solspace\Freeform\Elements\Submission::EVENT_PROCESS_SUBMISSION,
            function (\Solspace\Freeform\Events\Submissions\ProcessSubmissionEvent $event) {
                if (!$event->isValid) {
                    return;
                }

                $form = $event->getForm();
                if ($form->isMarkedAsSpam()) {
                    return;
                }
                $integrations = Freeform::getInstance()->integrations->getForForm($form, Filemaker::class);
                if ($integrations && Freeform::getInstance()->settings->isIntegrationQueueEnabled()) {
                    Craft::$app->getQueue()->push(new IntegrationQueue([
                        'formId' => $form->getId(),
                        'postedData' => $event->getSubmission()->getFormFieldValues(),
                    ]));
                    return;
                }

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
        );
    }

    private function _setupFormBuilder()
    {
        Event::on(
            \craftyfm\formbuilder\services\Integrations::class,
            \craftyfm\formbuilder\services\Integrations::EVENT_REGISTER_INTEGRATIONS,
            function(\craftyfm\formbuilder\events\RegisterIntegrationEvent $event) {
                    $event->types[\craftyfm\formbuilder\integrations\base\BaseIntegration::TYPE_MISC][] =
                        \craftyfm\filemakerproxy\integrations\formbuilder\FilemakerIntegration::class;
            }
        );
    }
}
