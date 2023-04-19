<?php

namespace Drupal\rhm_store\Plugin\custom;

use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\file\Entity\File;
use Drupal\profile\Entity\Profile;
use Drupal\rhm_rest\RhmRest;
use Drupal\rhm_rest\RhmRestUtil;

class RhmStoreController {

  public function productDisplayData($productDisplay) {

    $data    = [];
    $rhmRest = new RhmRest();
    $rhmUnit = new RhmRestUtil();

    /** @var \Drupal\commerce_product\Entity\Product $product */
    $productArray['title'] = $productDisplay->title->value;
    $productArray['body']  = $productDisplay->body->getValue();
    $productArray['media'] = $rhmRest->loadMedia($productDisplay->field_media_image, 'large');
    $productArray['path']  = $productDisplay->toUrl()->toString();
    if($productDisplay->field_product_type) {
      $productArray['displayType'] = $rhmUnit->loadTerm($productDisplay->field_product_type->entity->tid->value);
    }
    $product               = $productDisplay->field_product->entity;

    if ($product) {
      $productArray['id']         = $product->id();
      $productArray['type']       = $product->type[0]->target_id;
      $productArray['variations'] = $this->productVariations($product->getVariations());
      $data                       = $productArray;
    }

    return $data;
  }

  public function productVariations($variations) {

    $data    = [];
    $rhmRest = new RhmRest();

    foreach ($variations as $key => $variation) {
      $media          = [];
      $attributesData = [];
      $stockManager = \Drupal::service('commerce_stock.service_manager');
      $attributes     = $variation->getAttributeFieldNames();
      $price          = $variation->getPrice();
      $isColour = false;

      foreach ($attributes as $attribute) {
        $attributeValue             = $variation->getAttributeValue($attribute);
        $attributesData[$attribute] = [
          'name' => $attributeValue->name[0]->value,
          'id' => $attributeValue->attribute_value_id[0]->value
        ];

        if($attribute === 'attribute_colour') {
          $isColour = true;
        }
      }

      foreach ($variation->field_media_images as $image) {
        $media[] = $rhmRest->loadMedia($image);
      }

      $stock = intval($stockManager->getStockLevel($variation));
      $alwaysInStock = $variation->commerce_stock_always_in_stock->value;

      $data[$key] = [
        'id' => $variation->id(),
        'name' => $variation->title->value,
        'price_string' => $price->__toString(),
        'price' => number_format((float) $price->getNumber(), 2, '.', ','),
        'currency' => $price->getCurrencyCode(),
        'attributes' => $attributesData,
        'stock' => (int) ($alwaysInStock) ? '999' : $stock,
        'always_in_stock' => $alwaysInStock,
        'images' => $media,
      ];

      if($isColour) {
        $data[$key]['colour'] = $variation->field_colour->getValue();
      }
    }

    return $data;
  }

  public function orderLineItems($order) {

    $lineItems = [];
    $rhmRest   = new RhmRest();

    foreach ($order->getItems() as $orderItem) {
      $media           = [];
      $purchasedEntity = $orderItem->getPurchasedEntity();
      if ($purchasedEntity->field_media_images) {
        foreach ($purchasedEntity->field_media_images as $image) {
          $media[] = $rhmRest->loadMedia($image);
        }
      }

      $quantity      = (int) $orderItem->quantity->value;
      $unitPrice     = (float) $orderItem->unit_price->number;
      $subTotalPrice = $unitPrice * $quantity;
      $lineItems[]   = [
        'id' => $orderItem->id(),
        'media' => $media,
        'title' => $orderItem->title->value,
        'quantity' => (int) $orderItem->quantity->value,
        'price' => number_format((float) $unitPrice, 2, '.', ','),
        'subTotal' => number_format((float) $subTotalPrice, 2, '.', ','),
        'currency' => $orderItem->unit_price->currency_code,
      ];
    }
    return $lineItems;
  }

  public function orderShippingState($order) {

    $state = [];

    $shipments = $order->get('shipments')->getValue();
    if ($shipments) {

      $dateLabel  = '';
      $stateLabel = '';
      $shipment   = Shipment::load($shipments[0]['target_id']);

      switch ($shipment->getState()->value):
        case 'draft':
          $stateLabel = t('Ordered');
          $dateLabel  = t('Unknow');
          break;
        case 'ready':
          $stateLabel = t('Processing');
          $dateLabel  = t('3-5 Working Days');
          break;
        case 'canceled':
          $stateLabel = t('Canceled');
          break;
        case 'shipped':
          $stateLabel = t('Delivered');
          $dateLabel  = $shipment->get('field_shipped_date')->value;
          break;
      endswitch;

      $state = [
        'state' => $stateLabel,
        'date' => $dateLabel,
        'tracking' => $shipment->getTrackingCode()
      ];
    }

    return $state;
  }

  public function orderShippingRate($order) {

    $rate = 0;

    foreach ($order->collectAdjustments() as $adjustment) {
      if ($adjustment->getType() == 'shipping') {
        $isFree = FALSE;
        $fee    = $adjustment->getAmount()->getNumber();
        if ($fee == '0.00') {
          $isFree = TRUE;
          $fee    = t('Free Shipping');
        }
        else {
          $fee = number_format((float) $fee, 2, '.', ',');
        }
        $rate = [
          'isFree' => $isFree,
          'amount' => $fee,
          'currency' => $adjustment->getAmount()->getCurrencyCode()
        ];
      }
    }

    return $rate;
  }

  public function orderShippingProfile($order) {

    $profile = [];

    $shipments = $order->get('shipments')->getValue();
    if ($shipments) {
      $shipment = Shipment::load($shipments[0]['target_id']);
      if ($shipment->getShippingProfile()) {
        $profileEntity = Profile::load($shipment->getShippingProfile()->id());
        if ($profileEntity) {
          $profile = $profileEntity->address[0]->getValue();
        }
      }

    }

    return $profile;
  }

  public function orderDiscount($order) {

    $discount = [];

    foreach ($order->collectAdjustments() as $adjustment) {
      if ($adjustment->getType() == 'promotion') {
        $discount = [
          'amount' => number_format((float) $adjustment->getAmount()
            ->getNumber(), 2, '.', ','),
          'currency' => $adjustment->getAmount()->getCurrencyCode()
        ];
      }
    }

    return $discount;
  }
}
