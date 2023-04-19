<?php

namespace Drupal\rhm_store\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\user\Entity\User;

/**
 * @RestResource(
 *   id = "rhm_store_appointment",
 *   label = @Translation("RHM Store Appointment"),
 *   uri_paths = {
 *     "create" = "/rhm-store/appointment"
 *   }
 * )
 */
class RhmStoreAppointment extends ResourceBase {

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
    if (!$this->currentUser->hasPermission('restful post rhm_store_appointment')) {
      // Display the default access denied page.
      throw new AccessDeniedHttpException('Access Denied.');
    }

    if (empty($data)) {
      return new ResourceResponse('Appointment Details is empty');
    }

    // Preferred Date
    $data1 = date('Y-m-d', strtotime($data['preferred_1']));
    $data2 = date('Y-m-d', strtotime($data['preferred_2']));

    // Load Order
    $cartProvider = \Drupal::service('commerce_cart.cart_provider');
    $store        = \Drupal::entityTypeManager()
      ->getStorage('commerce_store')
      ->load(1);
    $order        = $cartProvider->getCart('default', $store);

    // Commerce - Place Order
    $orderStatus = $order->getState();
    $orderStatus->applyTransitionById('place'); // change status
    // Custom order number
    $dateTime = new \DateTime("now");
    $order->set('order_number', $dateTime->format('U') . $order->id());
    if ($order->save()) {
      // Create ECK Record
      $entityType = 'appointment';
      $formData = [
        'type' => 'appointment',
        'title' => $data['type'] . '- #' . $order->id(),
        'field_type' => $data['type'],
        'field_full_name' => $data['full_name'],
        'field_email' => $data['phone'],
        'field_phone' => $data['email'],
        'field_company' => $data['company'],
        'field_address' => $data['address'],
        'field_apartment' => $data['apartment'],
        'field_district' => $data['district'],
        'field_preferred_date_1' => $data1,
        'field_preferred_date_2' => $data2,
        'field_order' => $order->id()
      ];
      $eckEntity = \Drupal::entityTypeManager()->getStorage($entityType)->create($formData);
      $result = $eckEntity->save();

      #todo email ??

      if($result) {
        $order->field_appointment = $eckEntity->id();
        $order->save();
        return new ModifiedResourceResponse(200);
      }
    }

    throw new NotFoundHttpException('Page not found!');
  }
}
