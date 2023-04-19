<?php

namespace Drupal\rhm_store\Plugin\rest\resource;

use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rhm_rest\RhmRest;
use Drupal\rhm_store\Plugin\custom\RhmStoreController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\user\Entity\User;
use Drupal\commerce_order\Entity\Order;

/**
 *
 * @RestResource(
 *   id = "rhm_store_get_orders",
 *   label = @Translation("RHM Store Get Orders"),
 *   uri_paths = {
 *     "canonical" = "/rhm-store/orders/get"
 *   }
 * )
 */
class RhmStoreGetOrders extends ResourceBase {

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

    $rhmStore = new RhmStoreController();
    $limit =  ( \Drupal::request()->query->get('limit') ) ? \Drupal::request()->query->get('limit') : 500;
    $result = [];
    $orders = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->getQuery()
      ->condition('uid', $this->currentUser->id())
//      ->condition('state', 'completed')
      ->sort('completed', 'DESC')
      ->range(0, $limit)
      ->execute();

    if ($orders) {
      foreach ($orders as $key => $value) {
        $order = Order::load($key);
        if ($order) {

          // Line Items
          $lineItems = $rhmStore->orderLineItems($order);
          // Shipping State
          $state= $rhmStore->orderShippingState($order);
          // Shipping Rate
          $shipment = $rhmStore->orderShippingRate($order);
          // Shipping Address
          $shipping = $rhmStore->orderShippingProfile($order);
          // Discount
          $discount = $rhmStore->orderDiscount($order);

          $result[] = [
            'id' => $order->getOrderNumber(),
            'totalAmount' => number_format((float) $order->getTotalPrice()->getNumber(), 2, '.', ','),
            'currency' => $order->getTotalPrice()->getCurrencyCode(),
            'lineItems' => $lineItems,
            'status' => $order->get('state')->value,
            'created' => date('d/m/Y', $order->get('completed')->value),
            'state' => $state,
            'shipment' => $shipment,
            'shipping' => $shipping,
            'discount' => $discount
          ];
        }
      }

      $response = new ModifiedResourceResponse($result);
    }
    else {
      $response = new ModifiedResourceResponse(0);
    }
    return $response;
  }
}
