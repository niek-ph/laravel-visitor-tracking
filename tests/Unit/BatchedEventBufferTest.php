<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use NiekPH\LaravelVisitorTracking\BatchedEventBuffer;
use NiekPH\LaravelVisitorTracking\Jobs\InsertEventsJob;
use NiekPH\LaravelVisitorTracking\TrackingEvents\TrackingEvent;
use NiekPH\LaravelVisitorTracking\VisitorTracking;

uses(RefreshDatabase::class);

function createTrackingEvent(string $name = 'test_event'): TrackingEvent
{
    $request = Request::create('/test', 'GET');
    $request->headers->set('User-Agent', 'Test Browser');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    return new class($name, $request) extends TrackingEvent
    {
        public function __construct(
            private string $eventName,
            private Request $request
        ) {
            parent::__construct($this->request);
        }

        public function getName(): string
        {
            return $this->eventName;
        }

        public function getData(): array
        {
            return ['test' => 'data'];
        }
    };
}

it('can hold single event', function () {
    $buffer = new BatchedEventBuffer;
    $event = createTrackingEvent();

    expect($buffer->isEmpty())->toBeTrue();

    $buffer->push($event);

    expect($buffer->isEmpty())->toBeFalse();
    expect($buffer->getAll())->toHaveCount(1);
    expect($buffer->getAll()[0])->toBe($event);
});

it('can hold multiple events', function () {
    $buffer = new BatchedEventBuffer;
    $event1 = createTrackingEvent('page_view');
    $event2 = createTrackingEvent('click');
    $event3 = createTrackingEvent('form_submit');

    $buffer->push($event1);
    $buffer->push($event2);
    $buffer->push($event3);

    expect($buffer->isEmpty())->toBeFalse();
    expect($buffer->getAll())->toHaveCount(3);
    expect($buffer->getAll()[0])->toBe($event1);
    expect($buffer->getAll()[1])->toBe($event2);
    expect($buffer->getAll()[2])->toBe($event3);
});

it('can push multiple events at once', function () {
    $buffer = new BatchedEventBuffer;
    $events = [
        createTrackingEvent('event1'),
        createTrackingEvent('event2'),
        createTrackingEvent('event3'),
    ];

    $buffer->pushAll($events);

    expect($buffer->isEmpty())->toBeFalse();
    expect($buffer->getAll())->toHaveCount(3);
    expect($buffer->getAll())->toBe($events);
});

it('can mix push and push all', function () {
    $buffer = new BatchedEventBuffer;
    $event1 = createTrackingEvent('single');
    $batchEvents = [
        createTrackingEvent('batch1'),
        createTrackingEvent('batch2'),
    ];
    $event2 = createTrackingEvent('another_single');

    $buffer->push($event1);
    $buffer->pushAll($batchEvents);
    $buffer->push($event2);

    expect($buffer->getAll())->toHaveCount(4);
    expect($buffer->getAll()[0])->toBe($event1);
    expect($buffer->getAll()[1])->toBe($batchEvents[0]);
    expect($buffer->getAll()[2])->toBe($batchEvents[1]);
    expect($buffer->getAll()[3])->toBe($event2);
});

it('dispatches job at end of request lifecycle', function () {
    Queue::fake();

    // Simulate adding events to buffer through VisitorTracking
    $event1 = createTrackingEvent('page_view');
    $event2 = createTrackingEvent('click');

    VisitorTracking::track($event1);
    VisitorTracking::track([$event2]);

    // No job should be dispatched yet
    Queue::assertNothingPushed();

    // Simulate application termination (end of request lifecycle)
    app()->terminate();

    // Now the job should be dispatched with all buffered events
    Queue::assertPushed(InsertEventsJob::class, function ($job) use ($event1, $event2) {
        return count($job->events) === 2
            && $job->events[0] === $event1
            && $job->events[1] === $event2;
    });

    Queue::assertPushed(InsertEventsJob::class, 1);
});

it('does not dispatch job when buffer is empty', function () {
    Queue::fake();

    // Don't add any events to the buffer

    // Simulate application termination
    app()->terminate();

    // No job should be dispatched
    Queue::assertNothingPushed();
});

it('buffer is scoped per request', function () {
    $buffer1 = app()->make(BatchedEventBuffer::class);
    $buffer2 = app()->make(BatchedEventBuffer::class);

    // Should be the same instance within the same request
    expect($buffer1)->toBe($buffer2);

    $event = createTrackingEvent();
    $buffer1->push($event);

    // Both references should see the same data
    expect($buffer1->getAll())->toHaveCount(1);
    expect($buffer2->getAll())->toHaveCount(1);
});

it('handles large number of events', function () {
    $buffer = new BatchedEventBuffer;
    $events = [];

    // Add 100 events
    for ($i = 0; $i < 100; $i++) {
        $event = createTrackingEvent("event_{$i}");
        $events[] = $event;
        $buffer->push($event);
    }

    expect($buffer->getAll())->toHaveCount(100);
    expect($buffer->getAll())->toBe($events);
});
