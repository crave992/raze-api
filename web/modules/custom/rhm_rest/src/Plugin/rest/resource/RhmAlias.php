<?php

namespace Drupal\rhm_rest\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rhm_rest\RhmRest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "rhm_alias",
 *   label = @Translation("RHM Alias"),
 *   uri_paths = {
 *     "canonical" = "/rhm-alias"
 *   }
 * )
 */
class RhmAlias extends ResourceBase {

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
    $slug =  \Drupal::request()->query->get('slug');
    $lang =  \Drupal::request()->query->get('lang');

    if((string)$slug == 'undefined') $slug = '/';

    if($lang === 'en-US' || $lang === 'undefined'){
      $lang = 'en';
    }
    $lang = strtolower($lang);

    $alias = str_replace(',', '/', $slug);

    if($alias[0] != '/'){
      $alias = '/' . $alias;
    }

    if($alias == '/'){ // Homepage
      $config = \Drupal::config('system.site');
      $path = $config->get('page.front');
    }else{
      $path = \Drupal::service('path_alias.manager')->getPathByAlias($alias, $lang);
    }

    if(preg_match('/node\/(\d+)/', $path, $matches)) {
      $node = Node::load($matches[1]);
      $rhmRest = new RhmRest();
      $data = $rhmRest->loadFields('node', $node, $lang);

      $response = new ModifiedResourceResponse($data);
//        $metadata = new CacheableMetadata();
//        $metadata->addCacheContexts(['url.query_args:slug']);
//        $response->addCacheableDependency($metadata);
//        $response->addCacheableDependency($node);
      return $response;
    }


    throw new NotFoundHttpException('Page not found!');

  }



}
