<?php

namespace Drupal\rhm_store\Plugin\rest\resource;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rhm_store\Plugin\custom\RhmStripeEvent;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\profile\Entity\Profile;

/**
 * @RestResource(
 *   id = "rhm_store_account_address",
 *   label = @Translation("RHM Store Account Address"),
 *   uri_paths = {
 *     "create" = "/rhm-store/account/address"
 *   }
 * )
 */
class RhmStoreAccountAddress extends ResourceBase {

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
    if (!$this->currentUser->hasPermission('restful post rhm_store_account_address')) {
      // Display the default access denied page.
      throw new AccessDeniedHttpException('Access Denied.');
    }

    $result = '';
    $user   = User::load($this->currentUser->id());
    $profile             = Profile::load($data['id']);
    $action = $data['action'];

    if($profile) {

      if ($action === 'setDefault') {
        $profile->is_default = 1;
        $profile->save();
        $result = 200;
      }

      elseif ($action === 'delete') {
        $isDefault = ($profile->get('is_default')->value) ? true : false;
        $profile->delete();
        if($isDefault) {
          $profiles = $user->get('customer_profiles')->referencedEntities();
          if($profiles) {
            $profiles[0]->is_default = 1;
            $profiles[0]->save();
          }
        }
        $result = 200;
      }

      elseif ($action === 'update') {
        $profile->address->given_name          = $data['given_name'];
        $profile->address->family_name         = $data['family_name'];
        $profile->address->country_code        = $data['country'];
        $profile->address->locality            = $data['city'];
        $profile->address->administrative_area = $data['region'];
        $profile->address->postal_code         = $data['postal_code'];
        $profile->address->address_line1       = $data['address'];
        $profile->address->organization        = $data['company'];
        $profile->save();
        $result = 200;
      }
    }
    else {
      $result = 100;
    }

    if ($result) {
      return new ResourceResponse($result);
    }
    throw new NotFoundHttpException('Page not found!');
  }
}
