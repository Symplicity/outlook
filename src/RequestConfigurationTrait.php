<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use GuzzleHttp\RequestOptions;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Microsoft\Graph\Core\Requests\BatchRequestBuilderPostRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\EventsRequestBuilderPostRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderDeleteRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderPatchRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\Instances\InstancesRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Events\Item\Instances\InstancesRequestBuilderGetRequestConfiguration;
use Symplicity\Outlook\Interfaces\Utilities\CalendarView\CalendarViewParamsInterface;
use Symplicity\Outlook\Utilities\CalendarView\Delta\DeltaRequestBuilderGetRequestConfiguration;

trait RequestConfigurationTrait
{
    use BearerAuthorizationTrait;

    private readonly string $token;

    protected function getEventViewRequestConfiguration(?EventItemRequestBuilderGetQueryParameters $params = null): EventItemRequestBuilderGetRequestConfiguration
    {
        $requestConfiguration = new EventItemRequestBuilderGetRequestConfiguration();
        $queryParameters = EventItemRequestBuilderGetRequestConfiguration::createQueryParameters($params?->expand, $params?->select);
        $requestConfiguration->queryParameters = $queryParameters;
        $requestConfiguration->headers = array_merge(
            $this->getAuthorizationHeaders($this->token)
        );

        return $requestConfiguration;
    }

    /**
     * @param array<string, string> $headers
     * @param RequestOptions[] | null $options
     */
    protected function getInstancesViewRequestConfiguration(?InstancesRequestBuilderGetQueryParameters $params = null, array $headers = [], array $options = []): InstancesRequestBuilderGetRequestConfiguration
    {
        $requestConfiguration = new InstancesRequestBuilderGetRequestConfiguration(queryParameters: $params);
        return $this->generateRequestConfiguration(
            $requestConfiguration,
            $headers,
            $options
        );
    }

    /**
     * @param array<string, string> $headers
     * @param RequestOptions[] | null $options
     */
    protected function getEventPostRequestConfiguration(array $headers = [], array $options = []): EventsRequestBuilderPostRequestConfiguration
    {
        $requestConfiguration = new EventsRequestBuilderPostRequestConfiguration();
        return $this->generateRequestConfiguration(
            $requestConfiguration,
            $headers,
            $options
        );
    }

    /**
     * @param array<string, string> $headers
     * @param RequestOptions[] | null $options
     */
    protected function getEventPatchRequestConfiguration(array $headers = [], array $options = []): EventItemRequestBuilderPatchRequestConfiguration
    {
        $requestConfiguration = new EventItemRequestBuilderPatchRequestConfiguration();
        return $this->generateRequestConfiguration(
            $requestConfiguration,
            $headers,
            $options
        );
    }

    /**
     * @param array<string, string> $headers
     * @param RequestOptions[] | null $options
     */
    protected function getEventDeleteRequestConfiguration(array $headers = [], array $options = []): EventItemRequestBuilderDeleteRequestConfiguration
    {
        $requestConfiguration = new EventItemRequestBuilderDeleteRequestConfiguration();
        return $this->generateRequestConfiguration(
            $requestConfiguration,
            $headers,
            $options
        );
    }

    /**
     * @param array<string, string> $headers
     * @param RequestOptions[] | null $options
     */
    protected function getEventPostBatchRequestConfiguration(array $headers = [], array $options = []): BatchRequestBuilderPostRequestConfiguration
    {
        $requestConfiguration = new BatchRequestBuilderPostRequestConfiguration();
        return $this->generateRequestConfiguration(
            $requestConfiguration,
            $headers,
            $options
        );
    }

    protected function getCalendarViewRequestConfiguration(CalendarViewParamsInterface $params): DeltaRequestBuilderGetRequestConfiguration
    {
        $requestConfiguration = new DeltaRequestBuilderGetRequestConfiguration();
        $queryParameters = DeltaRequestBuilderGetRequestConfiguration::createQueryParameters();
        $queryParameters->startDateTime = $params->getStartDateTime();
        $queryParameters->endDateTime = $params->getEndDateTime();
        $queryParameters->filter = $params->getFilter();
        $queryParameters->orderby = $params->getOrderBy();
        $queryParameters->top = $params->getTop();
        $queryParameters->select = $params->getSelect();
        $queryParameters->expand = $params->getExpand();
        $requestConfiguration->queryParameters = $queryParameters;
        $requestConfiguration->headers = array_merge(
            $this->getAuthorizationHeaders($this->token),
            ['Prefer' => $params->getPreferHeaders()],
            $params->getHeaders()
        );

        return $requestConfiguration;
    }

    /**
     * @param array<string, string> $headers
     * @param RequestOptions[] | null $options
     */
    private function generateRequestConfiguration(InstancesRequestBuilderGetRequestConfiguration | EventsRequestBuilderPostRequestConfiguration | EventItemRequestBuilderPatchRequestConfiguration | BatchRequestBuilderPostRequestConfiguration | EventItemRequestBuilderDeleteRequestConfiguration $configuration, array $headers = [], array $options = []): InstancesRequestBuilderGetRequestConfiguration | EventsRequestBuilderPostRequestConfiguration | EventItemRequestBuilderPatchRequestConfiguration | BatchRequestBuilderPostRequestConfiguration | EventItemRequestBuilderDeleteRequestConfiguration
    {
        $configuration->headers = array_merge(
            $headers,
            $this->getAuthorizationHeaders($this->token)
        );

        $configuration->options = $options;
        return $configuration;
    }
}
