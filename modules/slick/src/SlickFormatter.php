<?php

/**
 * @file
 * Contains \Drupal\slick\SlickFormatter.
 */

namespace Drupal\slick;

use Drupal\slick\Entity\Slick;
use Drupal\slick\SlickImageBase;

/**
 * Implements SlickFormatterInterface.
 */
class SlickFormatter extends SlickImageBase implements SlickFormatterInterface {

  /**
   * {@inheritdoc}
   */
  public function buildSettings($items, $langcode, $settings = []) {
    $field          = $items->getFieldDefinition();
    $entity         = $items->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $entity_id      = $entity->id();
    $field_name     = $field->getName();
    $field_clean    = str_replace("field_", '', $field_name);
    $target_type    = $field->getFieldStorageDefinition()->getSetting('target_type');
    $optionset      = $settings['optionset'] ?: 'default';
    $unique         = empty($settings['skin']) ? $optionset : $optionset . '-' . $settings['skin'];
    $view_mode      = empty($settings['current_view_mode']) ? '_custom' : $settings['current_view_mode'];
    $id             = Slick::getHtmlId("slick-{$entity_type_id}-{$entity_id}-{$field_clean}-{$unique}");
    $internal_path  = $absolute_path = $url = NULL;

    // Deals with UndefinedLinkTemplateException such as paragraphs type.
    // @see #2596385, or fetch the host entity.
    if (!$entity->isNew() && method_exists($entity, 'hasLinkTemplate')) {
      if ($entity->hasLinkTemplate('canonical')) {
        $url = $entity->urlInfo();
        $internal_path = $url->getInternalPath();
        $absolute_path = $url->setAbsolute()->toString();
      }
    }

    $settings += [
      'absolute_path'  => $absolute_path,
      'bundle'         => $entity->bundle(),
      'caption'        => empty($settings['caption']) ? [] : array_filter($settings['caption']),
      'overridables'   => empty($settings['overridables']) ? [] : array_filter($settings['overridables']),
      'count'          => $items->count(),
      'entity_id'      => $entity_id,
      'entity_type_id' => $entity_type_id,
      'field_type'     => $field->getType(),
      'field_name'     => $field_name,
      'id'             => $id,
      'internal_path'  => $internal_path,
      'nav'            => !empty($settings['optionset_thumbnail']) && isset($items[1]),
      'lightbox'       => !empty($settings['media_switch']) && strpos($settings['media_switch'], 'box') !== FALSE,
      'target_type'    => $target_type,
      'cache_metadata' => ['keys' => [$id, $view_mode, $optionset]],
    ];

    $build['optionset'] = $this->manager()->load($optionset);
    $build['settings']  = $settings;

    if (empty($settings['responsive_image_style_id'])) {
      $build['settings']['lazy'] = $build['optionset']->getSetting('lazyLoad');
    }
    unset($entity, $field);
    return $build;
  }

}
