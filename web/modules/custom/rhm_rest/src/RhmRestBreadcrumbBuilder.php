<?php

namespace Drupal\rhm_rest;

use Drupal\system\PathBasedBreadcrumbBuilder;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

class RhmRestBreadcrumbBuilder extends PathBasedBreadcrumbBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {

    $url = Url::fromRoute($route_match->getRouteName(), $route_match->getParameters()->all());

    if ($request = $this->getRequestForPath($url->toString(), [])) {
      $context = new RequestContext();
      $context->fromRequest($request);
      $this->context = $context;
    }

    // Build breadcrumbs using new context ($route_match is unused)
    return parent::build($route_match);
  }

}
