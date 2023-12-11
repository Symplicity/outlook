<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView;

use Microsoft\Graph\Core\Models\PageResult as GraphPageResult;
use Microsoft\Kiota\Abstractions\Serialization\ParseNode;
use Microsoft\Kiota\Abstractions\Serialization\SerializationWriter;

class PageResult extends GraphPageResult
{
    private ?string $odataDeltaLink = null;

    public function getOdataDeltaLink(): ?string
    {
        return $this->odataDeltaLink;
    }

    public function setOdataDeltaLink(?string $odataDeltaLink): PageResult
    {
        $this->odataDeltaLink = $odataDeltaLink;
        return $this;
    }

    public function createFromDiscriminatorValue(ParseNode $parseNode): PageResult
    {
        return new PageResult();
    }

    public function getFieldDeserializers(): array
    {
        $deserializers = parent::getFieldDeserializers();
        $deserializers['@odata.deltaLink'] = fn (ParseNode $parseNode) => $this->setOdataDeltaLink($parseNode->getStringValue());
        return $deserializers;
    }

    public function serialize(SerializationWriter $writer): void
    {
        parent::serialize($writer);
        $writer->writeStringValue('@odata.deltaLink', $this->getOdataDeltaLink());
    }
}
