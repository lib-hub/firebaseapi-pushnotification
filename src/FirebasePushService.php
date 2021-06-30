<?php

namespace FirebaseWrapper;

use FirebaseWrapper\Constants;
use Google\Exception;
use Google_Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;
use Throwable;

class FirebasePushService
{

    /**
     * @var ClientInterface
     */
    private ClientInterface $httpClient;

    /**
     * FirebasePushService constructor.
     */
    public function __construct()
    {
        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(Constants::CLOUD_PLATFORM_SCOPE);
        $this->httpClient = $client->authorize();
    }

    /**
     * Find a firebase project without apps and return it
     *
     * @return object
     * @throws Throwable
     */
    private function getEmptyProject()
    {
        $response = $this->httpClient->request('GET', 'https://firebase.googleapis.com/v1beta1/projects');
        $responseData = json_decode($response->getBody());
        //Log::info("Firebase projects", (array)$responseData);
        $flag = false;
        $iterator = 0;
        while ($iterator < count($responseData->results) && !$flag) {
            $project = $responseData->results[$iterator];
            $resp = $this->httpClient->get("https://firebase.googleapis.com/v1beta1/projects/$project->projectId/webApps");
            $data = json_decode($resp->getBody(), true);
            //Log::info("Firebase project - " . $project->projectId . " apps:", $data);

            if (count($data) === 0) {
                $flag = true;
            } else {
                $iterator++;
            }
        }

        if ($flag) {
            return $responseData->results[$iterator];
        } else {
            return new stdClass();
        }
    }

    /**
     * @param string $projectId
     * @return stdClass
     * @throws GuzzleException
     */
    private function deleteProject(string $projectId): object
    {
        $response = $this->httpClient->request('DELETE', Constants::GOOGLE_CLOUD_URL . "/projects/$projectId");
        $responseData = new stdClass();
        // 200 success
        $responseData->status = $response->getStatusCode();
        $responseData->payload = json_decode($response->getBody());
        return $responseData;
    }

    /**
     * Get config of a firebase webapp
     *
     * @param string $projectId
     * @param string $appId
     * @return object config
     * @throws GuzzleException
     */
    private function getFirebaseConfig(string $projectId, string $appId): object
    {
        $response = $this->httpClient->request("GET", Constants::FIREBASE_URL . "/projects/$projectId/webApps/$appId/config");
        $responseData = new stdClass();
        // 200 success
        $responseData->status = $response->getStatusCode();
        $responseData->payload = json_decode($response->getBody());
        return $responseData;
    }

    /**
     * Create a webapp inside a firebase project
     *
     * @param string $projectId
     * @param string $name
     * @return string appId
     * @throws Exception
     * @throws GuzzleException
     */
    private function createWebApp(string $projectId, string $name): string
    {
        $response = $this->httpClient->request(
            "POST",
            Constants::FIREBASE_URL . "/projects/$projectId/webApps",
            ["body" => json_encode(["displayName" => $name])]
        );
        $appId = "";

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody());
            $operationResponse = $this->getOperation($data->name);

            if (count((array)$operationResponse) > 0) {
                $appId = $operationResponse->appId;
            }
        } else {
            throw new Exception($response->getBody(), $response->getStatusCode());
        }

        return $appId;
    }

    /**
     * Polling request to get Operation Resource
     *
     * @link https://firebase.google.com/docs/projects/api/reference/rest/v1beta1/operations
     * @param string $operation
     * @return object
     * @throws Exception
     * @throws GuzzleException
     */
    private function getOperation(string $operation): object
    {
        $done = false;
        $counter = 0;
        $operationData = null;
        $errors = [];

        while (!$done && $counter < Constants::OPERATION_POLLING_COUNT) {
            sleep(Constants::OPERATION_POLLING_INTERVAL);
            $response = $this->httpClient->request("GET", Constants::FIREBASE_URL . "/$operation");

            if ($response->getStatusCode() === 200) {
                $operationData = json_decode($response->getBody());

                if (isset($operationData->done)) {
                    $done = $operationData->done;
                } else {
                    $error = new stdClass();
                    $error->message = $response->getBody();
                    $error->code = $response->getStatusCode();
                    $errors[] = $error;
                }
            } else {
                $error = new stdClass();
                $error->message = $response->getBody();
                $error->code = $response->getStatusCode();
                $errors[] = $error;
            }

            $counter++;
        }

        if (!$done || isset($operationData->error)) {
            throw new Exception('Operation failed');
        } else {
            return $operationData->response;
        }
    }

    /**
     * Send a push notification via firebase
     *
     * @param $uuid
     * @param PushMessage $message
     * @return bool - Whether the notification was sent successfully or not.
     */
    public function sendPushNotification(string $projectId, PushMessage $message)
    {
        $campaignSuccess = false;
        $body = new stdClass();
        $body->validate_only = false;
        $body->message = $message->data();

        $response = $this->httpClient->request(
            "POST",
            "https://fcm.googleapis.com/v1/projects/$projectId/messages:send",
            ["body" => json_encode($body)]
        );

        if ($response->getStatusCode() === 200) {


            $fire = json_decode($response->getBody(), true);


        } else {
        }


        return $campaignSuccess;
    }

    /**
     * Subscribe to firebase topic or unsubscribe from firebase topic
     *
     * @param string $type - enum-> batchAdd,batchRemove
     * @param string $body - Json body of the request
     * @param string $projectId
     * @return stdClass
     * @throws Throwable
     */
    private function topicRequest($type, $body, $projectId)
    {
        $serverKeyEntity = ServerKey::where('projectId', $projectId)->firstOrFail();
        $request = new Client();
        $data = new stdClass();

        $response = $request->request("POST", "https://iid.googleapis.com/iid/v1:$type", [
            "body" => $body,
            'http_errors' => false,
            "headers" => [
                "Content-Type" => "application/json",
                "Authorization" => "key=$serverKeyEntity->serverKey"
            ]
        ]);

        $data->status = $response->getStatusCode();
        $data->message = json_decode($response->getBody(), true);
        Log::error("=== TOPIC === " . $response->getBody());
        return $data;
    }

    /**
     * @param string $uuid
     * @param string $topicUuid
     * @param array $pushTokens
     * @return object
     * @throws Throwable
     */
    public function subscribeToTopic($uuid, $topicUuid, array $pushTokens)
    {
        $successfulRequest = false;
        $type = "batchAdd";
        /**
         * Fetch topic name by it's UUID and related changes below
         * Added on 30th Sep 2020 by KapilH
         */
        $topicData = Topic::where('uuid', $topicUuid)->firstOrFail();

        $body = json_encode([
            "to" => "/topics/" . $topicData->topic,
            "registration_tokens" => $pushTokens
        ]);

        $config = $this->getConfig($uuid);

        if (count((array)$config) > 0) {
            return $this->topicRequest($type, $body, $config->projectId);
        } else {
            return $this->configNotFoundResponse();
        }
    }

    /**
     * @param string $uuid
     * @param string $topicUuid
     * @param array $pushTokens
     * @return object
     * @throws Throwable
     */
    public function unsubscribeFromTopic($uuid, $topicUuid, array $pushTokens)
    {
        $successfulRequest = false;
        $type = "batchRemove";
        /**
         * Fetch topic name by it's UUID and related changes below
         * Added on 30th Sep 2020 by KapilH
         */
        $topicData = Topic::where('uuid', $topicUuid)->firstOrFail();

        $body = json_encode([
            "to" => "/topics/" . $topicData->topic,
            "registration_tokens" => $pushTokens
        ]);

        $config = $this->getConfig($uuid);

        if (count((array)$config) > 0) {
            return $this->topicRequest($type, $body, $config->projectId);
        } else {
            return $this->configNotFoundResponse();
        }
    }

    /**
     * @param string $uuid
     * @param array $pushTokens
     * @return object
     * @throws Throwable
     */
    public function subscribeToDefaultTopic($uuid, array $pushTokens)
    {
        $successfulRequest = false;
        $type = "batchAdd";
        /**
         * Fetch topic name by it's UUID and related changes below
         * Added on 30th Sep 2020 by KapilH
         */
        $matchFields = [
            "implementationId" => $uuid,
            "default" => 1
        ];
        $topicData = Topic::where($matchFields)->firstOrFail();

        $body = json_encode([
            "to" => "/topics/" . $topicData->topic,
            "registration_tokens" => $pushTokens
        ]);

        $config = $this->getConfig($uuid);

        if (count((array)$config) > 0) {
            return $this->topicRequest($type, $body, $config->projectId);
        } else {
            return $this->configNotFoundResponse();
        }
    }
}
