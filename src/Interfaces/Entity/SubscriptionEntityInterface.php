<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Entity;

interface SubscriptionEntityInterface
{
    /**
     * Sets the data type
     * @param string $dataType
     * @return $this
     */
    public function setDataType(string $dataType): self;

    /**
     * Sets the resource
     * @param string $resource
     * @return $this
     */
    public function setResource(string $resource): self;

    /**
     * The url to call when there is change in event
     * @param string $notificationUrl
     * @return $this
     */
    public function setNotificationUrl(string $notificationUrl): self;

    /**
     * Specifies what types will be notified, Default: Created,Deleted,Updated,Missed
     * @param array $changeType
     * @return $this
     */
    public function setChangeType(array $changeType = []): self;

    /**
     * Specify state that is set for every notification send by outlook
     * Use this to verify authenticity of request
     * @param string $clientState
     * @return $this
     */
    public function setClientState(string $clientState): self;
}
