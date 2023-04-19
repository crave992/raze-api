<?php

namespace Drupal\rhm_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\profile\Entity\Profile;

/**
 * @RestResource(
 *   id = "rhm_contact_us_form",
 *   label = @Translation("RHM Contact Us Form"),
 *   uri_paths = {
 *     "create" = "/rhm-contact-us-form"
 *   }
 * )
 */
class RhmContactUsForm extends ResourceBase {

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
    if (!$this->currentUser->hasPermission('restful post rhm_contact_us_form')) {
      // Display the default access denied page.
      throw new AccessDeniedHttpException('Access Denied.');
    }

    $entityType = 'contact_us_form';
    $formData = [
      'type' => 'contact_us_form_data',
      'field_name' => $data['name'],
      'field_phone' => $data['phone'],
      'field_email' => $data['email'],
      'field_type' => $data['clientType'],
      'field_message' => $data['enquiry'],
    ];
    $eckEntity = \Drupal::entityTypeManager()->getStorage($entityType)->create($formData);
    $result = $eckEntity->save();

    #todo email ??

    if ($result) {
      return new ResourceResponse(200);
    }
    throw new NotFoundHttpException('Page not found!');
  }
}
