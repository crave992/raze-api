<?php

namespace Drupal\rhm_store\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\profile\Entity\Profile;

/**
 * @RestResource(
 *   id = "rhm_store_save_address",
 *   label = @Translation("RHM Store Save Address"),
 *   uri_paths = {
 *     "create" = "/rhm-store/address/save"
 *   }
 * )
 */
class RhmStoreSaveAddress extends ResourceBase {

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
   * Responds to POST requests.
   *
   * Creates a new node.
   *
   * @param mixed $data
   *   Data to create the node.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($data) {

    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('restful post rhm_store_save_address')) {
      // Display the default access denied page.
      throw new AccessDeniedHttpException('Access Denied.');
    }

    $user  = $this->currentUser;

    $profile = Profile::create([
      'type' => 'customer',
      'uid' => $user->id(),
    ]);
    $profile->save();

    $profile->address->given_name          = $data['given_name'];
    $profile->address->family_name         = $data['family_name'];
    $profile->address->country_code        = $data['country'];
    $profile->address->locality            = $data['city'];
    $profile->address->administrative_area = $data['region'];
    $profile->address->postal_code         = $data['postal_code'];
    $profile->address->address_line1       = $data['address'];
    $profile->address->organization        = $data['company'];
    $profile->is_default          = 1;
    $result                                = $profile->save();

    if ($result) {
      return new ResourceResponse(200);
    }
    throw new NotFoundHttpException('Page not found!');
  }
}
