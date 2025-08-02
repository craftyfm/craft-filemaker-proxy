<?php

namespace craftyfm\filemakerproxy\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use craftyfm\filemakerproxy\FmProxy;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

class ApiController extends Controller
{
    protected array|int|bool $allowAnonymous = ['middleware'];
    const ALLOWED_IP = '127.0.0.1';
    public $enableCsrfValidation = false;
    /**
     * @throws BadRequestHttpException
     * @throws UnauthorizedHttpException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function actionMiddleware(string $profile): Response
    {
        if ($this->request->getRemoteIP() !== self::ALLOWED_IP and $this->request->getUserIP() !== self::ALLOWED_IP) {
            throw new NotFoundHttpException('Page not found.');
        }
        $authHeader = Craft::$app->getRequest()->getHeaders()->get('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
            throw new UnauthorizedHttpException('Missing or invalid Authorization header.');
        }

        $token = $matches[1];
        $validToken = App::parseEnv(FmProxy::getInstance()->getSettings()->token);

        if (!hash_equals($validToken, $token)) {
            throw new UnauthorizedHttpException('Invalid API token.');
        }


        $this->requireAcceptsJson();
        $this->response->headers->set('X-Robots-Tag', 'noindex , nofollow' );

        // Find the connection by handle
        $profile = FmProxy::getInstance()->profiles->getProfileByHandle($profile);
        if (!$profile) {
            throw new NotFoundHttpException('Page not found.');
        }
        if (!$profile->enabled || !$profile->endpointEnabled) {
            throw new NotFoundHttpException('Page not found.');
        }
        $method = $this->request->getMethod();
        $data = $this->request->getBodyParams();
        try {
            $response = FmProxy::getInstance()->api->makeRequest($profile, $method, $data);
            $result = $response->getBody()->getContents();
            return $this->asJson(json_decode($result, true));
        } catch (GuzzleException $e) {
            return $this->asFailure('FileMaker API error: ' . $e->getMessage());
        }
    }
}