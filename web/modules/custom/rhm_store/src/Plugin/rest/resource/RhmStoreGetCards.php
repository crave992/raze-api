<?php

namespace Drupal\rhm_store\Plugin\rest\resource;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rhm_store\Plugin\custom\RhmStripeEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\user\Entity\User;

/**
 *
 * @RestResource(
 *   id = "rhm_store_get_cards",
 *   label = @Translation("RHM Store Get Cards"),
 *   uri_paths = {
 *     "canonical" = "/rhm-store/cards/get"
 *   }
 * )
 */
class RhmStoreGetCards extends ResourceBase {

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

    $result = [];
    $user = User::load($this->currentUser->id());
    $rhmStripeEvent = new RhmStripeEvent();
    $customer = $rhmStripeEvent->stripeGetCustomer($user);

    if ($user) {
      $cardsToken = $user->get('field_stripe_card_token')->getValue();
      if($cardsToken) {
        foreach ($cardsToken as $cardToken) {
          $paragraph = Paragraph::load($cardToken['target_id']);
          $card = $rhmStripeEvent->stripeGetCard($customer, $paragraph->field_card_token->value);
          if($card) {
            $result[] = [
              'isDefault' => $paragraph->field_default->value,
              'paragraphID' => $cardToken['target_id'],
              'details' => [
                'token' => $card['id'],
                'brand' => $card['brand'],
                'name' => $card['name'],
                'last4' => $card['last4'],
                'exp_month' => $card['exp_month'],
                'exp_year' => $card['exp_year'],
              ]
            ];
          }
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
