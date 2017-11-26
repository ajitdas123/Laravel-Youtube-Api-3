<?php

/**
 * Created by PhpStorm.
 * User: AD
 * Date: 11/18/2017
 * Time: 10:27 PM
 */

namespace ad\Youtube;

use Exception;
use Carbon\Carbon;
use Google_Client;
use Google_Service_YouTube;
use Illuminate\Support\Facades\DB;
class YoutubeAPI
{
    /**
     * Application Container
     *
     * @var Application
     */
    private $app;
    /**
     * Google Client
     *
     * @var \Google_Client
     */
    protected $client;
    /**
     * Google YouTube Service
     *
     * @var \Google_Service_YouTube
     */
    protected $youtube;
    /**
     * Video ID
     *
     * @var string
     */
    private $videoId;
    /**
     * Video Snippet
     *
     * @var array
     */
    private $snippet;
    /**
     * Thumbnail URL
     *
     * @var string
     */
    private $thumbnailUrl;
    /**
     * Constructor
     *
     * @param \Google_Client $client
     */
    public function __construct($app, Google_Client $client)
    {
        $this->app = $app;
        $this->client = $this->setup($client);
        $this->youtube = new \Google_Service_YouTube($this->client);
        if ($accessToken = $this->getLatestAccessTokenFromDB()) {
            $this->client->setAccessToken($accessToken);
        }
    }
    /**
     * Upload the video to YouTube
     *
     * @param  string $path
     * @param  array  $data
     * @param  string $privacyStatus
     * @return string
     */
    public function upload($path, array $data = [], $privacyStatus = 'public')
    {
        if(!file_exists($path)) {
            throw new Exception('Video file does not exist at path: "'. $path .'". Provide a full path to the file before attempting to upload.');
        }
        $this->handleAccessToken();
        try {
            // Setup the Snippet
            $snippet = new \Google_Service_YouTube_VideoSnippet();
            if (array_key_exists('title', $data))       $snippet->setTitle($data['title']);
            if (array_key_exists('description', $data)) $snippet->setDescription($data['description']);
            if (array_key_exists('tags', $data))        $snippet->setTags($data['tags']);
            if (array_key_exists('category_id', $data)) $snippet->setCategoryId($data['category_id']);
            // Set the Privacy Status
            $status = new \Google_Service_YouTube_VideoStatus();
            $status->privacyStatus = $privacyStatus;
            // Set the Snippet & Status
            $video = new \Google_Service_YouTube_Video();
            $video->setSnippet($snippet);
            $video->setStatus($status);
            // Set the Chunk Size
            $chunkSize = 1 * 1024 * 1024;
            // Set the defer to true
            $this->client->setDefer(true);
            // Build the request
            $insert = $this->youtube->videos->insert('status,snippet', $video);
            // Upload
            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $insert,
                'video/*',
                null,
                true,
                $chunkSize
            );
            // Set the Filesize
            $media->setFileSize(filesize($path));
            // Read the file and upload in chunks
            $status = false;
            $handle = fopen($path, "rb");
            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                $status = $media->nextChunk($chunk);
            }
            fclose($handle);
            $this->client->setDefer(false);
            // Set ID of the Uploaded Video
            $this->videoId = $status['id'];
            // Set the Snippet from Uploaded Video
            $this->snippet = $status['snippet'];
        }  catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this;
    }
    /**
     * Set a Custom Thumbnail for the Upload
     *
     * @param  string  $imagePath
     *
     * @return self
     */
    public function withThumbnail($imagePath)
    {
        try {
            $videoId = $this->getVideoId();
            $chunkSizeBytes = 1 * 1024 * 1024;
            $this->client->setDefer(true);
            $setRequest = $this->youtube->thumbnails->set($videoId);
            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $setRequest,
                'image/png',
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize(filesize($imagePath));
            $status = false;
            $handle = fopen($imagePath, "rb");
            while (!$status && !feof($handle)) {
                $chunk  = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }
            fclose($handle);
            $this->client->setDefer(false);
            $this->thumbnailUrl = $status['items'][0]['default']['url'];
        } catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this;
    }
    /**
     * Delete a YouTube video by it's ID.
     *
     * @param  int  $id
     *
     * @return bool
     */
    public function delete($id)
    {
        $this->handleAccessToken();
        if (!$this->exists($id)) {
            throw new Exception('A video matching id "'. $id .'" could not be found.');
        }
        return $this->youtube->videos->delete($id);
    }
    /**
     * Check if a YouTube video exists by it's ID.
     *
     * @param  int  $id
     *
     * @return bool
     */
    public function exists($id)
    {
        $this->handleAccessToken();
        $response = $this->youtube->videos->listVideos('status', ['id' => $id]);
        if (empty($response->items)) return false;
        return true;
    }
    /**
     * Return the Video ID
     *
     * @return string
     */
    public function getVideoId()
    {
        return $this->videoId;
    }
    /**
     * Return the snippet of the uploaded Video
     *
     * @return array
     */
    public function getSnippet()
    {
        return $this->snippet;
    }
    /**
     * Return the URL for the Custom Thumbnail
     *
     * @return string
     */
    public function getThumbnailUrl()
    {
        return $this->thumbnailUrl;
    }
    /**
     * Setup the Google Client
     *
     * @param \Google_Client $client
     * @return \Google_Client $client
     */
    private function setup(Google_Client $client)
    {
        if(
            !$this->app->config->get('youtubeAPIConfig.client_id') ||
            !$this->app->config->get('youtubeAPIConfig.client_secret')
        ) {
            throw new Exception('A Google "client_id" and "client_secret" must be configured.');
        }
        $client->setClientId($this->app->config->get('youtubeAPIConfig.client_id'));
        $client->setClientSecret($this->app->config->get('youtubeAPIConfig.client_secret'));
        $client->setScopes($this->app->config->get('youtubeAPIConfig.scopes'));
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $client->setRedirectUri(url(
            $this->app->config->get('youtubeAPIConfig.routes.prefix')
            . '/' .
            $this->app->config->get('youtubeAPIConfig.routes.redirect_uri')
        ));
        return $this->client = $client;
    }
    /**
     * Saves the access token to the database.
     *
     * @param  string  $accessToken
     */
    public function saveAccessTokenToDB($accessToken)
    {
        return DB::table('youtube_access_tokens')->insert([
            'access_token' => json_encode($accessToken),
            'created_at'   => Carbon::createFromTimestamp($accessToken['created'])
        ]);
    }
    /**
     * Get the latest access token from the database.
     *
     * @return string
     */
    public function getLatestAccessTokenFromDB()
    {
        $latest = DB::table('youtube_access_tokens')
            ->latest('created_at')
            ->first();
        return $latest ? (is_array($latest) ? $latest['access_token'] : $latest->access_token ) : null;
    }
    /**
     * Handle the Access Token
     *
     * @return void
     */
    public function handleAccessToken()
    {
        if (is_null($accessToken = $this->client->getAccessToken())) {
            throw new \Exception('An access token is required.');
        }
        if($this->client->isAccessTokenExpired())
        {
            // If we have a "refresh_token"
            if (array_key_exists('refresh_token', $accessToken))
            {
                // Refresh the access token
                $this->client->refreshToken($accessToken['refresh_token']);
                // Save the access token
                $this->saveAccessTokenToDB($this->client->getAccessToken());
            }
        }
    }
    /**
     * Pass method calls to the Google Client.
     *
     * @param  string  $method
     * @param  array   $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->client, $method], $args);
    }

    /***
     * Create a playlist
     */
    public function createPlaylist($name,$descriptions,$privacy)
    {

        $this->handleAccessToken();
        try {

            // 1. Create the snippet for the playlist. Set its title and description.
            $playlistSnippet = new \Google_Service_YouTube_PlaylistSnippet();
            $playlistSnippet->setTitle($name);
            $playlistSnippet->setDescription($descriptions);

            // 2. Define the playlist's status.
            $playlistStatus = new \Google_Service_YouTube_PlaylistStatus();
            $playlistStatus->setPrivacyStatus($privacy);

            // 3. Define a playlist resource and associate the snippet and status
            // defined above with that resource.
            $youTubePlaylist = new \Google_Service_YouTube_Playlist();
            $youTubePlaylist->setSnippet($playlistSnippet);
            $youTubePlaylist->setStatus($playlistStatus);

            // 4. Call the playlists.insert method to create the playlist. The API
            // response will contain information about the new playlist.
            $playlistResponse = $this->youtube->playlists->insert('snippet,status',
                $youTubePlaylist, array());
            //$playlistId = $playlistResponse['id'];

            // 5. Add a video to the playlist. First, define the resource being added
            // to the playlist by setting its video ID and kind.
//            $resourceId = new \Google_Service_YouTube_ResourceId();
//            $resourceId->setVideoId('SZj6rAYkYOg');
//            $resourceId->setKind('youtube#video');

            // Then define a snippet for the playlist item. Set the playlist item's
            // title if you want to display a different value than the title of the
            // video being added. Add the resource ID and the playlist ID retrieved
            // in step 4 to the snippet as well.
//            $playlistItemSnippet = new \Google_Service_YouTube_PlaylistItemSnippet();
//            $playlistItemSnippet->setTitle('First video in the test playlist');
//            $playlistItemSnippet->setPlaylistId($playlistId);


            // Finally, create a playlistItem resource and add the snippet to the
            // resource, then call the playlistItems.insert method to add the playlist
            // item.
//            $playlistItem = new \Google_Service_YouTube_PlaylistItem();
//            $playlistItem->setSnippet($playlistItemSnippet);
//            $playlistItemResponse = $this->youtube->playlistItems->insert(
//                'snippet,contentDetails', $playlistItem, array());

            return $playlistResponse;
        }  catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /***
     * Get all playlist by channel id
     */

    public function getAllPlayList(){
        $this->handleAccessToken();
        try {
            //Set channel id

            //Set limit

            //Set privacy status

            $params =array('mine' => true, 'maxResults' => 25);
            //Array marge
            $response = $this->youtube->playlists->listPlaylists('snippet,contentDetails', $params);
            return $response;
        } catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /***
     * Check if playlist Exists
     */

    public function isPlaylistExist(){
        
    }

    /***
     * Update a playlist
     */

    public function updatePlaylist($id,$title,$description,$privacy)
    {
        $this->handleAccessToken();
        try {
            // 1. Create the snippet for the playlist. Set its title and description.
            $playlistSnippet = new \Google_Service_YouTube_PlaylistSnippet();
            $playlistSnippet->setTitle($title);
            $playlistSnippet->setDescription($description);

            // 2. Define the playlist's status.
            $playlistStatus = new \Google_Service_YouTube_PlaylistStatus();
            $playlistStatus->setPrivacyStatus($privacy);

            // 3. Define a playlist resource and associate the snippet and status
            $youTubePlaylist = new \Google_Service_YouTube_Playlist();
            $youTubePlaylist->setId($id);
            $youTubePlaylist->setSnippet($playlistSnippet);
            $youTubePlaylist->setStatus($playlistStatus);

            // 4. Call the playlists.update method to update the playlist
            $playlistResponse = $this->youtube->playlists->update('snippet,status',
                $youTubePlaylist, array());
            return $playlistResponse;
        }
        catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /***
     * Delete playlist
     */

    public function deletePlaylist($id){
        $this->handleAccessToken();
        try {
            $playlistResponse = $this->youtube->playlists->delete($id);
            return $playlistResponse;
        }catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /**Get playlist information **/
    public function playListInfoById($id){
        $this->handleAccessToken();
        try {

            return $response;
        } catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**Get playlist items **/
    public function playListItemById($id){
        $this->handleAccessToken();
        try {
            $response = $this->youtube->playlistItems->listPlaylistItems( 'snippet,contentDetails',
                array('maxResults' => 25, 'playlistId' => $id));
            return $response;
        } catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}