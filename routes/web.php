<?php
/**
 * Created by PhpStorm.
 * User: AD
 * Date: 11/18/2017
 * Time: 10:21 PM
 */
/**
 * Route URI's
 */
Route::group(['prefix' => config('youtubeAPIConfig.routes.prefix')], function() {
    /**
     * Authentication
     */
    Route::get(config('youtubeAPIConfig.routes.authentication_uri'), function()
    {
        return redirect()->to(YoutubeAPI::createAuthUrl());
    });
    /**
     * Redirect
     */
    Route::get(config('youtubeAPIConfig.routes.redirect_uri'), function(Illuminate\Http\Request $request)
    {
        if(!$request->has('code')) {
            throw new Exception('$_GET[\'code\'] is not set. Please re-authenticate.');
        }
        $token = YoutubeAPI::authenticate($request->get('code'));
        YoutubeAPI::saveAccessTokenToDB($token);
        return redirect(config('youtubeAPIConfig.routes.redirect_back_uri', '/'));
    });
});