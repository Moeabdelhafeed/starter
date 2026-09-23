<?php

use App\Models\Page;
use App\Traits\HasUserTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * A throwaway model that opts into the trait, so the test exercises the
 * conversion itself rather than whichever feature happens to use it today.
 */
class TimezoneSubject extends Model
{
    use HasUserTimezone;

    protected $table = 'timezone_subjects';

    protected $guarded = [];

    protected array $userTimezoneDates = ['starts_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }
}

/**
 * The trait reads the header off the current request. These tests save models
 * directly rather than over HTTP, so the header goes on the bound request
 * instance — `withHeader()` would only apply to a request the test client makes.
 */
function viewingFrom(?string $timezone): void
{
    if ($timezone === null) {
        request()->headers->remove('X-Timezone');

        return;
    }

    request()->headers->set('X-Timezone', $timezone);
}

beforeEach(function () {
    viewingFrom(null);

    Schema::create('timezone_subjects', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->dateTime('starts_at')->nullable();
        $table->timestamps();
    });
});

it('stores a wall-clock time as UTC using the timezone the admin is viewing in', function () {
    // 09:00 in Amman (UTC+3) is 06:00 UTC. The database always holds UTC.
    viewingFrom('Asia/Amman');

    $subject = new TimezoneSubject(['starts_at' => '2026-06-01 09:00:00']);
    $subject->save();

    expect($subject->fresh()->getRawOriginal('starts_at'))->toBe('2026-06-01 06:00:00');
});

it('leaves the value alone when the request carries no timezone', function () {
    $subject = new TimezoneSubject(['starts_at' => '2026-06-01 09:00:00']);
    $subject->save();

    // No header means the app timezone (UTC), so there is nothing to shift.
    expect($subject->fresh()->getRawOriginal('starts_at'))->toBe('2026-06-01 09:00:00');
});

it('ignores a header that is not a real timezone', function () {
    viewingFrom('Totally/Made-Up');

    $subject = new TimezoneSubject(['starts_at' => '2026-06-01 09:00:00']);
    $subject->save();

    expect($subject->fresh()->getRawOriginal('starts_at'))->toBe('2026-06-01 09:00:00');
});

it('only converts a field the save actually touched', function () {
    viewingFrom('Asia/Amman');

    $subject = new TimezoneSubject(['starts_at' => '2026-06-01 09:00:00', 'name' => 'first']);
    $subject->save();

    $stored = $subject->fresh()->getRawOriginal('starts_at');

    // Saving an unrelated column must not shift the stored time a second time.
    $subject->name = 'second';
    $subject->save();

    expect($subject->fresh()->getRawOriginal('starts_at'))->toBe($stored);
});

it('converts only the fields the model lists', function () {
    viewingFrom('Asia/Amman');

    $subject = new TimezoneSubject(['starts_at' => '2026-06-01 09:00:00']);
    $subject->save();

    // created_at is framework-managed and is not in $userTimezoneDates, so the
    // trait must not touch it.
    expect($subject->fresh()->created_at->format('H'))->toBe(now()->format('H'));
});

it('is not applied to models that do not opt in', function () {
    viewingFrom('Asia/Amman');

    expect(in_array(HasUserTimezone::class, class_uses_recursive(Page::class), true))->toBeFalse();
});
