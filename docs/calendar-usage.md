# Starting with Calendar

To start working with calendars simply extend the Calendar class. There are a bunch of methods that needs to be implemented

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
    public function isBatchRequest(): CalendarInterface 
    {
        return true;
    }
    
    public function saveEventLocal(ReaderEntityInterface $reader) : void 
    {
        // handle events received from outlook
    }
    
    public function deleteEventLocal(ReaderEntityInterface $event) : void
    {
        // handle events deleted from outlook
    }
    
    public function getLocalEvents() : array 
    {
        // setup a return for all events that you want pushed to outlook calendar.
        // A Writer entity is setup for use but u can always create a new one for your specific use case, just make sure it implements WriterInterface
        $event1 = new Writer()
            ->setId('1')
            ->setSubject('testing new outlook events')
            ->setInternalEventType('Php Appointments')
            ->setLocation(new Location(['DisplayName' => 'Test']))
            ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'Testing Calendar']))
            ->setStartDate(new ODateTime(new DateTime('2019-02-20 08:30:00'), 'Eastern Standard Time'))
            ->setEndDate(new ODateTime(new DateTime('2019-02-20 09:00:00'), 'Eastern Standard Time'));
            
        $event2 = new Writer()
            ->setId('2')
            ->setSubject('testing new outlook events - 2')
            ->setInternalEventType('Php Appointments')
            ->setLocation(new Location(['DisplayName' => 'Test']))
            ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'Testing Calendar']))
            ->setStartDate(new ODateTime(new DateTime('2019-02-21 08:30:00'), 'Eastern Standard Time'))
            ->setEndDate(new ODateTime(new DateTime('2019-02-21 09:00:00'), 'Eastern Standard Time'));
                      
        return [$event1, $event2];
    }
    
    public function handlePoolResponses(array $responses = []) : void 
    {
          foreach ($responses as $id => $response) {
                $outlookResponse = $response['response'] ?? null;
                if ($outlookResponse instanceof BatchResponse && $outlookResponse->getStatus() === Promise::FULFILLED) {
                    // Handle Fullfilled mappings.
                } else {
                    // Handle Rejected mappings.
                }
          }
    }
}

```
