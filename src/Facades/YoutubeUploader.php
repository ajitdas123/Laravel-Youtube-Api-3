<?php

/**
 * Created by PhpStorm.
 * User: AD
 * Date: 11/18/2017
 * Time: 2:41 PM
 */

namespace ad\YoutubeUploader\Facades;
use Illuminate\Support\Facades\Facade;
class YoutubeUploader extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'youtubeUploader';
    }
}