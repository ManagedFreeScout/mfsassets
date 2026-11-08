<?php

// MFS Assets (card #426). Every agent can look assets up (those of organisations they may see);
// changes need "Manage assets", settings/types/delete need an admin (checked in the controllers).
Route::group(['middleware' => ['web', 'auth'], 'namespace' => 'Modules\MFSAssets\Http\Controllers'], function () {
    Route::get('/assets', 'AssetsController@index')->name('mfsassets.index');
    Route::get('/assets/new', 'AssetsController@create')->name('mfsassets.create');
    Route::post('/assets/new', 'AssetsController@store')->name('mfsassets.store');
    Route::get('/assets/export', 'AssetsController@export')->name('mfsassets.export');
    Route::post('/assets/import', 'AssetsController@import')->name('mfsassets.import');
    Route::get('/assets/types', 'TypesController@index')->name('mfsassets.types');
    Route::post('/assets/types', 'TypesController@save')->name('mfsassets.types.save');
    Route::get('/assets/options', 'AssetsController@options')->name('mfsassets.options');
    Route::get('/assets/search', 'AssetsController@search')->name('mfsassets.search');
    Route::get('/assets/{id}', 'AssetsController@show')->where('id', '[0-9]+')->name('mfsassets.show');
    Route::get('/assets/{id}/edit', 'AssetsController@edit')->where('id', '[0-9]+')->name('mfsassets.edit');
    Route::post('/assets/{id}', 'AssetsController@update')->where('id', '[0-9]+')->name('mfsassets.update');
    Route::post('/assets/{id}/delete', 'AssetsController@destroy')->where('id', '[0-9]+')->name('mfsassets.delete');
    Route::post('/assets/{id}/attachments', 'AssetsController@upload')->where('id', '[0-9]+')->name('mfsassets.upload');
    Route::get('/assets/{id}/attachments/{file_id}', 'AssetsController@download')->where(['id' => '[0-9]+', 'file_id' => '[0-9]+'])->name('mfsassets.download');
    Route::post('/assets/{id}/attachments/{file_id}/delete', 'AssetsController@deleteFile')->where(['id' => '[0-9]+', 'file_id' => '[0-9]+'])->name('mfsassets.deletefile');
    Route::post('/assets/{id}/link', 'AssetsController@link')->where('id', '[0-9]+')->name('mfsassets.link');
    Route::get('/customers/{id}/assets', 'AssetsController@customer')->where('id', '[0-9]+')->name('mfsassets.customer');
    Route::get('/assets/{id}/label', 'AssetsController@label')->where('id', '[0-9]+')->name('mfsassets.label');
});

// QR label: agents land on the asset page, everyone else on a short report form (no login).
Route::group(['middleware' => ['web'], 'namespace' => 'Modules\MFSAssets\Http\Controllers'], function () {
    Route::get('/a/{token}', 'ReportController@show')->where('token', '[a-f0-9]{32}')->name('mfsassets.qr');
    Route::post('/a/{token}', 'ReportController@submit')->where('token', '[a-f0-9]{32}')->name('mfsassets.report');
});

// Settings and licence: admins only.
Route::group(['middleware' => ['web', 'auth', 'roles'], 'roles' => ['admin'], 'namespace' => 'Modules\MFSAssets\Http\Controllers'], function () {
    Route::post('/admin/mfsassets/settings', 'SettingsController@save')->name('mfsassets.settings.save');
    Route::post('/admin/mfsassets/license/manage', 'SettingsController@manageLicense')->name('mfsassets.license.manage');
    Route::post('/admin/mfsassets/module-license-action', 'SettingsController@handleModuleLicenseAction')->name('mfsassets.module.license.action');
});

// REST API (v1 item 12): only with the official API & Webhooks module, behind its API key check.
if (\Module::isActive('apiwebhooks') && class_exists('\Modules\ApiWebhooks\Http\Middleware\ApiAuth')) {
    Route::group(['middleware' => ['bindings', \Modules\ApiWebhooks\Http\Middleware\ApiAuth::class], 'prefix' => \Helper::getSubdirectory(true).'api/', 'namespace' => 'Modules\MFSAssets\Http\Controllers'], function () {
        Route::get('/assets/types', 'ApiController@types');
        Route::get('/assets', 'ApiController@index');
        Route::post('/assets', 'ApiController@store');
        Route::get('/assets/{id}', 'ApiController@show')->where('id', '[0-9]+');
        Route::match(['put', 'patch'], '/assets/{id}', 'ApiController@update')->where('id', '[0-9]+');
        Route::delete('/assets/{id}', 'ApiController@destroy')->where('id', '[0-9]+');
        Route::post('/assets/{id}/conversations', 'ApiController@link')->where('id', '[0-9]+');
        Route::delete('/assets/{id}/conversations/{conversation_id}', 'ApiController@unlink')->where(['id' => '[0-9]+', 'conversation_id' => '[0-9]+']);
    });
}
