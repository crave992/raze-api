<?php

namespace Drupal\rhm_store\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @RestResource(
 *   id = "rhm_store_promo",
 *   label = @Translation("RHM Store Promo"),
 *   uri_paths = {
 *     "create" = "/rhm-store/promo"
 *   }
 * )
 */
class RhmStorePromo extends ResourceBase {

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
    if (!$this->currentUser->hasPermission('restful post rhm_store_promo')) {
      // Display the default access denied page.
      throw new AccessDeniedHttpException('Access Denied.');
    }

    $result    = '';
    $action = $data['action'];
    $promoCode = $data['promo_code'];

    // Load Order
    $cartProvider = \Drupal::service('commerce_cart.cart_provider');
    $store        = \Drupal::entityTypeManager()
      ->getStorage('commerce_store')
      ->load(1);
    $order        = $cartProvider->getCart('default', $store);

    if($action === 'remove') {
      $order->set('coupons', []);
      $order->save();
      $result = '400';
    }

    if($action === 'apply') {

      $coupon = \Drupal::entityTypeManager()
        ->getStorage('commerce_promotion_coupon')
        ->loadEnabledByCode($promoCode);
      if (!empty($coupon)) {
        if (!$coupon->available($order)) {
          $result = '100';
        }
        elseif (!$coupon->getPromotion()->applies($order)) {
          $result = '300';
        }
        else {
          $order->set('coupons', [$coupon->id()]);
          $order->save();
          $result = '200';
        }
      }
      else {
        $result = '100';
      }
    }


    if ($result) {
      return new ResourceResponse($result);
    }
    throw new NotFoundHttpException('Page not found!');
  }
}
