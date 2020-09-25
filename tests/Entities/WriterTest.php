<?php

namespace Symplicity\Outlook\Tests\Entities;

use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Entities\ExtensionWriter;
use Symplicity\Outlook\Entities\Location;
use Symplicity\Outlook\Entities\ODateTime;
use Symplicity\Outlook\Entities\ResponseBody;
use Symplicity\Outlook\Entities\Writer;
use Symplicity\Outlook\Interfaces\Entity\ExtensionWriterInterface;
use Symplicity\Outlook\Interfaces\Entity\WriterInterface;
use Symplicity\Outlook\Utilities\ChangeType;
use Symplicity\Outlook\Utilities\RequestType;
use Symplicity\Outlook\Utilities\SensitivityType;

class WriterTest extends TestCase
{

    /**
     * @dataProvider getData
     * @param WriterInterface $writer
     * @param array $expected
     */
    public function testJsonSerialize(WriterInterface $writer, array $expected)
    {
        $this->assertEquals($expected['url'], $writer->getUrl());
        $this->assertJsonStringEqualsJsonString($expected['json'], json_encode($writer));
        $this->assertEquals($expected['id'], (string) $writer);
        $this->assertEquals($expected['method'], $writer->getMethod());
    }

    public function testWriterExtensions()
    {
        /** @var WriterInterface $writer */
        $writer = (new Writer())
            ->setGuid('ABC')
            ->setId('foo')
            ->method(new RequestType(RequestType::Patch))
            ->setSubject('test')
            ->setInternalEventType('1')
            ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
            ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
            ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time'))
            ->setIsAllDay(true)
            ->setSensitivity(SensitivityType::Confidential)
            ->setLocation(new Location(['DisplayName' => 'NY']))
            ->setRecurrence([
                'Pattern' =>
                    [
                        'Month' => 0,
                        'DayOfMonth' => 0,
                        'FirstDayOfWeek' => 'Sunday',
                        'Index' => 'First',
                        'Type' => 'Daily',
                        'Interval' => 1,
                    ],
                'Range' =>
                    [
                        'NumberOfOccurrences' => '3',
                        'Type' => 'Numbered',
                        'StartDate' => '2019-05-29',
                        'RecurrenceTimeZone' => 'Eastern Standard Time',
                        'EndDate' => '2019-06-01',
                    ],
            ])
            ->setExtensions($this->getExtensionWriter());

        $expectedJson = '{"Subject":"test","Body":{"ContentType":"HTML","Content":"foo"},"Start":{"DateTime":"2019-02-04T16:40:36","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-04T16:50:36","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":"NY"},"Sensitivity":"Confidential","Recurrence":{"Pattern":{"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First","Type":"Daily","Interval":1},"Range":{"NumberOfOccurrences":"3","Type":"Numbered","StartDate":"2019-05-29","RecurrenceTimeZone":"Eastern Standard Time","EndDate":"2019-06-01"}},"IsAllDay":true,"Extensions":[{"@odata.type":"test","ExtensionName":"test123","policyId":"test"}]}';
        $this->assertJsonStringEqualsJsonString($expectedJson, json_encode($writer));
        $this->assertEquals('ABC', (string) $writer);
        $this->assertInstanceOf(RequestType::class, $writer->getRequestType());
        $this->assertRegExp('/ABC/', $writer->getUrl());
        $this->assertEquals(RequestType::Patch, $writer->getMethod());
        $this->assertEquals('{"@odata.type":"test","ExtensionName":"test123","policyId":"test"}', json_encode($writer->getExtensions()));
        $this->assertEquals('ABC', $writer->getGuid());
    }

    public function getData()
    {
        return [
            [(new Writer())
                ->setId('foo')
                ->setSubject('test')
                ->method(new RequestType(RequestType::Post))
                ->setInternalEventType('1')
                ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
                ->setIsAllDay(false)
                ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time'))
                ->setExtensions($this->getExtensionWriter()), [
                    'url' => '/Me/events',
                    'method' => 'POST',
                    'id' => 'foo',
                    'json' => '{"Subject":"test","Body":{"ContentType":"HTML","Content":"foo"},"Start":{"DateTime":"2019-02-04T16:40:36","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-04T16:50:36","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":null},"Sensitivity":"Personal","Recurrence":null,"IsAllDay":false,"Extensions":[{"@odata.type":"test","ExtensionName":"test123","policyId":"test"}]}'
            ]],
            [(new Writer())
                ->setId('foo')
                ->setSubject('test')
                ->method(new RequestType(RequestType::Post))
                ->setInternalEventType('1')
                ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
                ->setIsAllDay(true)
                ->setStartDate(new ODateTime(new \DateTime('2019-02-04 09:00:00'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:00:00'), 'Eastern Standard Time')), [
                'url' => '/Me/events',
                'method' => 'POST',
                'id' => 'foo',
                'json' => '{"Subject":"test","Body":{"ContentType":"HTML","Content":"foo"},"Start":{"DateTime":"2019-02-04T09:00:00","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-04T16:00:00","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":null},"Recurrence":null, "Sensitivity":"Personal", "IsAllDay": true}'
            ]],
            [(new Writer())
                ->setId('foo')
                ->setSensitivity(SensitivityType::Private)
                ->setSubject('test')
                ->method(new RequestType(RequestType::Post))
                ->setInternalEventType('1')
                ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
                ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time')), [
                'url' => '/Me/events',
                'method' => 'POST',
                'id' => 'foo',
                'json' => '{"Subject":"test","Body":{"ContentType":"HTML","Content":"foo"},"Start":{"DateTime":"2019-02-04T16:40:36","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-04T16:50:36","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":null},"Recurrence":null, "Sensitivity":"Private", "IsAllDay": false}'
            ]],
            [(new Writer())
                ->setGuid('ABC')
                ->setId('foo')
                ->method(new RequestType(RequestType::Patch))
                ->setSubject('test')
                ->setInternalEventType('1')
                ->setBody(new ResponseBody(['ContentType' => 'HTML', 'Content' => 'foo']))
                ->setStartDate(new ODateTime(new \DateTime('2019-02-04 16:40:36'), 'Eastern Standard Time'))
                ->setEndDate(new ODateTime(new \DateTime('2019-02-04 16:50:36'), 'Eastern Standard Time'))
                ->setRecurrence([
                    'Pattern' =>
                        [
                            'Month' => 0,
                            'DayOfMonth' => 0,
                            'FirstDayOfWeek' => 'Sunday',
                            'Index' => 'First',
                            'Type' => 'Daily',
                            'Interval' => 1,
                        ],
                    'Range' =>
                        [
                            'NumberOfOccurrences' => '3',
                            'Type' => 'Numbered',
                            'StartDate' => '2019-05-29',
                            'RecurrenceTimeZone' => 'Eastern Standard Time',
                            'EndDate' => '2019-06-01',
                        ],
                ]), [
                'url' => '/Me/events/ABC',
                'id' => 'ABC',
                'method' => 'PATCH',
                'json' => '{"Subject":"test","Body":{"ContentType":"HTML","Content":"foo"},"Start":{"DateTime":"2019-02-04T16:40:36","TimeZone":"Eastern Standard Time"},"End":{"DateTime":"2019-02-04T16:50:36","TimeZone":"Eastern Standard Time"},"Location":{"DisplayName":null},"Recurrence":{"Pattern":{"Month":0,"DayOfMonth":0,"FirstDayOfWeek":"Sunday","Index":"First","Type":"Daily","Interval":1},"Range":{"NumberOfOccurrences":"3","Type":"Numbered","StartDate":"2019-05-29","RecurrenceTimeZone":"Eastern Standard Time","EndDate":"2019-06-01"}}, "Sensitivity": "Personal", "IsAllDay": false}'
            ]]
        ];
    }

    private function getExtensionWriter(): ExtensionWriterInterface
    {
        return new class extends ExtensionWriter {
            public function __construct(array $data = [])
            {
                $this->setODataType('test');
                $this->setExtensionName('test123');
            }

            public function jsonSerialize()
            {
                $json = parent::jsonSerialize();
                $json['policyId'] = 'test';
                return $json;
            }
        };
    }
}
