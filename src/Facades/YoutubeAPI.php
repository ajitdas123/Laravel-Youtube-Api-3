<?php
/**
 * Created by PhpStorm.
 * User: AD
 * Date: 11/18/2017
 * Time: 10:22 PM
 */

namespace ad\Youtube\Facades;
use Illuminate\Support\Facades\Facade;

class YoutubeAPI extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'youtubeAPI';
    }
}