<?php
/**
 * Created by PhpStorm.
 * User: AD
 * Date: 11/18/2017
 * Time: 10:30 PM
 */

namespace ad\Youtube;

use Illuminate\Support\ServiceProvider;
class YoutubeAPIServiceProvider extends ServiceProvider
{
    /**
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
        $config = realpath(__DIR__.'/../config/youtubeAPIConfig.php');
        $this->publishes([$config => config_path('youtubeAPIConfig.php')], 'config');
        $this->mergeConfigFrom($config, 'youtubeAPIConfig');
        $this->publishes([
            __DIR__.'/../migrations/' => database_path('migrations')
        ], 'migrations');
        if($this->app->config->get('youtubeAPIConfig.routes.enabled')) {
            include __DIR__.'/../routes/web.php';
        }
    }
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->singleton('youtube', function($app) {
            return new YoutubeAPI($app, new \Google_Client);
        });
    }
}