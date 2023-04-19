<?php

namespace Drupal\rhm_rest\Plugin\rest\resource;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rhm_rest\RhmRest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 *
 * @RestResource(
 *   id = "rhm_certificates",
 *   label = @Translation("RHM Certificates"),
 *   uri_paths = {
 *     "canonical" = "/rhm-certificates"
 *   }
 * )
 */
class RhmCertificates extends ResourceBase {

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


    $page  = \Drupal::request()->query->get('page');

    $limit = 1;
    $min   = ($page === 1) ? 0 : ($page - 1) * $limit;
    $max   = ($page === 1) ? $limit : $page * $limit;

    if($page == 1) {
      $countQuery = \Drupal::entityQuery('node');
      $countQuery->condition('status', 1);
      $countQuery->condition('type', 'certificates');
      $total = $countQuery->count()->execute();
      $result['total'] = ceil($total / $limit);
    }

    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', 'certificates');
    $query->range($min, $max);
    $entity_ids = $query->execute();

    foreach ($entity_ids as $entity_id) {
      $certificate = Node::load($entity_id);
      $rhmUtil->translate($certificate, $lang);
      $fileID = $certificate->field_file->getValue()[0]['target_id'];
      $file = Media::load($fileID);
      $result['certificates'][] = [
        'nid' => $certificate->id(),
        'title' => $certificate->getTitle(),
        'body' => $certificate->body->value,
        'file' => file_create_url($file->field_media_document->entity->getFileUri())
      ];
    }

    return new ModifiedResourceResponse($result);
  }
}
