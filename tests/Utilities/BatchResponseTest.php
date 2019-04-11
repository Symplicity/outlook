<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Utilities;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Utils\BatchResponseInterface;
use Symplicity\Outlook\Utilities\BatchResponse;

class BatchResponseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getResponse
     * @param array $response
     */
    public function testConstruct(array $response = [])
    {
        $response = new BatchResponse($response);
        $this->assertInstanceOf(BatchResponseInterface::class, $response);
        if ($response->getStatus() == Promise::FULFILLED) {
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertInstanceOf(ReaderEntityInterface::class, $response->getResponse());
        } else {
            $this->assertEquals(Promise::REJECTED, $response->getStatus());
            $this->assertNull($response->getResponse());
            $this->assertEquals('Error Communicating with Server', $response->getReason());
        }
    }

    public function getResponse()
    {
        return [
            [['value' => new Response(200, [], '{"@odata.id":"https:\/\/outlook.office.com\/api\/v2.0\/Users(\'foo\')\/Events(\'x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=\')","@odata.etag":"W\/\"ghc\/foo\/\/pA==\"","Id":"AAMkAGM3YjRjZThiLWE4NjQtNDQ5Yi04ZWIyLTViMDUwZTdkYjE1MABGAAAAAABBP8UbNVDQTYPvokpe3hOiBwCCFz_gODC8RYDOifTpl-x9AAAAAAENAACCFz_gODC8RYDOifTpl-x9AAAGNCqaAAA=","CreatedDateTime":"2019-02-01T18:05:03.7354577-05:00","LastModifiedDateTime":"2019-02-04T23:58:49.478552-05:00","ChangeKey":"foo\/\/pA==","Categories":[],"OriginalStartTimeZone":"Eastern Standard Time","OriginalEndTimeZone":"Eastern Standard Time","iCalUId":"foo","ReminderMinutesBeforeStart":15,"IsReminderOn":true,"HasAttachments":false,"Subject":"FooBar","BodyPreview":"CCCCCCC","Importance":"Normal","Sensitivity":"Normal","IsAllDay":true,"IsCancelled":false,"IsOrganizer":false,"ResponseRequested":true,"SeriesMasterId":null,"ShowAs":"Free","Type":"SeriesMaster","WebLink":"https:\/\/outlook.office365.com\/owa\/?itemid=foo%3D&exvsurl=1&path=\/calendar\/item","OnlineMeetingUrl":null,"ResponseStatus":{"Response":"Accepted","Time":"2019-02-01T18:05:25.680242-05:00"},"Body":{"ContentType":"HTML","Content":"test"},"Start":{"DateTime":"2019-02-25T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-26T00:00:00.0000000","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"India","PostalCode":""},"Coordinates":{"Latitude":27.6031,"Longitude":88.6468}},"Locations":[{"DisplayName":"Bar","LocationUri":"","LocationType":"Default","UniqueId":"3f105ea4-0f49-494d-8d8a-a25a5618eb06","UniqueIdType":"LocationStore","Address":{"Type":"Unknown","Street":"","City":"Bar","State":"fooRegion","CountryOrRegion":"US","PostalCode":""},"Coordinates":{"Latitude":32.6031,"Longitude":999.6468}}],"Recurrence":{"Pattern":{"Type":"Daily","Interval":1,"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First"},"Range":{"Type":"EndDate","StartDate":"2019-02-25","EndDate":"2019-02-28","RecurrenceTimeZone":"Eastern Standard Time","NumberOfOccurrences":0}},"Attendees":[{"Type":"Required","Status":{"Response":"None","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}},{"Type":"Required","Status":{"Response":"Accepted","Time":"0001-01-01T00:00:00Z"},"EmailAddress":{"Name":"Insight Test","Address":"test"}}],"Organizer":{"EmailAddress":{"Name":"Outlook Test","Address":"foo@bar.com"}}}')]],
            [['reason' => new RequestException('Error Communicating with Server', new Request('GET', 'test'))]]
        ];
    }
}
