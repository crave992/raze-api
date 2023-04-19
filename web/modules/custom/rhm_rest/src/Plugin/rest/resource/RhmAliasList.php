<?php

namespace Drupal\rhm_rest\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "rhm_alias_list",
 *   label = @Translation("RHM AliasList"),
 *   uri_paths = {
 *     "canonical" = "/rhm-alias-list"
 *   }
 * )
 */
class RhmAliasList extends ResourceBase {

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
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('rhm_portal');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
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

    $query = \Drupal::database()->select('path_alias', 'n');
    $query->addField('n', 'alias');
    $query->addField('n', 'langcode');
    $query->condition('n.status', 1);
    $results = $query->execute();
    $results = $results->fetchAll();

    $output = [];
    foreach($results as $result){
      if($result->langcode == 'en'){
        $output[] = substr($result->alias, 1);
      }else{
        $output[] = $result->langcode . $result->alias;
      }
    }

    $response = new ModifiedResourceResponse($output);
    return $response;


    //throw new NotFoundHttpException('Page not found!');

  }



}
