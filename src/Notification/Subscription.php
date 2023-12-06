<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Notification;

use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Microsoft\Graph\Generated\Models\ODataErrors\MainError;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\Generated\Models\Subscription as MsSubscription;
use Microsoft\Graph\Generated\Subscriptions\Item\SubscriptionItemRequestBuilderDeleteRequestConfiguration;
use Microsoft\Graph\Generated\Subscriptions\Item\SubscriptionItemRequestBuilderPatchRequestConfiguration;
use Microsoft\Graph\Generated\Subscriptions\SubscriptionsRequestBuilderPostRequestConfiguration;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\AuthorizationContextTrait;
use Symplicity\Outlook\Exception\SubscribeFailedException;
use Symplicity\Outlook\Interfaces\Notification\SubscriptionInterface;
use Symplicity\Outlook\Utilities\EventView\GraphServiceEvent;

/**
 * @property-read GraphServiceEvent $graphService
 */
class Subscription implements SubscriptionInterface
{
    use AuthorizationContextTrait;
    use BearerAuthorizationTrait;

    private ?LoggerInterface $logger;

    public function __construct(private readonly string $clientId, private readonly string $clientSecret, private readonly string $token, array $args = [])
    {
        $this->logger = $args['logger'] ?? null;
    }

    public function __get(string $property)
    {
        if ($property === 'graphService') {
            $this->graphService = new GraphServiceEvent(
                $this->clientId,
                $this->clientSecret,
                $this->token
            );

            return $this->graphService;
        }

        return null;
    }

    public function subscribe(MsSubscription $subscriptionEntity, array $args = []): MsSubscription
    {
        try {
            $subscriptionRequestConfig = new SubscriptionsRequestBuilderPostRequestConfiguration();
            $subscriptionRequestConfig->headers = array_merge(
                $args['headers'] ?? [],
                $this->getAuthorizationHeaders($this->token)
            );

            $subscriptionRequestConfig->options = $args['options'] ?? [];

            return $this->graphService
                ->client($args)
                ->subscriptions()
                ->post($subscriptionEntity, $subscriptionRequestConfig)
                ->wait();
        } catch (\Exception $e) {
            $this->convertToReadableError($e);
        }
    }

    public function renew(string $subscriptionId, \DateTime $expiration, array $args = []): MsSubscription
    {
        try {
            $subscriptionRequestConfig = new SubscriptionItemRequestBuilderPatchRequestConfiguration();
            $subscriptionRequestConfig->headers = array_merge(
                $args['headers'] ?? [],
                $this->getAuthorizationHeaders($this->token)
            );

            $subscriptionRequestConfig->options = $args['options'] ?? [];

            $request = new MsSubscription();
            $request->setExpirationDateTime($expiration);
            return $this->graphService
                ->client($args)
                ->subscriptions()
                ->bySubscriptionId($subscriptionId)
                ->patch($request, $subscriptionRequestConfig)
                ->wait();
        } catch (\Exception $e) {
            $this->convertToReadableError($e);
        }
    }

    public function delete(string $subscriptionId, array $args = []): void
    {
        try {
            $subscriptionRequestConfig = new SubscriptionItemRequestBuilderDeleteRequestConfiguration();
            $subscriptionRequestConfig->headers = array_merge(
                $args['headers'] ?? [],
                $this->getAuthorizationHeaders($this->token)
            );

            $subscriptionRequestConfig->options = $args['options'] ?? [];
            $this->graphService
                ->client($args)
                ->subscriptions()
                ->bySubscriptionId($subscriptionId)
                ->delete($subscriptionRequestConfig)
                ->wait();
        } catch (\Exception $e) {
            $this->convertToReadableError($e);
        }
    }

    /**
     * @throws SubscribeFailedException
     */
    private function convertToReadableError(\Exception $e)
    {
        $message = null;
        if ($e instanceof ODataError) {
            /** @var MainError $errorInfo */
            $errorInfo = $e->getBackingStore()->get('error');
            $code = 0;
            $localizedDescription = $errorInfo->getMessage();
            $message = $errorInfo->getCode();
        } else {
            $code = $e->getCode();
            $localizedDescription = $e->getMessage();
        }

        $this->logger?->info('Subscription error...', [
            'code' => $code,
            'localizedDescription' => $localizedDescription,
            'message' => $message
        ]);

        $error = new SubscribeFailedException($localizedDescription, $code);
        $error->setOdataErrorMessage($message);
        throw $error;
    }
}
