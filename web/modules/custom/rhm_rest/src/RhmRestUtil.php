<?php

namespace Drupal\rhm_rest;

class RhmRestUtil{

  /**
   * Translates an entity to the current language
   * @param $entity
   * @param $lang
   * $param $return
   * @return mixed
   */
  public function translate(&$entity, $lang = NULL, $return = FALSE){

    if($lang === NULL){
      $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    if($entity && $entity->hasTranslation($lang)){
      $entity = $entity->getTranslation($lang);
    }

    if($return){
      return $entity;
    }
  }

  public function loadTerm($termID) {

    $term = \Drupal\taxonomy\Entity\Term::load($termID);
    $this->translate($term);

    return [
      'id' => $termID,
      'name' => $term->getName()
    ];
  }
}
