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

/**
 * @RestResource(
 *   id = "rhm_store_account_payment",
 *   label = @Translation("RHM Store Account Payment"),
 *   uri_paths = {
 *     "create" = "/rhm-store/account/payment"
 *   }
 * )
 */
class RhmStoreAccountPayment extends ResourceBase {

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
    if (!$this->currentUser->hasPermission('restful post rhm_store_account_payment')) {
      // Display the default access denied page.
      throw new AccessDeniedHttpException('Access Denied.');
    }

    $result = '';
    $user   = User::load($this->currentUser->id());
    $action = $data['action'];

    if ($action === 'setDefault') {

      $newDefault = Paragraph::load($data['paragraphID']);
      if ($newDefault) {

        // reset all to 0 first
        $cardsToken = $user->get('field_stripe_card_token')->getValue();
        if ($cardsToken) {
          foreach ($cardsToken as $cardToken) {
            $cardParagraph                       = Paragraph::load($cardToken['target_id']);
            $cardParagraph->field_default->value = 0;
            $cardParagraph->save();
          }
        }
        // set new one to 1
        $newDefault->field_default->value = 1;
        $newDefault->save();
        $result = 200;
      }
    }
    else {

      $rhmStripeEvent = new RhmStripeEvent();
      $customer       = $rhmStripeEvent->stripeGetCustomer($user);

      if ($action === 'delete') {
        $deleteCard = Paragraph::load($data['paragraphID']);
        if ($deleteCard) {

          // Remove value from User
          $cardsToken    = $user->get('field_stripe_card_token')->getValue();
          $indexToRemove = array_search($data['paragraphID'], array_column($cardsToken, 'target_id'));
          $user->get('field_stripe_card_token')->removeItem($indexToRemove);
          $user->save();

          // Delete card from Stripe
          $removeStripe = $rhmStripeEvent->stripeDeleteCard($customer, $deleteCard->field_card_token->value);

          // Remove paragraph
          if ($removeStripe) {
            $deleteCard->delete();
            $result = 200;
          }
        }
      }

      elseif ($action === 'update') {
        $card  = Paragraph::load($data['paragraphID']);
        $month = explode('/', $data['expiry'])[0];
        $year  = explode('/', $data['expiry'])[1];

        if ($card) {
          $cardToken = $card->field_card_token->value;
          $result    = $rhmStripeEvent->stripeUpdateCard($customer, $cardToken, $data['name'], $month, $year);
        }
      }

      elseif ($action === 'create') {
        $newCard = $rhmStripeEvent->stripeCreateCard($customer, $data['token']['id']);
        if($newCard) {
          $stripeCardTokenParagraph = Paragraph::create([
            'type' => 'stripe_card_token',
            'field_card_token' => $data['token']['card']['id'],
            'field_default' => 0,
          ]);
          $user->field_stripe_card_token[] = $stripeCardTokenParagraph;
          $user->save();
          $result = 200;
        }
      }
    }


    if ($result) {
      return new ResourceResponse($result);
    }
    throw new NotFoundHttpException('Page not found!');
  }
}
