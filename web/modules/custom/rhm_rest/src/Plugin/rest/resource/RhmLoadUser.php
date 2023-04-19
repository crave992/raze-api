<?php

namespace Drupal\rhm_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\user\Entity\User;

/**
 *
 * @RestResource(
 *   id = "rhm_load_user",
 *   label = @Translation("RHM Load User"),
 *   uri_paths = {
 *     "canonical" = "/rhm-load-user"
 *   }
 * )
 */
class RhmLoadUser extends ResourceBase {

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

    $result =[];
    $userNode = User::load($this->currentUser->id());

    if($userNode) {
      $logout_path = \Drupal::service('router.route_provider')->getRouteByName('user.logout.http');
      $logout_path = ltrim($logout_path->getPath(), '/');
      $logout_token = \Drupal::service('csrf_token')->get($logout_path);

      $result = [
        'name' =>$userNode->get('name')->value,
        'logoutToken' => $logout_token
      ];
    }

    return new ModifiedResourceResponse($result);
  }
}
