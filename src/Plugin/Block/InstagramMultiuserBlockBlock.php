<?php

namespace Drupal\instagram_multiuser_block\Plugin\Block;

use Drupal\instagram_block\Plugin\Block\InstagramBlockBlock;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\SortArray;

/**
 * Extends the Instagram block with multi-user functionality.
 *
 * @Block(
 *   id = "instagram_multiuser_block_block",
 *   admin_label = @Translation("Instagram multiuser block"),
 *   category = @Translation("Social")
 * )
 */
class InstagramMultiuserBlockBlock extends InstagramBlockBlock {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['user_id']['#title'] = 'Access tokens';
    $form['user_id']['#type'] = 'textarea';
    $form['user_id']['#description'] = $this->t('The unique Instagram access tokens for the accounts to be used with this block, one token per line. For example: 3532917500.f93621a.2f7224b551be46568052e2e577f6da05. <a href="@url">How to authenticate with Instagram?</a>', [
      '@url' => Url::fromUri('https://www.drupal.org/node/2746185')->toString(),
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Build a render array to return the Instagram Images.
    $build = array();

    // If no configuration was saved, don't attempt to build block.
    if (empty($this->configuration['user_id'])) {
      return $build;
    }

    $access_tokens = explode("\r\n", $this->configuration['user_id']);

    $results = $posts = [];
    foreach ($access_tokens as $access_token) {
      if (trim($access_token)) {
        list($user_id) = explode('.', trim($access_token));
        // Build url for http request.
        $uri = "https://api.instagram.com/v1/users/{$user_id}/media/recent/";
        $options = [
          'query' => [
            'access_token' => $access_token,
            'count' => $this->configuration['count'],
          ],
        ];
        $url = Url::fromUri($uri, $options)->toString();

        // Get the instagram images and decode.
        $results[$user_id] = $this->fetchData($url);
        $posts = array_merge($posts, $results[$user_id]['data']);
      }
    }

    if (!$posts) {
      return $build;
    }

    usort($posts, function ($a, $b) {
      return SortArray::sortByKeyInt($a, $b, 'created_time');
    });
    $posts = array_reverse($posts);
    $posts = array_slice($posts, 0, $this->configuration['count']);

    foreach ($posts as $post) {
      $build['children'][$post['id']] = array(
        '#theme' => 'instagram_multiuser_block_image',
        '#data' => $post,
        '#href' => $post['link'],
        '#src' => $post['images'][$this->configuration['img_resolution']]['url'],
        '#width' => $this->configuration['width'],
        '#height' => $this->configuration['height'],
      );
    }

    // Add css.
    if (!empty($build)) {
      $build['#attached']['library'][] = 'instagram_block/instagram_block';
    }

    // Cache for a day.
    $build['#cache']['keys'] = [
      'block',
      'instagram_multiuser_block',
      $this->configuration['id'],
      $this->configuration['user_id'],
    ];
    $build['#cache']['context'][] = 'languages:language_content';
    $build['#cache']['max_age'] = $this->configuration['cache_time_minutes'] * 60;

    return $build;
  }

}
