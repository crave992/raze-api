<?php

namespace Drupal\rhm_store\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\user\Entity\User;

/**
 *
 * @RestResource(
 *   id = "rhm_store_get_address",
 *   label = @Translation("RHM Store Get Address"),
 *   uri_paths = {
 *     "canonical" = "/rhm-store/address/get"
 *   }
 * )
 */
class RhmStoreGetAddress extends ResourceBase {

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
    $user = User::load($this->currentUser->id());
    $profiles = $user->get('customer_profiles')->referencedEntities();

    if($profiles) {
      foreach($profiles as $profile) {
        $result[] = [
          'isDefault' => $profile->is_default->value,
          'details' => [
            'id' => $profile->profile_id->value,
            'address' => $profile->address[0]->getValue()
          ]
        ];
      }

      $response = new ModifiedResourceResponse($result);
    }
    else {
      $response = new ModifiedResourceResponse(0);
    }
    return $response;
  }
}
