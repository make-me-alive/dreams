<?php

/**
 * @file
 * Hooks and preprocess functions for the Slick module.
 */

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Template\Attribute;
use Drupal\image\Entity\ImageStyle;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Serialization\Json;
use Drupal\slick\Entity\Slick;

/**
 * Prepares variables for slick.html.twig templates.
 */
function template_preprocess_slick(&$variables) {
  $element   = $variables['element'];
  $defaults  = Slick::htmlSettings();
  $settings  = isset($element['#settings']) ? array_merge($defaults, $element['#settings']) : $defaults;
  $customs   = isset($element['#options']) ? $element['#options'] : [];
  $optionset = isset($element['#optionset']) ? $element['#optionset'] : Slick::load($settings['optionset']);
  $js        = $customs ? array_merge($optionset->getSettings(), $customs) : $optionset->getSettings();
  $display   = $variables['display'] = $settings['display'];

  // Prepare attributes.
  $attributes = [];
  $content_attributes = new Attribute();
  if (isset($settings['attributes']) && is_array($settings['attributes'])) {
    $attributes = NestedArray::mergeDeep($attributes, $settings['attributes']);
  }

  if (!isset($attributes['id'])) {
    $id = Slick::getHtmlId('slick', $settings['id']);
    $attributes['id'] = $display == 'thumbnail' ? $id . '-thumbnail' : $id;
  }

  // @see SlickManager::buildGrid(), and this should make sense.
  $settings['count']   = isset($settings['count']) ? $settings['count'] : count($element['#items']);
  $settings['unslick'] = $settings['unslick'] || $settings['count'] == 1;

  foreach (['asNavFor', 'nextArrow', 'prevArrow', 'downArrowTarget', 'downArrowOffset', 'slidesToShow'] as $key) {
    $settings[$key] = isset($js[$key]) ? $js[$key] : FALSE;
  }

  // Prevents broken slick when only one item given, or an enforced unslick.
  if (!$settings['unslick']) {
    $content_attributes->setAttribute('id', $attributes['id'] . '-slider');

    $settings['arrow']['attributes'] = new Attribute();
    if ($display == 'main' && $js['downArrow'] && $js['downArrowTarget']) {
      $settings['downArrow']['attributes'] = new Attribute();
    }

    if ($js['rtl']) {
      $content_attributes->setAttribute('dir', 'rtl');
    }

    // focusOnSelect won't work with empty slide value, so add proper selector.
    $js['slide'] = empty($js['slide']) && $js['focusOnSelect'] ? '.slick__slide' : $js['slide'];

    // Add the configuration as JSON object into the slick container.
    if ($json = $optionset->removeDefaultValues($js)) {
      $content_attributes->setAttribute('data-slick', Json::encode($json));
    }
  }

  // Pass settings and attributes to twig.
  $variables['settings'] = $settings;
  $variables['attributes'] = $attributes;
  $variables['content_attributes'] = $content_attributes;

  // Process individual item.
  $variables['items'] = [];
  foreach ($element['#items'] as $delta => $item) {
    $settings['current_item'] = $display;

    $theme = $settings['vanilla'] ? 'slick_vanilla' : ($display == 'thumbnail' ? 'slick_thumbnail' : 'slick_slide');
    $slide = _slick_build_slide($item, $delta, $settings, $theme);
    $variables['items'][$delta] = $slide;
    unset($slide);
  }
}

/**
 * Re-uses individual slide building for grid, slide, thumbnail, vanilla.
 */
function _slick_build_slide($item, $delta = 0, $settings = array(), $theme = 'slick_slide') {
  $settings = isset($item['settings']) ? array_merge($settings, $item['settings']) : $settings;
  unset($item['settings']);
  return [
    '#theme'    => $theme,
    '#item'     => $item,
    '#delta'    => $delta,
    '#settings' => $settings,
  ];
}

/**
 * Prepares variables for slick-wrapper.html.twig templates.
 */
function template_preprocess_slick_wrapper(&$variables) {
  foreach (['attributes', 'items', 'settings'] as $key) {
    $variables[$key] = isset($variables['element']["#$key"]) ? $variables['element']["#$key"] : [];
  }
}

/**
 * Prepares common variables for slick item templates.
 */
function _slick_preprocess_slick_item(&$variables) {
  foreach (['attributes', 'delta', 'item', 'settings'] as $key) {
    $variables[$key] = isset($variables['element']["#$key"]) ? $variables['element']["#$key"] : [];
  }
}

/**
 * Prepares variables for slick-vanilla.html.twig templates.
 */
function template_preprocess_slick_vanilla(&$variables) {
  _slick_preprocess_slick_item($variables);
}

/**
 * Prepares variables for slick-thumbnail.html.twig templates.
 */
function template_preprocess_slick_thumbnail(&$variables) {
  _slick_preprocess_slick_item($variables);
}

/**
 * Prepares variables for slick-slide.html.twig templates.
 */
function template_preprocess_slick_slide(&$variables) {
  _slick_preprocess_slick_item($variables);

  // All slide types -- main, thumbnail, grid, overlay -- may have captions.
  foreach (['alt', 'data', 'link', 'overlay', 'title'] as $key) {
    $variables['item']['caption'][$key] = isset($variables['item']['caption'][$key]) ? $variables['item']['caption'][$key] : [];
  }

  $item     = &$variables['item'];
  $settings = &$variables['settings'];

  // split: Split image from captions if we do have captions, and main image.
  // fullwidth: If full skins, add wrappers to hold caption and overlay.
  // detroy: Remove .slide__content if it is an enforced unslick grid.
  // wrapper: Don't add divities for a single item to have clean markups.
  $item['caption']       = array_filter($item['caption']);
  $settings['split']     = !empty($item) && $settings['caption'];
  $settings['data']      = !empty($item['caption']['alt']) || !empty($item['caption']['title']) || !empty($item['caption']['data']);
  $settings['fullwidth'] = !empty($settings['skin']) && strpos($settings['skin'], 'full') !== FALSE;
  $settings['detroy']    = $settings['current_item'] == 'main' && !empty($settings['grid']) && !empty($settings['unslick']);
  $settings['wrapper']   = $settings['count'] > 1 && $settings['current_item'] != 'grid';
}

/**
 * Prepares variables for slick-grid.html.twig templates.
 */
function template_preprocess_slick_grid(&$variables) {
  $settings = $variables['settings'] = $variables['element']['#settings'];

  $variables['items'] = [];
  foreach ($variables['element']['#items'] as $delta => $item) {
    $settings['current_item'] = 'grid';

    $slide['slide'] = _slick_build_slide($item, $delta, $settings);
    $slide['attributes'] = new Attribute();
    $variables['items'][$delta] = $slide;
    unset($slide);
  }
}

/**
 * Prepares variables for slick-media.html.twig templates.
 */
function template_preprocess_slick_media(&$variables) {
  _slick_preprocess_slick_item($variables);

  // Configure attributes for containing elements.
  $content_attributes = [];
  $settings           = &$variables['settings'];
  $settings['player'] = $settings['ratio_clean'] = FALSE;

  // Pure CSS responsive iframes/images based on aspect ratio.
  if (!empty($settings['ratio']) && empty($settings['responsive_image_style'])) {
    $settings['ratio_clean'] = str_replace(':', '', 'media--ratio--' . $settings['ratio']);

    if (!empty($settings['height']) && $settings['ratio'] == 'fluid') {
      $variables['attributes']['data-ratio'] = round((($settings['height'] / $settings['width']) * 100), 2);
    }
  }

  // The media audio/video files can be iframed/lightboxed/switched.
  if (in_array($settings['type'], ['video', 'audio']) && !empty($settings['url'])) {
    // item  : If iframe switch disabled, fallback to just iframe, remove image.
    // player: If no colorbox/photobox, it is an image to iframe switcher.
    // data- : Gets consistent with colorbox to share JS manipulation.
    $variables['item']                = empty($settings['media_switch']) ? [] : $variables['item'];
    $settings['player']               = !$settings['lightbox'];
    $content_attributes['data-media'] = Json::encode(['type' => $settings['type'], 'scheme' => $settings['scheme']]);
    $content_attributes['data-lazy']  = $settings['url'];
    $content_attributes['src']        = empty($settings['iframe_lazy']) ? $settings['url'] : 'about:blank';
  }

  $variables['content_attributes'] = new Attribute($content_attributes);
}

/**
 * Prepares variables for slick-image.html.twig template.
 */
function template_preprocess_slick_image(array &$variables) {
  _slick_preprocess_slick_item($variables);

  $item       = $variables['item'];
  $settings   = &$variables['settings'];
  $attributes = $variables['attributes'];
  $uri        = ($entity = $item->entity) && empty($item->uri) ? $entity->getFileUri() : $item->uri;

  $variables['noscript'] = '';
  $variables['url']      = isset($variables['element']['#url']) ? $variables['element']['#url'] : '';
  $attributes['class'][] = 'media__image';

  // Do not output an empty 'title' attribute.
  if (Unicode::strlen($item->title) != 0) {
    $variables['image']['#title'] = $item->title;
  }

  // Check whether we have responsive image, or plain one.
   $variables['image']['#uri'] = $uri;
  if (!empty($settings['responsive_image_style_id'])) {
    $variables['image']['#type'] = 'responsive_image';
    $variables['image']['#responsive_image_style_id'] = $settings['responsive_image_style_id'];
  }
  else {
    $variables['image']['#theme'] = 'image';
    if (!empty($settings['image_style'])) {
      $style = ImageStyle::load($settings['image_style']);
      $settings['image_url'] = $style->buildUrl($uri);
      if (!isset($settings['dimensions'])) {
        $dimensions = array(
          'width' => isset($item->width) ? $item->width : '',
          'height' => isset($item->height) ? $item->height : '',
        );

        $style->transformDimensions($dimensions, $uri);
        $variables['image']['#width'] = $dimensions['width'];
        $variables['image']['#height'] = $dimensions['height'];
      }
    }
    else {
      $settings['image_url'] = $item->entity->url();
    }

    foreach (['width', 'height', 'alt'] as $key) {
      $variables['image']["#$key"] = isset($variables['image']["#$key"]) ? $variables['image']["#$key"] : $item->$key;
    }

    // Provide lazyloaded image with noscript for the first slide only.
    if ($settings['count'] > 1 && $settings['lazy']) {
      $variables['noscript']      = $settings['delta'] == 0 ? $variables['image'] : '';
      $attributes['class'][]      = 'lazy';
      $variables['image']['#uri'] = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

      $lazy_attribute = empty($settings['lazy_attribute']) ? 'lazy' : $settings['lazy_attribute'];
      $attributes['data-' . $lazy_attribute] = $settings['image_url'];
    }

    if (!empty($settings['ratio'])) {
      if (empty($variables['url'])) {
        $attributes['class'][] = 'media__ratio';
      }
      else {
        $settings['url']['attributes']['class'][] = 'media__ratio';
      }
    }
  }

  $variables['image']['#attributes'] = $attributes;

  // URL can be entity or lightbox URL different from the content image URL.
  if (!empty($variables['url'])) {
    $settings['url']['attributes'] = isset($settings['url']['attributes']) ? new Attribute($settings['url']['attributes']) : [];
  }
}
