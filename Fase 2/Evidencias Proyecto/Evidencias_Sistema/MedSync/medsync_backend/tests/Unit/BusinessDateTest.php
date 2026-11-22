<?php

use App\Support\BusinessDate;
use Carbon\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('uses the Chile business date through late evening, midnight, month and year boundaries', function (string $instant, string $expected) {
    Carbon::setTestNow(Carbon::parse($instant));
    expect(BusinessDate::today())->toBe($expected);
})->with([
    ['2026-11-22T21:00:00-03:00', '2026-11-22'],
    ['2026-11-22T22:00:00-03:00', '2026-11-22'],
    ['2026-11-22T23:59:00-03:00', '2026-11-22'],
    ['2026-11-23T00:00:00-03:00', '2026-11-23'],
    ['2026-11-23T00:01:00-03:00', '2026-11-23'],
    ['2026-12-31T23:59:00-03:00', '2026-12-31'],
    ['2027-01-01T00:00:00-03:00', '2027-01-01'],
    ['2026-09-06T03:59:00Z', '2026-09-05'],
    ['2026-09-06T04:00:00Z', '2026-09-06'],
    ['2026-04-05T03:59:00Z', '2026-04-04'],
    ['2026-04-05T04:00:00Z', '2026-04-05'],
]);
