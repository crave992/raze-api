<?php

namespace Drupal\rhm_rest\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Drupal\rest_menu_items\Plugin\rest\resource\RestMenuItemsCacheableDependency;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 *
 * @RestResource(
 *   id = "rhm_site_settings",
 *   label = @Translation("RHM Site Settings"),
 *   uri_paths = {
 *     "canonical" = "/rhm-site-settings"
 *   }
 * )
 */
class RhmSiteSettings extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('rhm_portal');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {


    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!(\Drupal::currentUser())->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $result = [];
    $section = \Drupal::request()->query->get('section');

    if(!$section) {
      return new ModifiedResourceResponse($result);
    }

    // Site Settings
    $site_settings = \Drupal::service('site_settings.loader');
    $fieldset = $site_settings->loadByFieldset($section);

    if($fieldset[$section]) {

      switch ($section) {
        case 'footer_content' :

            if($fieldset[$section]['field_logo']) {
              $media = Media::load($fieldset[$section]['field_logo']);
              $fid = $media->field_media_image->target_id;
              $file = File::load($fid);
              $uri        = $file->getFileUri();
              $result['logo'] = file_create_url($uri);
            }

            $result['body'] = $fieldset[$section]['field_body']['value'];

            if($fieldset[$section]['field_fb_link']) {
              $url = Url::fromUri($fieldset[$section]['field_fb_link']['uri'])->toString(TRUE)->getGeneratedUrl();
              $result['fb_link']['url'] = $url;
            }

            if($fieldset[$section]['field_ig_link']) {
              $url = Url::fromUri($fieldset[$section]['field_ig_link']['uri'])->toString(TRUE)->getGeneratedUrl();
              $result['ig_link']['url'] = $url;
            }

            break;

        case 'global_link' :

          $links = [];
          $fields = ['field_store_link'];

          foreach ($fields as $field) {
            if($fieldset[$section][$field]) {
              $url = Url::fromUri($fieldset[$section][$field]['uri'])->toString(TRUE)->getGeneratedUrl();
              $links[$field] = ['title' => $fieldset[$section][$field]['title'], 'url' => $url];;
            }
          }
          $result = $links;
          break;

        default: break;
      }
    }

    // return new ModifiedResourceResponse($result);

    // Return response.
    $response = new ResourceResponse($result);

    // Configure caching for minDepth and maxDepth parameters.
    if ($response instanceof CacheableResponseInterface) {
      $response->addCacheableDependency(new RestMenuItemsCacheableDependency($section, 0, 1));
    }

    // Return the JSON response.
    return $response;
  }
}
