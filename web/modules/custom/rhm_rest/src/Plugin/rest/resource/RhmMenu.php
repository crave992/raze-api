<?php

namespace Drupal\rhm_rest\Plugin\rest\resource;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Drupal\rest_menu_items\Plugin\rest\resource\RestMenuItemsCacheableDependency;
use Drupal\rhm_rest\RhmRestUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 *
 * @RestResource(
 *   id = "rhm_menu",
 *   label = @Translation("RHM Menu"),
 *   uri_paths = {
 *     "canonical" = "/rhm-menu"
 *   }
 * )
 */
class RhmMenu extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * A list of menu items.
   *
   * @var array
   */
  protected $menuItems = [];

  /**
   * A instance of the entitytype manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The maximum depth we want to return the tree.
   *
   * @var int
   */
  protected $maxDepth = 0;

  /**
   * The minimum depth we want to return the tree from.
   *
   * @var int
   */
  protected $minDepth = 1;

  /**
   * @var string
   */
  protected $language = '';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance              = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger      = $container->get('logger.factory')
      ->get('rhm_portal');
    $instance->currentUser = $container->get('current_user');
    $instance->language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $instance->rhmUnti = \Drupal::service('rhm_rest_util');
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

    $menuName = \Drupal::request()->query->get('menu_name');


    if ($menuName) {
      // Create the parameters.
      $parameters = new MenuTreeParameters();
      $parameters->onlyEnabledLinks();

      // Load the tree based on this set of parameters.
      $menu_tree = \Drupal::menuTree();
      $tree      = $menu_tree->load($menuName, $parameters);

      // Return if the menu does not exist or has no entries.
      if (empty($tree)) {
        $response = new ResourceResponse($tree);

        if ($response instanceof CacheableResponseInterface) {
          $response->addCacheableDependency(new RestMenuItemsCacheableDependency($menuName));
        }

        return $response;
      }

      // Finally, build a renderable array from the transformed tree.
      $menu = $menu_tree->build($tree);

      // Return if the menu has no entries.
      if (empty($menu['#items'])) {
        return new ResourceResponse([]);
      }

      $this->rhmGetMenuItems($menu['#items'], $this->menuItems);

      // Return response.
      $response = new ResourceResponse(array_values($this->menuItems));

      // Configure caching for minDepth and maxDepth parameters.
      if ($response instanceof CacheableResponseInterface) {
        $response->addCacheableDependency(new RestMenuItemsCacheableDependency($menuName, $this->minDepth, $this->maxDepth));
      }

      // Return the JSON response.
      return $response;
    }
    throw new HttpException($this->t("Menu name was not provided"));
  }

  /**
   * Generate the menu tree we can use in JSON.
   *
   * @param array $tree
   *   The menu tree.
   * @param array $items
   *   The already created items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function rhmGetMenuItems(array $tree, array &$items = []) {

    $outputValues = [
      'key',
      'title',
      'description',
      'uri',
      'alias',
      'external',
      'absolute',
      'relative',
      'existing',
      'weight',
      'expanded',
      'enabled',
      'uuid',
      'options',
      'blocks'
    ];

    // Loop through the menu items.
    foreach ($tree as $item_value) {

      /* @var $org_link \Drupal\Core\Menu\MenuLinkInterface */
      $org_link = $item_value['original_link'];

      /* @var $url \Drupal\Core\Url */
      $url = $item_value['url'];

      $newValue = [];

      foreach ($outputValues as $valueKey) {
        if (!empty($valueKey)) {
          $this->rhmGetElementValue($newValue, $valueKey, $org_link, $url);
        }
      }

      if (!empty($item_value['below'])) {
        $newValue['below'] = [];
        $this->rhmGetMenuItems($item_value['below'], $newValue['below']);
      }

      $items[] = $newValue;
    }
  }

  /**
   * Generate the menu element value.
   *
   * @param array $returnArray
   *   The return array we want to add this item to.
   * @param string $key
   *   The key to use in the output.
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   The link from the menu.
   * @param \Drupal\Core\Url $url
   *   The URL object of the menu item.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function rhmGetElementValue(array &$returnArray, $key, MenuLinkInterface $link, Url $url) {
    $external = $url->isExternal();
    $routed   = $url->isRouted();
    $existing = TRUE;
    $value    = NULL;

    // Check if the url is a <nolink> and do not do anything for some keys.
    $itemsToRemoveWhenNoLink = [
      'uri',
      'alias',
      'absolute',
      'relative',
    ];
    if (!$external && $routed && $url->getRouteName() === '<nolink>' && in_array($key, $itemsToRemoveWhenNoLink)) {
      return;
    }

    if ($external || !$routed) {
      $uri = $url->getUri();
    }
    else {
      try {
        $uri = $url->getInternalPath();
      }
      catch (\UnexpectedValueException $e) {
        $uri      = $relative = Url::fromUri($url->getUri())
          ->toString();
        $existing = FALSE;
      }
    }

    switch ($key) {
      case 'key':
        $value = $link->getDerivativeId();
        if (empty($value)) {
          $value = $link->getBaseId();
        }
        break;

      case 'title':
        $value = $link->getTitle();
        break;

      case 'description':
        $value = $link->getDescription();
        break;

      case 'uri':
        $value = $uri;
        break;

      case 'alias':
        if ($routed) {
          $value = \Drupal::service('path_alias.manager')->getAliasByPath("/" . $uri);
        }
        break;

      case 'external':
        $value = $external;
        break;

      case 'absolute':
        $base_url = '';

        if ($external) {
          $value = $uri;
        }
        elseif (!$routed) {
          if (empty($base_url)) {
            $url->setAbsolute();
          }

          $value = $url->toString(TRUE)->getGeneratedUrl();

          if (!empty($base_url)) {
            $value = $base_url . $value;
          }
        }
        else {
          $options = [];
          if (empty($base_url)) {
            $options = ['absolute' => TRUE];
          }

          $value = Url::fromUri('internal:/' . $uri, $options)
            ->toString(TRUE)
            ->getGeneratedUrl();

          if (!empty($base_url)) {
            $value = $base_url . $value;
          }
        }
        break;

      case 'relative':
        if (!$external) {
          $value = Url::fromUri('internal:/' . $uri, ['absolute' => FALSE])
            ->toString(TRUE)
            ->getGeneratedUrl();
        }

        if (!$routed) {
          $url->setAbsolute(FALSE);
          $value = $url->toString(TRUE)
            ->getGeneratedUrl();
        }

        if (!$existing) {
          $value = Url::fromUri($url->getUri())
            ->toString();
        }
        break;

      case 'existing':
        $value = $existing;
        break;

      case 'weight':
        $value = $link->getWeight();
        break;

      case 'expanded':
        $value = $link->isExpanded();
        break;

      case 'enabled':
        $value = $link->isEnabled();
        break;

      case 'options':
        $value = $link->getOptions();
        break;

      case 'blocks':
        $value = [];
        if ($link instanceof \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent) {
          $uuid   = $link->getDerivativeId();
          $entity = \Drupal::service('entity.repository')
            ->loadEntityByUuid('menu_link_content', $uuid);

          $blocks = $entity->field_menu_blocks->getValue();
          if ($blocks) {
            foreach ($blocks as $v) {

              // Level 1
              $blockParagraph = Paragraph::load($v['target_id']);
              $this->rhmUnti->translate($blockParagraph, $this->language);

              $linkValue = $blockParagraph->field_link->getValue();
              $link      = [];
              if ($linkValue) {
                $url  = Url::fromUri($linkValue[0]['uri'])->toString(TRUE)->getGeneratedUrl();
                $link = ['title' => $linkValue[0]['title'], 'url' => $url, 'isExternal' => UrlHelper::isExternal($url)];
              }

              // Level 2
              $blockValue     = [];
              $block          = $blockParagraph->field_menu_block->getValue();
              if ($block) {
                foreach ($block as $s) {

                  $paragraph     = Paragraph::load($s['target_id']);
                  $this->rhmUnti->translate($paragraph, $this->language);

                  $subBlockLinks = $paragraph->field_links->getValue();
                  $links         = [];
                  if ($subBlockLinks) {
                    foreach ($subBlockLinks as $subLink) {
                      $url     = Url::fromUri($subLink['uri'])->toString(TRUE)->getGeneratedUrl();
                      $links[] = ['title' => $subLink['title'], 'url' => $url, 'isExternal' => UrlHelper::isExternal($url)];
                    }
                  }

                  // Level 2 result
                  $blockValue[] = [
                    'title' => ( $paragraph->field_title->value ) ? $paragraph->field_title->value : '',
                    'description' => ( $paragraph->field_description->value ) ? $paragraph->field_description->value : '',
                    'links' => $links
                  ];
                }
              }

              // Final result
              $value[] = [
                'title' => ($blockParagraph->field_title->value) ? $blockParagraph->field_title->value : '',
                'block' => $blockValue,
                'link' => $link
              ];
            }
          }
        }
        break;
    }

    $addFragmentElements = [
      'alias',
      'absolute',
      'relative',
    ];
    if (in_array($key, $addFragmentElements)) {
      $this->rhmAddFragment($value, $link);
    }

    $returnArray[$key] = $value;
  }

  /**
   * Add the fragment to the value if neccesary.
   *
   * @param string $value
   *   The value to add the fragment to. Passed by reference.
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   The link from the menu.
   */
  private function rhmAddFragment(&$value, $link) {
    $options = $link->getOptions();
    if (!empty($options) && isset($options['fragment'])) {
      $value .= '#' . $options['fragment'];
    }
  }
}
