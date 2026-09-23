<?php

/**
 * The create-repo action shells out to the GitHub CLI, so these tests cover the
 * parts that must hold before any shell command runs: who may reach it, and
 * what a repository name is allowed to contain.
 *
 * NOTHING HERE MAY PASS VALIDATION OVER HTTP. The controller shells out to
 * `gh repo create` as soon as validation succeeds, so any request that gets
 * past it creates a real repository on the signed-in GitHub account and
 * commits the working tree. Assert successful input against the rules
 * directly, never through the route.
 */
use App\Http\Controllers\Admin\DevSetting\DevSettingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    // DevSettings only registers in the local environment, so the route does not
    // exist under `testing`. Register the same controller, middleware and name
    // here, then refresh the name lookup so route() can resolve it.
    Route::middleware(['web', 'auth', 'role:super_admin'])
        ->post('/dev-settings/github-repo', [DevSettingController::class, 'createGithubRepo'])
        ->name('dev_settings.github_repo');

    Route::getRoutes()->refreshNameLookups();
});

it('refuses an anonymous visitor', function () {
    $this->post(route('dev_settings.github_repo'), ['name' => 'thing', 'visibility' => 'private'])
        ->assertRedirect(route('login'));
});

it('refuses an admin who is not a super admin', function () {
    $this->actingAs(adminWithPermissions(['users']))
        ->post(route('dev_settings.github_repo'), ['name' => 'thing', 'visibility' => 'private'])
        ->assertForbidden();
});

it('rejects a repository name that could reach the shell', function (string $name) {
    // The name is escaped before it is used, but it is still user input that
    // ends up in a command, so it is constrained at the edge as well.
    $this->actingAs(adminUser())
        ->from('/')
        ->post(route('dev_settings.github_repo'), ['name' => $name, 'visibility' => 'private'])
        ->assertSessionHasErrors('name');
})->with([
    'semicolon' => 'repo; rm -rf /',
    'backtick' => 'repo`id`',
    'subshell' => 'repo$(whoami)',
    'pipe' => 'repo | cat /etc/passwd',
    'space' => 'my repo',
    'slash' => 'owner/repo',
    'quote' => "repo'",
]);

it('accepts the characters GitHub itself allows', function (string $name) {
    // Deliberately NOT an HTTP request: a valid name passes validation and the
    // controller would then really run `gh repo create`, creating a repository
    // on the signed-in account. The rule is asserted on its own instead.
    $validator = Validator::make(
        ['name' => $name],
        ['name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/']],
    );

    expect($validator->passes())->toBeTrue();
})->with([
    'plain' => 'my-project',
    'underscore' => 'my_project',
    'dotted' => 'my.project',
    'digits' => 'project2026',
]);

it('requires a name and a known visibility', function () {
    $this->actingAs(adminUser())->from('/')
        ->post(route('dev_settings.github_repo'), ['visibility' => 'private'])
        ->assertSessionHasErrors('name');

    $this->actingAs(adminUser())->from('/')
        ->post(route('dev_settings.github_repo'), ['name' => 'thing', 'visibility' => 'secret'])
        ->assertSessionHasErrors('visibility');
});

it('caps the name and description lengths', function () {
    // An over-long name fails validation, so the request never reaches the CLI.
    $this->actingAs(adminUser())->from('/')
        ->post(route('dev_settings.github_repo'), [
            'name' => str_repeat('a', 101),
            'visibility' => 'private',
        ])
        ->assertSessionHasErrors('name');

    // The description cap is checked as a rule: pairing it with a *valid* name
    // over HTTP would fail validation only on the description, and any test that
    // gets past validation runs `gh repo create` for real.
    $validator = Validator::make(
        ['description' => str_repeat('a', 351)],
        ['description' => ['nullable', 'string', 'max:350']],
    );

    expect($validator->fails())->toBeTrue();
});
