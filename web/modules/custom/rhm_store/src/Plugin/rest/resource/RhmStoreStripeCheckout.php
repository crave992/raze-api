<?php

namespace Drupal\rhm_store\Plugin\rest\resource;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\profile\Entity\Profile;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\user\Entity\User;
use Drupal\rhm_store\Plugin\custom\RhmStripeEvent;

/**
 * @RestResource(
 *   id = "rhm_store_stripe_checkout",
 *   label = @Translation("RHM Store Stripe Checkout"),
 *   uri_paths = {
 *     "create" = "/rhm-store/stripe/checkout"
 *   }
 * )
 */
class RhmStoreStripeCheckout extends ResourceBase {

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
    if (!$this->currentUser->hasPermission('restful post rhm_store_stripe_checkout')) {
      // Display the default access denied page.
      throw new AccessDeniedHttpException('Access Denied.');
    }

    if (empty($data)) {
      return new ResourceResponse('Order and Card Details is empty');
    }

    $i               = 1;
    $lineItems       = [];
    $metaData        = [];
    $user            = $this->currentUser;
    $userNode        = User::load($user->id());
    $frontEndOrderID = $data['cartID'];
    $shippingAddress = $data['shippingAddress'];
    $billingAddress = $data['billingAddress'];
    $isNewCard       = $data['isNewCard'];
    $isNewCustomer   = FALSE;


    // Load Order
    $cartProvider = \Drupal::service('commerce_cart.cart_provider');
    $store        = \Drupal::entityTypeManager()->getStorage('commerce_store')->load(1);
    $order        = $cartProvider->getCart('default', $store);
    $orderID = $order->id();
    $stockManager = \Drupal::service('commerce_stock.service_manager');
    $totalAmount  = $order->getTotalPrice()->getNumber();
    $currency     = $order->getTotalPrice()->getCurrencyCode();

    if($frontEndOrderID == $orderID) {

      // Check Stock before payment and
      // Set LineItems and Stripe Metadata
      $metaData['orderID'] = $orderID;
      foreach ($order->getItems() as $orderItem) {
        $purchasedEntity = $orderItem->getPurchasedEntity();
//        $avaStock        = (int) $purchasedEntity->field_stock->value;
        $avaStock = intval($stockManager->getStockLevel($purchasedEntity));
        $quantity = (int) $orderItem->quantity->value;
        $unitPrice = (float) $orderItem->unit_price->number;
        $subTotalPrice = $unitPrice * $quantity;
        $alwaysInStock = $purchasedEntity->commerce_stock_always_in_stock->value;

        if (!$alwaysInStock && $avaStock < $orderItem->quantity->value) {
          return new ModifiedResourceResponse(100); // out of stock
          break;
        }

        $lineItems[]            = [
          'id' => $orderItem->id(),
          'title' => $orderItem->title->value,
          'quantity' => (int) $orderItem->quantity->value,
          'price' => number_format((float) $unitPrice, 2, '.', ',') * 100,
          'subTotal' => number_format((float) $subTotalPrice, 2, '.', ',') * 100,
          'currency' => $orderItem->unit_price->currency_code,
        ];
        $metaData['Item_' . $i] = $orderItem->title->value . ' x ' . $quantity;
        $i++;
      }

      // Payment Gateway
      $paymentGateway = \Drupal::entityTypeManager()
        ->getStorage('commerce_payment_gateway')
        ->load('stripe');

      // Stripe
      $rhmStripeEvent = new RhmStripeEvent();
      if($isNewCard) {
        $stripeToken    = $data['token']['id'];
      }
      else {
        $stripeToken = $data['token'];
      }

      // Stripe - Get Customer
      $customer = $rhmStripeEvent->stripeGetCustomer($user);

      // Stripe - Create Customer
      if (empty($customer)) {
        $isNewCustomer = TRUE;
        $customer      = $rhmStripeEvent->stripeCreateCustomer($user, $stripeToken);
      }

      // Stripe - New Card
      if ($isNewCard) {
        if ($isNewCustomer) { // card created when stripe creating customer
          $source = [];
        }
        else {
          $source = $rhmStripeEvent->stripeCreateCard($customer, $stripeToken);
        }
      }
      else { // Stripe - Get Card
        $source = $rhmStripeEvent->stripeGetCard($customer, $stripeToken);
      }


      // Stripe - Create Charge
      $chargeArr = [
        'amount' => (int) ($totalAmount * 100),
        'currency' => $currency,
        'customer' => $customer,
        'source' => $source,
        'metadata' => $metaData
      ];
//      echo json_encode($chargeArr);
      $chargeObj = $rhmStripeEvent->stripeCreateCharge($chargeArr);
//      echo json_encode($chargeObj);

      // Payment Success
      if ($chargeObj->status === 'succeeded') {

        // Commerce - Set Billing Profile
        // Clone the shipping address to a new profile and assign uid to 0
        // Because the address will be removed from address-book if assigned the address to order
        $profile = Profile::create([
          'type' => 'customer',
          'uid' => 0,
        ]);
        $profile->save();
        $profile->address->given_name          = $billingAddress['address']['given_name'];
        $profile->address->family_name         = $billingAddress['address']['family_name'];
        $profile->address->country_code        = $billingAddress['address']['country_code'];
        $profile->address->locality            = $billingAddress['address']['locality'];
        $profile->address->administrative_area = $billingAddress['address']['administrative_area'];
        $profile->address->postal_code         = $billingAddress['address']['postal_code'];
        $profile->address->address_line1       = $billingAddress['address']['address_line1'];
        $profile->address->organization        = $billingAddress['address']['organization'];
        $profile->save();
//        echo json_encode($profile);
        $order->setBillingProfile($profile);

        // Commerce - Create Payment
        try {
          $payment = Payment::create([
            'state' => 'new',
            'amount' => $order->getTotalPrice(),
            'payment_gateway' => $paymentGateway->id(),
            'order_id' => $order->id(),
            'remote_id' => $chargeObj->id,
            'payment_gateway_mode' => $paymentGateway->getPlugin()->getMode(),
            'expires' => 0,
            'uid' => $user->id(),
          ])->save();
        }
        catch (\Exception $e) {
          \Drupal::logger('rhm_store')
            ->error('Exception (' . get_class($e) . '):' . $e->getMessage() . ' (ERR_CODE:1629950281)' . ' (' . __FILE__ . ':' . __LINE__ . ')');
          return FALSE;
        }

        // Commerce - Place Order
        if ($payment) {
          $orderStatus = $order->getState();
          $orderStatus->applyTransitionById('place'); // change status
          // Add new card token
          if ($isNewCard) {
            if ($isNewCustomer) {
              $token = $chargeObj->source->id;
            }
            else {
              $token = $source->id;
            }
            $stripeCardTokenParagraph = Paragraph::create([
              'type' => 'stripe_card_token',
              'field_card_token' => $token,
              'field_default' => 0, #toDO set 1 if it is default
            ]);
            $userNode->field_stripe_card_token[] = $stripeCardTokenParagraph;
          }
          // Custom order number
          $dateTime = new \DateTime("now");
          $order->set('order_number', $dateTime->format('U') . $order->id());
          if ($order->save() && $userNode->save()) {
            return new ModifiedResourceResponse(200);
          }
        }
      }
    }

    throw new NotFoundHttpException('Page not found!');
  }
}
