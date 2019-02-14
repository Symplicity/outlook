# Handling Events
- [Concept](#concept)

## Concept
Every time sync is called the outlook package will try to send all internal events to outlook calendar, and then pull the events from outlook. With the given parameters outlook will fetch events using the api.

```
'endPoint' => 'me/calendarview', <---- endpoint for getting the events
'queryParams' => [
    'startDateTime' => date("Y-m-d\TH:i:s", strtotime('January 1 -2 years')), <-- Query from date
    'endDateTime' => date("Y-m-d\TH:i:s", strtotime('January 1 +2 years')), <--- Query end date
    'delta' => $data['delta_token'] <--- delta token received from outlook if its not an initial sync
],
```

Once the events have been fetched its converted to a Reader Entity and a method `saveEventLocal` is called with a single ReaderInterface Entity. This entity might be Reader(Single Event/Recurrence) or Occurrence.

For Events that are being synced from internal , every event has to be an entity of WriteInterface
```
    $events = [
        0 => new Write()
        1 => new Write()
    ];
```
If you set batch send to true then 20 events are asynced at a time, Once all the events are handled the handling on completion method `handlePoolResponses(array $responses = [])` is called, parameters are array of internal object with key item and a BatchResponse entity. For batch send all the events that fail will be retried 3 times if the response has a specific code and then returned as a part of handlePoolResponses with the exception options.

For single event sync `handleResponse(array $failedToWrite = [])` is called with all the ids that could not be sent to outlook.




 