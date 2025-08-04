<?php

namespace craftyfm\filemakerproxy\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\DateTimeHelper;
use craftyfm\filemakerproxy\FmProxy;
use craftyfm\filemakerproxy\models\Connection;
use craftyfm\filemakerproxy\models\Profile;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Exception;
use yii\base\InvalidConfigException;

class ApiService extends Component
{

    /**
     * Authenticate with FileMaker and get an access token
     *
     * @param Profile $profile
     * @return string|null
     * @throws GuzzleException
     */
    public function authenticate(Profile $profile): ?string
    {
        $connection = $profile->getConnection();
        $client = new Client([
            'timeout' => 30,
            'verify' => true, // You might want to configure SSL verification
        ]);

        // Decrypt the password
        $password = App::parseEnv($connection->password);
        $username = App::parseEnv($connection->username);

        try {
            $response = $client->post($connection->getAuthUrl(), [
                'auth' => [$username, $password],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => ''
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data && isset($data['response']['token'])) {
                return $data['response']['token'];
            }

            return null;
        } catch (GuzzleException $e) {
            Craft::error("FileMaker authentication failed for profile $profile->handle: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    /**
     * Log out from FileMaker API
     *
     * @param Profile $profile
     * @param string $token
     * @return bool
     */
    public function logout(Profile $profile, string $token): bool
    {
        $client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);

        $connection = $profile->getConnection();

        try {
            $logoutUrl = $connection->getAuthUrl() . '/' . $token;

            $response = $client->delete($logoutUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);
            if ($response->getStatusCode() === 200) {
                Craft::info("Successfully logged out from FileMaker connection $profile->handle", __METHOD__);
                return true;
            }
        } catch (GuzzleException $e) {
            Craft::warning("Logout attempt failed for connection $profile->handle: " . $e->getMessage() , __METHOD__);
        }

        return false;
    }

    /**
     * Make a request to FileMaker with retry logic
     *
     * @param Profile $profile
     * @param string $method
     * @param array $data
     * @throws Exception
     * @throws GuzzleException
     */
    public function makeRequest(Profile $profile, string $method = 'GET', array $data = []): ?\Psr\Http\Message\ResponseInterface
    {
        $token = $this->authenticate($profile);

        if (!$token) {
            throw new Exception('Failed to authenticate with FileMaker');
        }

        $client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($data)) {
            $options['json'] = $data;
        }

        $maxRetries = 5;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $client->request($method, $profile->getRecordUrl(), $options);
                // Logout after successful request
                $this->logout($profile, $token);
                
                return $response;
            } catch (GuzzleException $e) {
                if ($attempt === $maxRetries) {
                    // Log error after final attempt
                    Craft::error("FileMaker API request failed for profile $profile->handle after $maxRetries attempts. Method: $method. Last error: " . $e->getMessage(), __METHOD__);
                    
                    // Attempt to log out even on failure
                    $this->logout($profile, $token);
                    $this->_sendErrorNotification($profile, $maxRetries, $method, $e->getMessage());
                    throw $e;
                }
                
                // Wait before retry (exponential backoff)
                sleep(pow(2, $attempt - 1));
                Craft::warning("Request attempt $attempt failed for profile $profile->handle: " . $e->getMessage() . ". Retrying...", __METHOD__);
            }
        }

        return null;
    }


    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function handleRequestedUrl(string $url, string $method, array $options, ): ?string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        // Check if it's localhost
        if ($host === 'localhost' || $host === '127.0.0.1') {
            // Check if it's targeting the specific action URL
            if (str_starts_with($path, '/actions/filemaker-proxy/api/middleware')) {
                // Check for profile in query string
                parse_str($query, $queryParams);
                if (!empty($queryParams['profile'])) {
                    $profile = FmProxy::getInstance()->profiles->getProfileByHandle($queryParams['profile']);
                    if (!$profile) {
                        return null;
                    }
                    $response =  FmProxy::getInstance()->api->makeRequest($profile, $method, $options);
                    return $response->getBody()->getContents();
                }
            }
        }

        return null;
    }
    
    private function _sendErrorNotification(Profile $profile, int $maxRetries, string $method, string $errorMessage): void
    {
        $recordUrl = $profile->getRecordUrl();
        $datetime = DateTimeHelper::toIso8601(new DateTime());

        $htmlMessage = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9;'>
        <div style='background-color: #d32f2f; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
            <h2 style='margin: 0; font-size: 18px;'>
                <span style='display: inline-block; margin-right: 10px;'>⚠️</span>
                FileMaker API Error
            </h2>
        </div>
        
        <div style='margin-bottom: 20px;'>
            <h3 style='color: #333; margin-bottom: 10px; font-size: 16px;'>Error Details</h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background-color: #f5f5f5; font-weight: bold; width: 120px;'>connection:</td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>$profile->handle</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background-color: #f5f5f5; font-weight: bold;'>URL:</td>
                    <td style='padding: 8px; border: 1px solid #ddd; word-break: break-all;'>$recordUrl</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background-color: #f5f5f5; font-weight: bold;'>Method:</td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>$method</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background-color: #f5f5f5; font-weight: bold;'>Attempts:</td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>$maxRetries</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background-color: #f5f5f5; font-weight: bold;'>Timestamp:</td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>$datetime</td>
                </tr>
            </table>
        </div>
        
        <div style='margin-bottom: 20px;'>
            <h3 style='color: #333; margin-bottom: 10px; font-size: 16px;'>Error Message</h3>
            <div style='background-color: #ffebee; border: 1px solid #e57373; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 14px; color: #c62828;'>
                " . htmlspecialchars($errorMessage) . "
            </div>
        </div>

        
        <div style='text-align: center; color: #666; font-size: 12px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;'>
            Generated by FileMaker Middleware • " . date('Y-m-d H:i:s') . "
        </div>
    </div>";
      $message = "FileMaker API request to $recordUrl for connection '$profile->handle' failed after $maxRetries attempts. Method: $method. Last error: " . $errorMessage . 'at'
          . 'at' .$datetime;
    FmProxy::getInstance()->notification->sendErrorNotification($htmlMessage, $message);
}


}