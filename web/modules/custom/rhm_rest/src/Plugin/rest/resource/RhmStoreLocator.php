<?php

namespace Drupal\rhm_rest\Plugin\rest\resource;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rhm_rest\RhmRest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 *
 * @RestResource(
 *   id = "rhm_store_locator",
 *   label = @Translation("RHM Store Locator"),
 *   uri_paths = {
 *     "canonical" = "/rhm-store-locator"
 *   }
 * )
 */
class RhmStoreLocator extends ResourceBase {

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

    $result  = [];
    $rhmRest = new RhmRest();
    $rhmUtil = \Drupal::service('rhm_rest_util');
    $lang    = \Drupal::request()->query->get('lang');

    if ($lang === 'en-US' || $lang === 'undefined') {
      $lang = 'en';
    }
    $lang = strtolower($lang);

    $location = \Drupal::request()->query->get('location');
    $type = \Drupal::request()->query->get('type');

    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', 'store');
    if($location != 'all' && $type != 'partner') {
      $query->condition('field_location.entity.tid', $location);
    }
    $query->condition('field_store_type.entity.field_slug', $type);
    $query->sort('created' , 'DESC');
    $entity_ids = $query->execute();

    foreach ($entity_ids as $entity_id) {
      $store = Node::load($entity_id);
      $rhmUtil->translate($store, $lang);

      $link = '';
      if($store->field_link) {
        $link = $store->field_link->getValue()[0]['uri'];
      }

      $result['stores'][] = [
        'title' => $store->getTitle(),
        'address' => $store->body->getValue(),
        'thumbnail' => $rhmRest->loadMedia($store->field_thumbnail, 'vertical'),
        'period' => $store->field_period->value,
        'phone' => $store->field_phone->value,
        'opening' => $store->field_opening_hours->value,
        'link' => $link
      ];
    }

    return new ModifiedResourceResponse($result);
  }
}
