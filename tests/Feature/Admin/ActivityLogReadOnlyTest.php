<?php

use App\Models\ActivityLog;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The activity log is an audit trail, so it is read-only: the panel had a delete button on
 * every row and a bulk delete, which let an admin erase the record of what they had done.
 */
function activityLogReader(): User
{
    $admin = User::factory()->create(['is_active' => true]);
    $role = Role::firstOrCreate(['name' => 'log_reader', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'activity_logs', 'guard_name' => 'web']));
    $admin->assignRole($role);

    return $admin;
}

function activityLogEntry(): ActivityLog
{
    return ActivityLog::create([
        'causer_name' => 'Admin',
        'causer_email' => 'admin@example.com',
        'subject_type' => User::class,
        'subject_id' => 1,
        'action' => 'updated',
    ]);
}

it('has no route to delete one entry or many', function () {
    $admin = activityLogReader();
    $log = activityLogEntry();

    // No route at all: 404 for an id, 405 where a GET happens to share the path.
    expect($this->actingAs($admin)->post("/activity-logs/{$log->id}", ['_method' => 'DELETE'])->status())->toBeIn([404, 405])
        ->and($this->actingAs($admin)->post('/activity-logs/bulk-destroy', ['_method' => 'DELETE', 'ids' => [$log->id]])->status())->toBeIn([404, 405]);

    expect(ActivityLog::whereKey($log->id)->exists())->toBeTrue();
});

it('refuses a delete from code as well', function () {
    $log = activityLogEntry();

    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
    expect(ActivityLog::whereKey($log->id)->exists())->toBeTrue();
});

it('still lists and exports the log', function () {
    $admin = activityLogReader();
    activityLogEntry();

    $this->withoutVite()->actingAs($admin)->get(route('activity_logs'))->assertOk();
    $this->actingAs($admin)->get(route('activity_logs.export'))->assertOk();
});
