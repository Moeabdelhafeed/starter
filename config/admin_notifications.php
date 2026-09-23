<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification type → route mapping
    |--------------------------------------------------------------------------
    |
    | Maps an AdminNotification `type` (set by NotifiesAdmin trait via the
    | model's $notifyType) to a named admin route. The notification UI uses
    | this to make notifications clickable, navigating to the matching index
    | page with a `highlight` query param so the related row glows.
    |
    | Add a row here whenever you put NotifiesAdmin on a new model.
    |
    */

    'routes' => [
        'app_users' => 'app_users',
        'users' => 'users',
        'activity_logs' => 'activity_logs',
    ],
];
