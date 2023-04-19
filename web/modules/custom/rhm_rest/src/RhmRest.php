<?php

namespace Drupal\rhm_rest;

use Drupal\commerce_product\Entity\Product;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;
use Drupal\image\Entity\ImageStyle;
use Drupal\rhm_store\Plugin\custom\RhmStoreController;

class RhmRest {

  private $paragraphModels = [];
  private $contentModels   = [];
  private $base_url = '';
  private $rhmUtil;

  public function __construct() {

    $fileSystem         = \Drupal::service('file_system');
    $modulePath         = drupal_get_path('module', 'rhm_rest');
    $paragraphModelsRaw = $fileSystem->scanDirectory($modulePath . '/models/paragraphs', '/.*/');
    foreach ($paragraphModelsRaw as $model) {
      $this->paragraphModels[$model->name] = $modulePath . '/models/paragraphs/' . $model->filename;
    }
    $contentModelsRaw = $fileSystem->scanDirectory($modulePath . '/models/content', '/.*/');
    foreach ($contentModelsRaw as $model) {
      $this->contentModels[$model->name] = $modulePath . '/models/content/' . $model->filename;
    }
    $this->base_url = Request::createFromGlobals()->getSchemeAndHttpHost();
    $this->rhmUtil = \Drupal::service('rhm_rest_util');
  }

  /**
   * Recursively load all fields in an easily digestible format
   * @param $type
   * @param $entity
   * @param $lang
   * @return array
   */
  public function loadFields($type, $entity, $lang = 'en') {

    $this->rhmUtil->translate($entity, $lang);

    $data           = [];
    $data['bundle'] = $entity->bundle();
    $data['id']     = $entity->id();
    $data['lang']   = $lang;

    $cacheLastInSeconds = 86400;
    $drupalCacheTags = ['node:' . $entity->id(), 'http_response'];

    /** @var \Drupal\Core\Cache\CacheBackendInterface $drupalCache */
    $drupalCache = \Drupal::cache();
    $drupalCache->
    //Build Cache ID
    $cid = 'rhm.node.' . $entity->id() . '.' . $lang;
    $cachedDataObj = $drupalCache->get($cid);
    // Todo: handle this for dev
//    if (!empty($cachedDataObj)) {
//      $result = $cachedDataObj->data;
//      return $result;
//    }

    // METATAGS
    $metatag_manager = \Drupal::service('metatag.manager');
    $metatag_token   = \Drupal::service('metatag.token');
    $tags            = $metatag_manager->tagsFromEntityWithDefaults($entity);
    $data['meta']    = [];
    foreach ($tags as $key => $value) {
      $data['meta'][$key] = $metatag_token->replace($value, ['node' => $entity]);
    }

    // BREADCRUMB
    // todo: this isn't working on staging...
//    $routeName = $entity->toUrl()->getRouteName();
//    $routeParameters = $entity->toUrl()->getRouteParameters();
//    $route = \Drupal::service('router.route_provider')->getRouteByName($routeName);
//    $routeMatch = new RouteMatch($routeName, $route, $routeParameters);
//
//    /** @var \Drupal\Core\Breadcrumb\Breadcrumb $breadcrumb */
//    $breadcrumb = \Drupal::service('rhm_breadcrumb')->build($routeMatch);
//    $data['breadcrumb'] = [];
//
//    foreach($breadcrumb->getLinks() as $link){
//      /** @var \Drupal\Core\Link $link */
//      $data['breadcrumb'][] = [
//        'link' => $link->getUrl()->toString(),
//        'text' => $link->getText()
//      ];
//    }

    // Append current page
    $data['breadcrumb'][] = [
      'text' => $entity->title->value,
      'link' => NULL
    ];


    $bundleModel = $this->contentModels[$entity->bundle()];
    if (!empty($bundleModel)) {
      $bundleYaml = Yaml::parse(file_get_contents($bundleModel));
      foreach ($bundleYaml as $fieldKey => $fieldValue) {
        $pointer         = $this->loadParts($entity, $fieldValue, $fieldKey, $lang);
        $data[$fieldKey] = $pointer;
      }
    }
    \Drupal::moduleHandler()->alter('rest_node', $data);
    $drupalCache->set($cid, $data, \Drupal::time()->getRequestTime() + ($cacheLastInSeconds), $drupalCacheTags);

    return $data;
  }


  /**
   * Loads the parts of a field
   * @param $entity
   * @param $fieldValue
   * @param $fieldKey
   * @param $lang
   * @return array|string|null
   */
  public function loadParts($entity, $fieldValue, $fieldKey, $lang) {

    // Paragraphs
    if ($fieldValue === 'getParagraph') {
      $paragraphOutput = [];

      foreach ($entity->{$fieldKey} as $paragraph) {
        $paragraphEntity = $paragraph->entity;
        $paragraphBundle = $paragraphEntity->bundle();

        if ($paragraphBundle === 'from_library') {
          $paragraphEntity = $paragraph->entity->get('field_reusable_paragraph')->entity->get('paragraphs')->entity;
          $paragraphBundle = $paragraphEntity->bundle();
        }

        $paragraphModel = $this->paragraphModels[$paragraphBundle];

        if (!empty($paragraphModel)) {

          $paragraphArray = ['bundle' => $paragraphBundle, 'paragraph' => []];

          $yaml = Yaml::parse(file_get_contents($paragraphModel));

          if (!empty($yaml)) {
            foreach ($yaml as $fieldKey => $fieldValue) {
              $pointer                                = RhmRest::loadParts($paragraphEntity, $fieldValue, $fieldKey, $lang);
              $paragraphArray['lang']                 = $lang;
              $paragraphArray['paragraph'][$fieldKey] = $pointer;
            }
          }

          \Drupal::moduleHandler()->alter('rest_paragraph', $paragraphArray);
          $paragraphOutput[] = $paragraphArray;
        }
      }

      return $paragraphOutput;

    }

    elseif($fieldKey === 'body') {
      return $entity->{$fieldKey}->getValue();
    }

    elseif($fieldKey === 'created') {
      $created = $entity->getCreatedTime();
      $created = \Drupal::service('date.formatter')
        ->format($created, 'article');
      return $created;
    }

    elseif($fieldKey === 'author') {
      $uid = $entity->getOwnerId();
      $user = User::load($uid);
      if (!is_null($user)) {
        $username = $user->getDisplayName();
        return $username;
      }
      else {
        return null;
      }
    }

    // References
    elseif (is_array($fieldValue) && !empty($fieldValue['getReference'])) {
      $reference = $entity->{$fieldKey};
      $refParts  = $fieldValue['getReference'];
      $output    = [];

      foreach ($reference as $i => $ref) {
        $output[$i] = [];

        foreach ($refParts as $refPartKey => $refPartValue) {
          $refEntity = $ref->entity;
          if(!empty($refEntity)) {
            $this->rhmUtil->translate($refEntity, $lang);
            $output[$i][$refPartKey] = $this->loadParts($refEntity, $refPartValue, $refPartKey, $lang);
          }
        }
      }

      $pointer = $output;
      return $pointer;

    }

    else {
      $loadParts = explode('.', $fieldValue);
      $this->rhmUtil->translate($entity, $lang);
      $pointer = $entity->{$fieldKey};

      $isArray = FALSE;
      if (is_object($pointer) && (
          get_class($pointer) === 'Drupal\Core\Field\EntityReferenceFieldItemList' ||
          get_class($pointer) === 'Drupal\Core\Field\FieldItemList') ||
          get_class($pointer) === 'Drupal\path\Plugin\Field\FieldType\PathFieldItemList') {
        $isArray = TRUE;
      }

      if (!is_array($loadParts)) {
        return NULL;
      }
      foreach ($loadParts as $loadPart) {

        // Links
        if ($loadPart === 'getLink') {

          if ($isArray) {
            $valueArray = [];
            foreach ($pointer as $item) {
              $url          = Url::fromUri($item->uri)
                ->toString(TRUE)
                ->getGeneratedUrl();
              $valueArray[] =
                [
                  'title' => $item->title,
                  'url' => $url
                ];
            }
            $pointer = $valueArray;
          }
          else {
            $url = Url::fromUri($pointer)
              ->toString(TRUE)
              ->getGeneratedUrl();
            $pointer = [
              'title' => $pointer->title,
              'url' => $url
            ];
          }
        }
        elseif (strpos($loadPart, 'getTruncate') !== FALSE) {
          $truncateParts = explode('|', $loadPart);
          $chars         = 100;
          if (!empty($truncateParts[1])) {
            $chars = $truncateParts[1];
          }
          $pointer = Unicode::truncate(strip_tags($pointer->value), $chars, TRUE);
        }
        elseif (strpos($loadPart, 'getDate') !== FALSE) {
          $dateParts = explode('|', $loadPart);
          $format    = 'article';
          if (!empty($dateParts[1])) {
            $format = $dateParts[1];
          }
          $pointer = \Drupal::service('date.formatter')
            ->format($pointer->value, $format);
        }
        elseif ($loadPart === 'getVideo') {
          /** @var \Drupal\Core\Field\FieldItemList $video */
          if (!$pointer->isEmpty()) {
            $video   = $pointer->entity->field_media_oembed_video->getValue();
            $pointer = $video[0]['value'];
          }
          else {
            $pointer = '';
          }
        }
        elseif ($loadPart === 'getFile') {
          $fileUri = $pointer->entity->getFileUri();
          $pointer = [
            'url' => file_create_url($fileUri),
          ];

        }
        elseif (strpos($loadPart, 'getMedia') !== FALSE) {

          $mediaParts = explode('|', $loadPart);
          $imageStyle = NULL;
          if (!empty($mediaParts[1])) {
            $imageStyle = $mediaParts[1];
          }

          if ($isArray) {
            $mediaArray = [];
            foreach ($pointer as $media) {
              $mediaArray[] = $this->loadMedia($media, $imageStyle);
            }
            $pointer = $mediaArray;
          }
          else {
            $pointer = $this->loadMedia($pointer, $imageStyle);
          }

        }
        elseif ($loadPart === 'getProduct') {
          $products = [];
          $rhmStoreController = new RhmStoreController();
          foreach ($entity->{$fieldKey} as $product) {
            $pid = $product->getValue()['target_id'];
            if($pid) {
              $product = Product::load($pid);
              $this->rhmUtil->translate($product, $lang);
              $products = [
                'type' => $product->type[0]->target_id,
                'description' => $product->body->value,
                'variations' => $rhmStoreController->productVariations($product->getVariations())
              ];
            }
          }
          $pointer = $products;
        }
        else {

          if ($loadPart === 'alias') {
            $aliasArray = $pointer->getValue();
            if (empty($aliasArray[0]['alias'])) {
              $pointer = '/node/' . $entity->nid->value;
            }
            else {
              $pointer = $aliasArray[0]['alias'];
            }

          }
          else {
            if ($isArray) {
              $valueArray = [];
              foreach ($pointer as $item) {
                $valueArray[] = $item->{$loadPart};
              }
              $pointer = $valueArray;
            }
            else {
              $pointer = $pointer->{$loadPart};
            }
          }
        }
      }

      if(is_string($pointer[0])){
        $pointer[0] = Html::transformRootRelativeUrlsToAbsolute($pointer[0], $this->base_url);
      }

      return $pointer;
    }
  }

  /**
   * Load Media data to the array
   * @param $media
   * @param null $imageStyle
   * @return array
   */
  public function loadMedia($media, $imageStyle = NULL) {

    $data        = [];
    $mediaEntity = $media->entity;

    if ($mediaEntity) {

      $bundle = $mediaEntity->bundle();

      if ($bundle == 'image') {

        $fileEntity = File::load($mediaEntity->field_media_image->target_id);
        $uri        = $fileEntity->getFileUri();

        if ($imageStyle) {
          $url = ImageStyle::load($imageStyle)->buildUrl($uri);
        }
        else {
          $url = file_create_url($uri);
        }

        $urlWebp = preg_replace('/\.(png|jpg|jpeg)(\\?.*?)?(,| |$)/i', '.\\1.webp\\2\\3', $url);

        if(strlen(file_get_contents($urlWebp)) > 1){
          $urls[] = [
            'type' => 'image/webp',
            'url' => $urlWebp
          ];
        }

        $urls[] = [
          'type' => $fileEntity->getMimeType(),
          'url' => $url
        ];

        $data['alt']   = $mediaEntity->field_media_image->alt;
        $data['title'] = $mediaEntity->field_media_image->title;
        $data['url']   = $url;
        $data['urls'] = $urls;

      }

      elseif ($bundle == 'file') {
        $fileEntity  = File::load($mediaEntity->field_media_image->target_id);
        $uri         = $fileEntity->getFileUri();
        $data['url'] = file_create_url($uri);
      }
    }

    return $data;
  }

}
