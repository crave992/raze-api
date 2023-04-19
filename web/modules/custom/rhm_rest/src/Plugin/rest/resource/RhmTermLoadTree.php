<?php

namespace Drupal\rhm_rest\Plugin\rest\resource;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rhm_rest\RhmRest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\user\Entity\User;

/**
 *
 * @RestResource(
 *   id = "rhm_term_load_tree",
 *   label = @Translation("RHM Term Load Tree"),
 *   uri_paths = {
 *     "canonical" = "/rhm-term-load-tree"
 *   }
 * )
 */
class RhmTermLoadTree extends ResourceBase {

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
    $instance              = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger      = $container->get('logger.factory')
      ->get('rhm_portal');
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

    $rhmUtil = \Drupal::service('rhm_rest_util');

    $result  = [];
    $vid = \Drupal::request()->query->get('term');

    if(!$vid) {
      return new ModifiedResourceResponse(100);
    }

    $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
    foreach ($terms as $term) {
      $result[] = $rhmUtil->loadTerm($term->tid);
    }

    return new ModifiedResourceResponse($result);
  }
}
