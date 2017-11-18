<?php

/**
 * Created by PhpStorm.
 * User: AD
 * Date: 11/18/2017
 * Time: 2:45 PM
 */
/**
 * Route URI's
 */
Route::group(['prefix' => config('youtubeUploader.routes.prefix')], function() {
    /**
     * Authentication
     */
    Route::get(config('youtubeUploader.routes.authentication_uri'), function()
    {
        return redirect()->to(YoutubeUploader::createAuthUrl());
    });
    /**
     * Redirect
     */
    Route::get(config('youtubeUploader.routes.redirect_uri'), function(Illuminate\Http\Request $request)
    {
        if(!$request->has('code')) {
            throw new Exception('$_GET[\'code\'] is not set. Please re-authenticate.');
        }
        $token = YoutubeUploader::authenticate($request->get('code'));
        YoutubeUploader::saveAccessTokenToDB($token);
        return redirect(config('youtubeUploader.routes.redirect_back_uri', '/'));
    });
});