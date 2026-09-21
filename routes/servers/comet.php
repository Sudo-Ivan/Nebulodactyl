<?php


use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Client;
use Pterodactyl\Http\Controllers\Api\Client\Servers;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Comet;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Activity\AccountSubject;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\Http\Middleware\Api\Client\Server\CheckDaemonType;


/*
|--------------------------------------------------------------------------
| Client Control API
|--------------------------------------------------------------------------
|
| Endpoint: /api/client/servers/comet/{server}
|
*/

Route::group([
    'prefix' => '/{server}',
    'middleware' => [
        ServerSubject::class,
        AuthenticateServerAccess::class,
        ResourceBelongsToServer::class,
        CheckDaemonType::class . ':comet',
    ],
], function () {
    Route::get('/', [Comet\ServerController::class, 'index'])->name('api:client:server.comet.view');
    Route::get('/websocket', Comet\WebsocketController::class)->name('api:client:server.comet.ws');
    Route::get('/resources', Comet\ResourceUtilizationController::class)->name('api:client:server.comet.resources');
    Route::get('/activity', Comet\ActivityLogController::class)->name('api:client:server.comet.activity');

    Route::post('/command', [Comet\CommandController::class, 'index']);
    Route::post('/power', [Comet\PowerController::class, 'index']);

    Route::group(['prefix' => '/databases'], function () {
        Route::get('/', [Comet\DatabaseController::class, 'index']);
        Route::post('/', [Comet\DatabaseController::class, 'store']);
        Route::post('/{database}/rotate-password', [Comet\DatabaseController::class, 'rotatePassword']);
        Route::delete('/{database}', [Comet\DatabaseController::class, 'delete']);
    });

    Route::group(['prefix' => '/files'], function () {
        Route::get('/list', [Comet\FileController::class, 'directory']);
        Route::get('/contents', [Comet\FileController::class, 'contents']);
        Route::get('/download', [Comet\FileController::class, 'download']);
        Route::put('/rename', [Comet\FileController::class, 'rename']);
        Route::post('/copy', [Comet\FileController::class, 'copy']);
        Route::post('/write', [Comet\FileController::class, 'write']);
        Route::post('/compress', [Comet\FileController::class, 'compress']);
        Route::post('/decompress', [Comet\FileController::class, 'decompress']);
        Route::post('/delete', [Comet\FileController::class, 'delete']);
        Route::post('/create-folder', [Comet\FileController::class, 'create']);
        Route::post('/chmod', [Comet\FileController::class, 'chmod']);
        Route::post('/pull', [Comet\FileController::class, 'pull'])->middleware(['throttle:30,1']);
        Route::get('/upload', Comet\FileUploadController::class);
    });

    Route::group(['prefix' => '/schedules'], function () {
        Route::get('/', [Comet\ScheduleController::class, 'index']);
        Route::post('/', [Comet\ScheduleController::class, 'store']);
        Route::get('/{schedule}', [Comet\ScheduleController::class, 'view']);
        Route::post('/{schedule}', [Comet\ScheduleController::class, 'update']);
        Route::post('/{schedule}/execute', [Comet\ScheduleController::class, 'execute']);
        Route::delete('/{schedule}', [Comet\ScheduleController::class, 'delete']);

        Route::post('/{schedule}/tasks', [Comet\ScheduleTaskController::class, 'store']);
        Route::post('/{schedule}/tasks/{task}', [Comet\ScheduleTaskController::class, 'update']);
        Route::delete('/{schedule}/tasks/{task}', [Comet\ScheduleTaskController::class, 'delete']);
    });

    Route::group(['prefix' => '/network'], function () {
        Route::get('/allocations', [Comet\NetworkAllocationController::class, 'index']);
        Route::post('/allocations', [Comet\NetworkAllocationController::class, 'store']);
        Route::post('/allocations/{allocation}', [Comet\NetworkAllocationController::class, 'update']);
        Route::post('/allocations/{allocation}/primary', [Comet\NetworkAllocationController::class, 'setPrimary']);
        Route::delete('/allocations/{allocation}', [Comet\NetworkAllocationController::class, 'delete']);
    });

    Route::group(['prefix' => '/users'], function () {
        Route::get('/', [Servers\SubuserController::class, 'index']);
        Route::post('/', [Servers\SubuserController::class, 'store']);
        Route::get('/{user}', [Servers\SubuserController::class, 'view']);
        Route::post('/{user}', [Servers\SubuserController::class, 'update']);
        Route::delete('/{user}', [Servers\SubuserController::class, 'delete']);
    });

    Route::group(['prefix' => '/backups'], function () {
        Route::get('/', [Comet\BackupController::class, 'index']);
        Route::post('/', [Comet\BackupController::class, 'store']);
        Route::get('/{backup}', [Comet\BackupController::class, 'view']);
        Route::get('/{backup}/download', [Comet\BackupController::class, 'download']);
        Route::post('/{backup}/lock', [Comet\BackupController::class, 'toggleLock']);
        Route::post('/{backup}/restore', [Comet\BackupController::class, 'restore']);
        Route::delete('/{backup}', [Comet\BackupController::class, 'delete']);
    });

    Route::group(['prefix' => '/startup'], function () {
        Route::get('/', [Comet\StartupController::class, 'index']);
        Route::put('/variable', [Comet\StartupController::class, 'update']);
        Route::put('/command', [Comet\StartupController::class, 'updateCommand']);
        Route::get('/command/default', [Comet\StartupController::class, 'getDefaultCommand']);
        Route::post('/command/process', [Comet\StartupController::class, 'processCommand']);
    });

    Route::group(['prefix' => '/settings'], function () {
        Route::post('/rename', [Comet\SettingsController::class, 'rename']);
        Route::post('/reinstall', [Comet\SettingsController::class, 'reinstall']);
        Route::put('/docker-image', [Comet\SettingsController::class, 'dockerImage']);
        Route::put('/egg', [Comet\SettingsController::class, 'changeEgg']);
        Route::post('/egg/preview', [Comet\SettingsController::class, 'previewEggChange'])
            ->middleware('server.operation.rate-limit');
        Route::post('/egg/apply', [Comet\SettingsController::class, 'applyEggChange'])
            ->middleware('server.operation.rate-limit');
    });
});
