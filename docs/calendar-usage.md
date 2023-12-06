# Starting with Calendar

To start working with calendars simply extend your class to the abstract Calendar class.

```
<?php
declare(strict_types=1);

namespace YourPackage;

use Symplicity\Outlook\Calendar;
use Symplicity\Outlook\Entities\Writer;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Entities\ResponseBody;
use Symplicity\Outlook\Interfaces\Utils\BatchResponseInterface;

class OutlookCalendar extends Calendar 
{    
    public function saveEventLocal(ReaderEntityInterface $reader) : void 
    {
        // handle events received from outlook
    }
    
    public function deleteEventLocal(string $eventId) : void
    {
        // handle events deleted from outlook
    }
    
    public function getLocalEvents() : array 
    {
        // setup a return for all events that you want pushed to outlook calendar.

        // Post event to outlook
        $start = new \Microsoft\Graph\Generated\Models\DateTimeTimeZone();
        $start->setTimeZone('Eastern Standard Time');
        $start->setDateTime('2023-11-28 15:00:00');

        $end = new \Microsoft\Graph\Generated\Models\DateTimeTimeZone();
        $end->setTimeZone('Eastern Standard Time');
        $end->setDateTime('2023-11-28 16:00:00');

        $body = new \Microsoft\Graph\Generated\Models\ItemBody();
        $body->setContent('This is a test event');

        $event1 = new \Symplicity\Outlook\Models\Event();
        $event1->setSubject('Test1');
        $event1->setStart($start);
        $event1->setEnd($end);
        $event1->setBody($body);

        // Delete event from outlook
        $event2 = new \Symplicity\Outlook\Models\Event();
        $event2->setIsDelete();
        $event2->setId('ABC==');

        // Patch event to outlook
        $body = new \Microsoft\Graph\Generated\Models\ItemBody();
        $body->setContent('Update event with extension');

        $event3 = new \Microsoft\Graph\Generated\Models\Event();
        $event3->setSubject('Update Event');
        $event3->setId('ADB==');
        $event3->setStart($start);
        $event3->setEnd($end);
        $event3->setBody($body);

        $extension = new \Microsoft\Graph\Generated\Models\OpenTypeExtension();
        $extension->setExtensionName('com.symplicity.test');
        $extension->setAdditionalData([
            'internalId' => '1232133'
        ]);
        $event3->setExtensions([$extension]);
         
        return [$event1, $event2, $event3];
    }
    
    public function handleBatchResponse(?BatchResponseContent $responses): void
    {
        // handle responses from push events request
    }
}
```
