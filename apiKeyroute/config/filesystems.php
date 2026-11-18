<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('/'),
            'throw' => false,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0755,
                ],
            ],
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('/'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0755,
                ],
            ],
        ],

        'storage-2' => [
            'driver' => 's3',
            'endpoint' => env('ENDPOINT_URL'),
            'key' => env('ACCESS_KEY'),
            'secret' => env('SECRET_KEY'),
            'region' => env('DEFAULT_REGION'),
            'bucket' => env('BUCKET_NAME'),
            'use_path_style_endpoint' => false,
        ],

        'storage-3' => [
            'driver' => 's3',
            'endpoint' => env('ENDPOINT_URL'),
            'key' => env('ACCESS_KEY'),
            'secret' => env('SECRET_KEY'),
            'region' => env('DEFAULT_REGION'),
            'bucket' => env('BUCKET_NAME'),
        ],

        'google' => [
            'driver' => 'google',
            'service' => [
                'account' => storage_path('app/google-drive.json'),
            ],
            'options' => [
                'folderId' => env('GOOGLE_DRIVE_FOLDER_ID'),
            ],
        ],

        'airplus-ftp' => [
            'driver' => 'ftp',
            'host' => env('AIRPLUS_FTP_SERVER'),
            'username' => env('AIRPLUS_FTP_ACCOUNT'),
            'password' => env('AIRPLUS_FTP_PASSWORD'),
            // 'port' => env('AIRPLUS_FTP_PORT', 21),
            // 'root' => env('FTP_ROOT'),
            // 'passive' => true,
            // 'ssl' => true,
            // 'timeout' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('/'),
    ],

];
