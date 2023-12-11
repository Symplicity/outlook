<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Entities;

use Generator;
use Microsoft\Graph\Generated\Models\Event;
use Microsoft\Graph\Generated\Models\EventType;
use Microsoft\Kiota\Serialization\Json\JsonParseNode;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Entities\Occurrence;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;

class OccurrenceTest extends TestCase
{
    /**
     * @dataProvider getOccurrences
     */
    public function testHydrate(string $data)
    {
        try {
            $json = new JsonParseNode(json_decode($data, true));
            $event = $json->getObjectValue([Event::class, 'createFromDiscriminatorValue']);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $occurrence = (new Occurrence())->hydrate($event);
        $this->assertInstanceOf(ReaderEntityInterface::class, $occurrence);
        $this->assertNotEmpty($occurrence->getId());
        $this->assertNotEmpty($occurrence->getETag());
        $this->assertInstanceOf(DateEntityInterface::class, $occurrence->getDate());
        $this->assertNotEmpty($occurrence->getSeriesMasterId());
        $this->assertEmpty($occurrence->getTitle());
        $this->assertEmpty($occurrence->getDescription());
        $this->assertEmpty($occurrence->getBody());
        $this->assertEmpty($occurrence->getLocation());
        $this->assertEmpty($occurrence->isAllDay());
        $this->assertEmpty($occurrence->getVisibility());
        $this->assertCount(0, $occurrence->getExtensions());
        $this->assertEmpty($occurrence->getWebLink());
        $this->assertEmpty($occurrence->getSensitivityStatus());
        $this->assertEmpty($occurrence->getRecurrence());
        $this->assertEmpty($occurrence->getOrganizer());
        $this->assertEquals(EventType::OCCURRENCE, $occurrence->getEventType()?->value());
    }

    public static function getOccurrences(): Generator
    {
        yield ['{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"DwAAABYAAADsMG1Lfqh6SqUVUv+VbespAAALd6vn\"","id":"1==","seriesMasterId":"foo=","type":"occurrence","start":{"dateTime":"2023-12-05T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-05T07:30:00.0000000","timeZone":"UTC"}}'];

        yield ['{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"CwAAABYAAADsMG1Lfqh6SqUVUv+VbespAAALd6vn\"","id":"2==","seriesMasterId":"foo=","type":"occurrence","start":{"dateTime":"2023-12-06T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-06T07:30:00.0000000","timeZone":"UTC"}}'];

        yield ['{"@odata.type":"#microsoft.graph.event","@odata.etag":"W\/\"RwAAABYAAADsMG1Lfqh6SqUVUv+VbespAAALd6vn\"","id":"3==","seriesMasterId":"foo=","type":"occurrence","start":{"dateTime":"2023-12-07T07:00:00.0000000","timeZone":"UTC"},"end":{"dateTime":"2023-12-07T07:30:00.0000000","timeZone":"UTC"}}'];
    }
}
