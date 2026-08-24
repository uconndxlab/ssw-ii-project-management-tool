<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum upload size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Used for agreement attachments and activity logging field documents.
    | Laravel's "max" file rule expects kilobytes. Ensure PHP upload_max_filesize
    | and post_max_size meet or exceed this value on the server.
    |
    */

    'max_file_kb' => (int) env('UPLOAD_MAX_KB', 51200),

    'temporary_url_minutes' => (int) env('UPLOAD_TEMPORARY_URL_MINUTES', 5),

];
