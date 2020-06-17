<?php

namespace Drupal\feeds_youtube\Feeds\Fetcher;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Url;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\Fetcher\FetcherInterface;
use Drupal\feeds\Plugin\Type\ClearableInterface;
use Drupal\feeds\Plugin\Type\PluginBase;
use Drupal\feeds\Result\RawFetcherResult;
use Drupal\feeds\StateInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Constructs FeedsYouTubeFetcher object.
 *
 * @FeedsFetcher(
 *   id = "feeds_youtube_fetcher",
 *   title = @Translation("YouTube"),
 *   description = @Translation("Fetch videos from a YouTube user"),
 *   form = {
 *     "configuration" = "Drupal\feeds_youtube\Feeds\Fetcher\Form\FeedsYouTubeFetcherForm",
 *     "feed" = "Drupal\feeds_youtube\Feeds\Fetcher\Form\FeedsYouTubeFetcherFeedForm",
 *   }
 * )
 */
class FeedsYouTubeFetcher extends PluginBase implements ClearableInterface, FetcherInterface {

  /**
   * {@inheritdoc}
   */
  public function fetch(FeedInterface $feed, StateInterface $state) {
    $result = $this->get($feed->getSource(), $feed->id());
    if ($result !== FALSE) {
      return new RawFetcherResult($result);
    }
    else {
      return new RawFetcherResult('');
    }
  }

  /**
   * Helper function to get client factory.
   *
   * @param string $id
   *   The feed Id.
   *
   * @return Google_Client|null
   */
  public function getClientFactory($id) {
    $config = $this->getConfiguration();
    $cid = $this->getAccessTokenCacheId($id);
    $google_access_token = \Drupal::service('cache.feeds_youtube_tokens')->get($cid);

    if (!empty($config['google_oauth_client_id']) &&
      !empty($config['google_oauth_client_secret']) &&
      !empty($config['google_developer_key'])) {
      $client = new \Google_Client();
      $client->setClientId($config['google_oauth_client_id']);
      $client->setClientSecret($config['google_oauth_client_secret']);
      $client->setApprovalPrompt('force');
      $client->setAccessType('offline');
      $client->setDeveloperKey($config['google_developer_key']);
      $client->setScopes('https://www.googleapis.com/auth/youtube.readonly');
      $current_path = \Drupal::service('path.current')->getPath();
      $path_alias = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);
      $current_url = Url::fromUserInput($path_alias, ['absolute' => TRUE])->toString();
      $redirect = filter_var($current_url, FILTER_SANITIZE_URL);
      $client->setRedirectUri($redirect);

      if (!empty($google_access_token->data)) {
        $client->setAccessToken($google_access_token->data);
        if ($client->isAccessTokenExpired()) {
          $client->refreshToken($client->getRefreshToken());

          // Save refreshed access token.
          $cache_tags = [
            'feeds_youtube:google_access_token',
          ];
          \Drupal::service('cache.feeds_youtube_tokens')->set($cid, $client->getAccessToken(), CacheBackendInterface::CACHE_PERMANENT, $cache_tags);
        }
      }
      return $client;
    }
    else {
      \Drupal::messenger()->addWarning($this->t('Google API access is not configured.'));
    }
  }

  /**
   * Make the API queries to get the data the parser needs.
   *
   * @param string $source
   *   The URL source.
   * @param string $id
   *   The feed Id.
   *
   * @return string
   *   Returns a JSON-encoded array of stdClass objects.
   */
  public function get(String $source, $id) {
    $client = $this->getClientFactory($id);
    $youtube = new \Google_Service_YouTube($client);

    $number_of_pages = $this->getConfiguration('import_video_limit');
    if ($number_of_pages < 1) {
      $number_of_pages = 1;
    }

    $result = [];
    $next_page_token = '';
    for ($i = 0; $i < $number_of_pages; $i++) {
      $api_request_result = $this->requestNextPage($source, $next_page_token, $client, $youtube);
      $next_page_token = (!empty($api_request_result['next_page_token'])) ? $api_request_result['next_page_token'] : '';
      if (!empty($api_request_result['result'])) {
        $result = array_merge($result, $api_request_result['result']);
      }
    }

    return json_encode($result);
  }

  /**
   * Convert YouTube video duration to time interval.
   *
   * @param string $duration
   *   YouTube video duration.
   *
   * @return string
   */
  private function timeToDuration($duration) {
    $di = new \DateInterval($duration);
    $string = '';
    if ($di->h > 0) {
      $string .= $di->h . ':';
    }
    return $string . $di->i . ':' . $di->s;
  }

  /**
   * Parse a YouTube video feed.
   *
   * @param array $video_items
   *
   * @return array
   */
  private function parseVideoItems($video_items) {
    $items = [];

    foreach ($video_items['items'] as $key => $video) {
      $item = [
        'video_id' => '',
        'video_url' => '',
        'title' => '',
        'description' => '',
        'thumbnail_default' => '',
        'thumbnail_medium' => '',
        'thumbnail_high' => '',
        'thumbnail_standard' => '',
        'thumbnail_maxres' => '',
        'category' => '',
        'tags' => '',
        'duration' => '',
        'duration_raw' => '',
        'published_datetime' => '',
        'published_timestamp' => '',
        'view_count' => '',
        'fav_count' => '',
        'likes' => '',
        'dislikes' => '',
        'favorites' => '',
        'embedded_player' => '',
      ];

      if (isset($video['id']) && is_array($video['id']) && isset($video['id']['videoId'])) {
        $item['video_id'] = $video['id']['videoId'];
      }
      elseif (isset($video['contentDetails']) && isset($video['contentDetails']['videoId'])) {
        $item['video_id'] = $video['contentDetails']['videoId'];
      }
      elseif (isset($video['snippet']) && isset($video['snippet']['resourceId']) && isset($video['snippet']['resourceId']['videoId'])) {
        $item['video_id'] = $video['snippet']['resourceId']['videoId'];
      }

      if (!empty($item['video_id'])) {
        $item['video_url'] = 'https://www.youtube.com/watch?v=' . $item['video_id'];
      }

      if (isset($video['snippet'])) {
        $item['title'] = $video['snippet']['title'];
        $item['description'] = $video['snippet']['description'];

        if (isset($video['snippet']['thumbnails']['default'])) {
          $item['thumbnail_default'] = $video['snippet']['thumbnails']['default']['url'];
        }
        if (isset($video['snippet']['thumbnails']['standard'])) {
          $item['thumbnail_standard'] = $video['snippet']['thumbnails']['standard']['url'];
        }
        if (isset($video['snippet']['thumbnails']['medium'])) {
          $item['thumbnail_medium'] = $video['snippet']['thumbnails']['medium']['url'];
        }
        if (isset($video['snippet']['thumbnails']['high'])) {
          $item['thumbnail_high'] = $video['snippet']['thumbnails']['high']['url'];
        }
        if (isset($video['snippet']['thumbnails']['maxres'])) {
          $item['thumbnail_maxres'] = $video['snippet']['thumbnails']['maxres']['url'];
        }

        if (isset($video['snippet']['categoryId'])) {
          $item['category'] = $video['snippet']['categoryId'];
        }

        if (isset($video['snippet']['tags'])) {
          $item['tags'] = implode(', ', $video['snippet']['tags']);
        }

        $published_timestamp = strtotime($video['snippet']['publishedAt']);
      }

      if (isset($video['contentDetails'])) {
        if (isset($video['contentDetails']['duration'])) {
          $item['duration'] = $this->timeToDuration($video['contentDetails']['duration']);
          $item['duration_raw'] = $video['contentDetails']['duration'];
        }

        if (!isset($published_timestamp) && isset($video['contentDetails']['videoPublishedAt'])) {
          $published_timestamp = strtotime($video['snippet']['publishedAt']);
        }
      }

      if (isset($video['statistics'])) {
        $item['view_count'] = $video['statistics']['viewCount'];
        $item['fav_count'] = $video['statistics']['favoriteCount'];
        $item['likes'] = $video['statistics']['likeCount'];
        $item['dislikes'] = $video['statistics']['dislikeCount'];
        $item['favorites'] = $video['statistics']['favoriteCount'];
      }

      if (isset($video['player'])) {
        $item['embedded_player'] = $video['player']['embedHtml'];
      }

      if (isset($published_timestamp)) {
        $item['published_datetime'] = date('Y-m-d H:i:s', $published_timestamp);
        $item['published_timestamp'] = $published_timestamp;
      }

      $items[] = $item;
    }

    return $items;
  }

  /**
   * Request URL.
   *
   * @param string $url
   *   The URL to request.
   * @param string $page_token
   *   The page token for the request.
   *
   * @return array|null
   */
  public function requestUrl($url, $page_token = '') {
    try {
      $url .= '&maxResults=' . $this->getConfiguration('results_per_page');
      $url .= ($page_token != '') ? '&pageToken=' . $page_token : '';
      $api_request_result = \Drupal::httpClient()->get($url);
      $data = (string) $api_request_result->getBody();
      return json_decode($data, TRUE);
    }
    catch (RequestException $e) {
      \Drupal::messenger()->addError($this->t('Request Error: @message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * Perform API request to YouTube API and retrieve search result.
   *
   * @param string $url
   *   The URL to request.
   * @param string $page_token
   *   The page token for the request.
   * @param Google_Client $client
   *   A Google client to use.
   * @param Google_Service_YouTube $youtube
   *   A Google YouTube service to use.
   *
   * @return array|null
   */
  private function requestNextPage($url, $page_token = '', $client, $youtube) {
    $result = [];
    $response = $this->requestUrl($url, $page_token);

    if (!empty($response['items'])) {
      $result = $this->parseVideoItems($response);
    }
    else {
      \Drupal::messenger()->addStatus($this->t('No videos found.'));
    }

    return [
      'result' => $result,
      'next_page_token' => isset($search_response['nextPageToken']) ? $search_response['nextPageToken'] : '',
    ];
  }

  /**
   * Get access token cache ID.
   *
   * @param string $id
   *   The feed Id.
   *
   * @return string
   */
  public function getAccessTokenCacheId($id) {
    return 'feeds_youtube:google_access_token:' . $id;
  }

  /**
   * {@inheritdoc}
   */
  public function clear(FeedInterface $feed, StateInterface $state) {
    $this->onFeedDeleteMultiple([$feed]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'google_developer_key' => '',
      'google_oauth_client_id' => '',
      'google_oauth_client_secret' => '',
      'import_video_limit' => 50,
      'results_per_page' => 50,
    ];
  }

}
