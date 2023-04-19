<?php

namespace Drupal\rhm_store\Plugin\rest\resource;

use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\profile\Entity\Profile;


/**
 * @RestResource(
 *   id = "rhm_store_shipping",
 *   label = @Translation("RHM Store Shipping"),
 *   uri_paths = {
 *     "create" = "/rhm-store/shipping"
 *   }
 * )
 */
class RhmStoreShipping extends ResourceBase {

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
    $instance                       = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger               = $container->get('logger.factory')->get('rhm_portal');
    $instance->currentUser          = $container->get('current_user');
    $instance->shippingOrderManager = $container->get('commerce_shipping.order_manager');
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
    if (!$this->currentUser->hasPermission('restful post rhm_store_shipping')) {
      // Display the default access denied page.
      throw new AccessDeniedHttpException('Access Denied.');
    }

    $result = '';
    $isEstimate = false;
    $user   = $this->currentUser;

    // Load Order
    $cartProvider = \Drupal::service('commerce_cart.cart_provider');
    $store        = \Drupal::entityTypeManager()->getStorage('commerce_store')->load(1);
    $order        = $cartProvider->getCart('default', $store);

    // Shipping Method
    $shipping_method_storage = \Drupal::entityTypeManager()->getStorage('commerce_shipping_method');

    // Profile
    $address = (isset($data['address'])) ? $data['address'] : '';
    if ($address) {
      $profile = Profile::load($address['details']['id']);
    }
    else {
      // temporary estimate
      $isEstimate = true;
      $profile = Profile::create([
        'type' => 'customer',
        'address' => [
          'country_code' => $data['country'],
        ],
      ]);
    }

    // Delete and unset shipment if exist
    if ($this->shippingOrderManager->hasShipments($order)) {
      $shipment = Shipment::load($order->get('shipments')
        ->getValue()[0]['target_id']);
      $shipment->delete();
      $order->set('shipments', []);
      $order->save();
    }

    // Create a new shipment
    $shipments = $this->shippingOrderManager->pack($order, $profile);
    foreach ($shipments as $shipment) {
      $shipment->order_id->entity = $order;
      $shipping_methods           = $shipping_method_storage->loadMultipleForShipment($shipment);
      foreach ($shipping_methods as $shipping_method) {
        $shipping_method_plugin = $shipping_method->getPlugin();
        $shipping_rates         = $shipping_method_plugin->calculateRates($shipment);
        foreach ($shipping_rates as $shipping_rate) {
          $amount = $shipping_rate->getAmount();
          $shipment->setShippingMethod($shipping_method);
          $shipment->setAmount($amount);
          $shipment->save();
          $order->set('shipments', $shipment);
          $order->save();
          $result = '200';
        }

        // Remove the profile if is estimate
        if($isEstimate) {
          $profile->delete();
        }

        break;
      }
    }


    if ($result) {
      return new ResourceResponse(200);
    }
    throw new NotFoundHttpException('Page not found!');
  }
}
