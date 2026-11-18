<?php

use App\Http\Controllers\Api\Panel\V2\AsteriskAmiController;
use App\Http\Controllers\Panel\S3Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('asterisk')->controller(AsteriskAmiController::class)->middleware(['authWithJwt'])->group(function () {
    // AMI Connection & Testing
    Route::get('test-connection', 'testConnection');
    Route::get('channels/active', 'getActiveChannels');
    Route::get('channel/status', 'getChannelStatus');

    // Call Management
    Route::post('call/make', 'makeCall');
    Route::post('call/hangup', 'hangupCall');
    Route::post('call/sync', 'syncActiveCallsToCallLogs');

    // SMS & USSD
    Route::post('sms/send', 'sendSms');
    Route::post('ussd/send', 'sendUssd');

    // Custom Actions
    Route::post('action/execute', 'executeAction');

    // WebSocket Testing
    Route::get('websocket/test', 'testWebSocketConnection');
    Route::get('websocket/stats', 'getWebSocketStats');
    Route::post('notification/test', 'sendTestNotification');

    // Comprehensive Testing
    Route::get('comprehensive-test', 'comprehensiveTest');

    Route::get('connect-check', 'quickConnectionCheck');

    // Ingest batched AMI events from central connector
    Route::post('events/ingest', 'ingestEvents');
});

Route::controller(S3Controller::class)->middleware(['authWithJwt'])->group(function () {
    Route::post('upload/s3', 'uploadFile');
    Route::delete('media/s3', 'deleteMedia');
    Route::post('upload/profile', 'profilePhoto');
});
