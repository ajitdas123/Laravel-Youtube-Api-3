<?php

namespace ad\YoutubeUploader;

use Illuminate\Support\ServiceProvider;

class YoutubeUploaderServiceProvider extends ServiceProvider
{ /**
 * Indicates if loading of the provider is deferred.
 *
 * @var bool
 */
    protected $defer = false;
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        $config = realpath(__DIR__.'/../config/youtubeUploader.php');
        $this->publishes([$config => config_path('youtubeUploader.php')], 'config');
        $this->mergeConfigFrom($config, 'youtubeUploader');
        $this->publishes([
            __DIR__.'/../migrations/' => database_path('migrations')
        ], 'migrations');
        if($this->app->config->get('youtubeUploader.routes.enabled')) {
            include __DIR__.'/../routes/web.php';
        }
    }
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->singleton('youtubeUploader', function($app) {
            return new YoutubeUploader($app, new \Google_Client);
        });
    }
}
