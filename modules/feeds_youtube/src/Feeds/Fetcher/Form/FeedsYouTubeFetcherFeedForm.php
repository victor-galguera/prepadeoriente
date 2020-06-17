<?php

namespace Drupal\feeds_youtube\Feeds\Fetcher\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\FeedsPluginInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\feeds\Plugin\Type\ExternalPluginFormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form on the feed edit page for the FeedsYouTubeFetcher.
 */
class FeedsYouTubeFetcherFeedForm extends ExternalPluginFormBase implements ContainerInjectionInterface {

  /**
   * The Feeds plugin.
   *
   * @var \Drupal\feeds\Plugin\Type\FeedsPluginInterface
   */
  protected $plugin;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * Constructs a FeedsYouTubeFetcherFeedForm form object.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->privateTempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setPlugin(FeedsPluginInterface $plugin) {
    $this->plugin = $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    $form['source'] = [
      '#title' => $this->t('Feed URL'),
      '#type' => 'url',
      '#default_value' => $feed->getSource(),
      '#maxlength' => 2048,
      '#required' => TRUE,
    ];

    // Authenticate/revoke access to Google Account.
    $config = $this->plugin->getConfiguration();
    if ($feed->id() && !empty($config['google_oauth_client_id']) &&
      !empty($config['google_oauth_client_secret']) &&
      !empty($config['google_developer_key'])) {

      // Get existing token (if one exists) and check for code query parameter.
      $cid = $this->plugin->getAccessTokenCacheId($feed->id());
      $google_access_token = \Drupal::service('cache.feeds_youtube_tokens')->get($cid);
      $code = \Drupal::request()->query->get('code');

      $form['google_access_token'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Google OAuth 2.0 Authentication'),
      ];

      if (!empty($code)) {
        $state = \Drupal::request()->query->get('state');
        $tempstore_state = $this->privateTempStoreFactory->get('feeds_youtube')->get('state');
        if (strval($tempstore_state) !== strval($state)) {
          \Drupal::messenger()->addError($this->t('The session state did not match. Please try again.'));
        }
        else {
          $client = $this->plugin->getClientFactory($feed->id());
          $client->authenticate($code);

          // Save access token.
          $cache_tags = [
            'feeds_youtube:google_access_token',
            $cid,
          ];
          \Drupal::service('cache.feeds_youtube_tokens')->set($cid, $client->getAccessToken(), CacheBackendInterface::CACHE_PERMANENT, $cache_tags);

          // Clean up and let the user know they successfully authenticated.
          $this->privateTempStoreFactory->get('feeds_youtube')->delete('state');
          \Drupal::messenger()->addStatus($this->t('You have successfully authenticated your website to use the Google API.'));

          // Redirect back to the original page.
          $current_path = \Drupal::service('path.current')->getPath();
          $response = new RedirectResponse($current_path, 302);
          $response->send();
        }
      }
      elseif (empty($google_access_token->data)) {
        $current_path = \Drupal::service('path.current')->getPath();
        $path_alias = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);

        $form['google_access_token']['info'] = [
          '#type' => 'markup',
          '#markup' => '<p><em>' . $this->t('Before authenticating, you must add <a href=":link">this URL</a> as an authorized redirect URI to your Google OAuth 2.0 client ID.', [
            ':link' => Url::fromUserInput($path_alias, ['absolute' => TRUE])->toString(),
          ]) . '</em></p>',
        ];

        $client = $this->plugin->getClientFactory($feed->id());
        $state = mt_rand();
        $client->setState($state);
        $this->privateTempStoreFactory->get('feeds_youtube')->set('state', $state);

        // Create authentication link.
        $url = Url::fromUri($client->createAuthUrl());
        $auth_link = Link::fromTextAndUrl($this->t('Authenticate Google Account'), $url);
        $auth_link = $auth_link->toRenderable();
        $auth_link['#attributes'] = ['class' => ['button', 'button--primary']];
        $form['google_access_token']['grant_access'] = [
          '#type' => 'markup',
          '#markup' => '<p>' . render($auth_link) . '</p>',
        ];
      }
      else {
        $form['google_access_token']['info'] = [
          '#type' => 'markup',
          '#markup' => '<p><em>' . $this->t('Last authenticated: @date', [
            '@date' => format_date($google_access_token->data['created'], 'long'),
          ]) . '</em></p>',
        ];
        $form['google_access_token']['revoke_access'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Revoke access to Google Account'),
          '#default_value' => FALSE,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    $source = $form_state->getValue('source');

    try {
      $test = $this->plugin->requestUrl($source);
    } catch (\Google_Service_Exception $e) {
      $form_state->setError($form['source'], $this->t('A service error occurred: %error', [
        '%error' => htmlspecialchars($e->getMessage())
      ]));
    } catch (\Google_Exception $e) {
      $form_state->setError($form['source'], $this->t('A client error occurred: %error', [
        '%error' => htmlspecialchars($e->getMessage())
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    if ($form_state->getValue(['google_access_token', 'revoke_access'])) {
      // Revoke access token.
      $client = $this->plugin->getClientFactory($feed->id());
      $client->revokeToken($client->getAccessToken());

      // Clear cached token.
      Cache::invalidateTags([$this->plugin->getAccessTokenCacheId($feed->id())]);

      // Let user know the access token has been revoked.
      \Drupal::messenger()->addStatus($this->t('Google API access token revoked.'));
    }

    $feed->setSource($form_state->getValue('source'));
  }

}
