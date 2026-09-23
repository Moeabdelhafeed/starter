<?php

use App\Models\Language;
use App\Models\NotificationTemplate;
use App\Models\Page;

/**
 * A system template is one application code sends by looking it up with
 * `forSystemType()`. The copy belongs to the admin; the key and the trigger belong to the
 * code, and the row may never be deleted out from under it.
 */
beforeEach(function () {
    Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true],
    );
});

it('refuses to delete a system template from the CMS', function () {
    $template = NotificationTemplate::factory()->system('order_shipped')->create();

    $this->actingAs(adminUser())->from(route('notification_templates'))
        ->delete(route('notification_templates.destroy', $template))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(NotificationTemplate::find($template->id))->not->toBeNull();
});

it('refuses the delete even when the controller is bypassed', function () {
    $template = NotificationTemplate::factory()->system('order_shipped')->create();
    $template->saveTranslations(['title' => ['en' => 'Shipped']]);

    expect($template->delete())->toBeFalse()
        ->and(NotificationTemplate::find($template->id))->not->toBeNull()
        // The guard runs before HasTranslations' deleting listener, so a cancelled delete
        // must not have taken the copy with it.
        ->and($template->fresh()->getTranslation('title', 'en'))->toBe('Shipped');
});

it('skips system templates in a bulk delete and deletes the rest', function () {
    $system = NotificationTemplate::factory()->system('order_shipped')->create();
    $ordinary = NotificationTemplate::factory()->create();

    $this->actingAs(adminUser())->from(route('notification_templates'))
        ->delete(route('notification_templates.bulk-destroy'), ['ids' => [$system->id, $ordinary->id]])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(NotificationTemplate::find($system->id))->not->toBeNull()
        ->and(NotificationTemplate::find($ordinary->id))->toBeNull();
});

it('removes an ordinary template\'s copy along with it', function () {
    $template = NotificationTemplate::factory()->create();
    $template->saveTranslations(['title' => ['en' => 'Sale']]);

    $this->actingAs(adminUser())->from(route('notification_templates'))
        ->delete(route('notification_templates.bulk-destroy'), ['ids' => [$template->id]])
        ->assertRedirect();

    expect(NotificationTemplate::find($template->id))->toBeNull()
        ->and($template->translations()->count())->toBe(0);
});

it('keeps a system template on its key and trigger when an admin edits it', function () {
    $template = NotificationTemplate::factory()
        ->system('order_shipped')
        ->triggeredBy(Page::class, 'created')
        ->create();

    // Everything the code owns is sent as something else; none of it may take effect.
    $this->actingAs(adminUser())
        ->put(route('notification_templates.update', $template), [
            'slug' => 'renamed',
            'topic' => 'guests',
            'trigger_model' => null,
            'trigger_event' => null,
            'is_active' => false,
            'translations' => ['title' => ['en' => 'Reworded'], 'body' => ['en' => 'New copy']],
        ])->assertSessionHasNoErrors();

    $template->refresh();

    expect($template->slug)->toBe('order_shipped')
        ->and($template->trigger_model)->toBe(Page::class)
        ->and($template->trigger_event)->toBe('created')
        // Copy, topic and the on/off switch are the admin's.
        ->and($template->getTranslation('title', 'en'))->toBe('Reworded')
        ->and($template->topic)->toBe('guests')
        ->and($template->is_active)->toBeFalse();
});

it('cannot be created as a system template from the CMS', function () {
    $this->actingAs(adminUser())
        ->post(route('notification_templates.store'), [
            'slug' => 'sneaky',
            'system_type' => 'order_shipped',
            'topic' => 'users',
            'translations' => ['title' => ['en' => 'Hi'], 'body' => ['en' => 'There']],
        ])->assertRedirect();

    expect(NotificationTemplate::where('slug', 'sneaky')->first()->system_type)->toBeNull();
});

it('looks a system template up by its type', function () {
    $template = NotificationTemplate::factory()->system('order_shipped')->create();

    expect(NotificationTemplate::forSystemType('order_shipped')?->id)->toBe($template->id)
        ->and(NotificationTemplate::forSystemType('never_seeded'))->toBeNull();
});
