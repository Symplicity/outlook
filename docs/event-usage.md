# Handling Events
- [Concept](#concept)
- [Example](../example/sync/calendar_sync.php)

## Concept
Events that are fetched via the pull method is converted to a Reader Entity and a method `saveEventLocal` is called with a single ReaderInterface Entity. This entity might be Reader(Single Event/Recurrence) or Occurrence.

For Events that are being synced from internal app, every event has to be an entity of Symplicity\Outlook\Model\Event
```
    $events = [
        0 => new Event()
        1 => new Event()
    ];
```

At a time 20 events are synced, once all the events are pushed the on-completion handling method `handleBatchResponses(?Generator $responses = null)` is called, parameters are array of internal object with key info item and an Event entity.
