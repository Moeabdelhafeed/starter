<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dynamic Storage size caps (in kilobytes)
    |--------------------------------------------------------------------------
    |
    | Per-kind upload limits for the dynamic media store. Applied in
    | Api\Media\MediaController after the media type is inferred from the
    | uploaded file's mime. Tune via .env.
    |
    */

    'max_image_kb' => env('MEDIA_MAX_IMAGE_KB', 2048),

    'max_video_kb' => env('MEDIA_MAX_VIDEO_KB', 20480),

    'max_file_kb' => env('MEDIA_MAX_FILE_KB', 10240),

];
