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
 *   id = "rhm_store_account_user",
 *   label = @Translation("RHM Store Account User"),
 *   uri_paths = {
 *     "create" = "/rhm-store/account/user"
 *   }
 * )
 */
class RhmStoreAccountUser extends ResourceBase {

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
    if (!$this->currentUser->hasPermission('restful post rhm_store_account_user')) {
      // Display the default access denied page.
      throw new AccessDeniedHttpException('Access Denied.');
    }

    $result   = [];
    $action   = $data['action'];
    $name     = (isset($data['name'])) ? $data['name'] : '';
    $email    = (isset($data['email'])) ? $data['email'] : '';
    $phone    = (isset($data['phone'])) ? $data['phone'] : '';
    $rhmStripeEvent = new RhmStripeEvent();

    /** @var  $userNode */
    $userNode = User::load($this->currentUser->id());
    $currentEmail = $userNode->getEmail();

    if ($action === 'update') {

      // Update stripe token if email changed
      $cardsToken = $userNode->get('field_stripe_card_token')->getValue();
      if($currentEmail !== $email && $cardsToken) {
        $customer = $rhmStripeEvent->stripeGetCustomer($userNode);
        $rhmStripeEvent->stripeUpdateCustomer($email, $customer['id']);
      }

      $userNode->set('field_name', $name);
      $userNode->setEmail($email);
      $userNode->set('field_phone', (int)$phone);
      $userNode->save();
    }

    $result = $this->getUserData();


    if ($result) {
      return new ResourceResponse($result);
    }
    throw new NotFoundHttpException('Page not found!');
  }

  public function getUserData() {

    $userNode = User::load($this->currentUser->id());

    return [
      'name' => $userNode->get('field_name')->value,
      'email' => $userNode->getEmail(),
      'phone' => $userNode->get('field_phone')->value
    ];
  }
}
