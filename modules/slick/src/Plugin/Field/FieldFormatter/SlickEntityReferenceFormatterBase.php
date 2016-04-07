<?php

/**
 * @file
 * Contains \Drupal\slick\Plugin\Field\FieldFormatter\SlickEntityReferenceFormatterBase.
 */

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\slick\SlickDefault;
use Drupal\slick\SlickFormatterInterface;
use Drupal\slick\SlickFormatterTrait;

/**
 * Base class for slick entity reference formatters.
 *
 * @see \Drupal\slick_media\Plugin\Field\FieldFormatter\SlickMediaFormatter.
 */
abstract class SlickEntityReferenceFormatterBase extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {
  use SlickFormatterTrait;

  /**
   * Constructs a SlickMediaFormatter instance.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_factory, SlickFormatterInterface $formatter) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->loggerFactory = $logger_factory;
    $this->formatter     = $formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('slick.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'color_field' => '',
      'iframe_lazy' => FALSE,
    ] + SlickDefault::fieldableSettings();
  }

  /**
   * Returns media contents.
   */
  public function buildElements($entities, $langcode, $settings = []) {
    $build     = [];
    $view_mode = $settings['view_mode'] ?: 'full';

    // $medium = $this->formatter->setDimensions($files[0]->_referringItem, $settings['image_style'], $files[0]->getFileUri());
    foreach ($entities as $delta => $entity) {
      // Protect ourselves from recursive rendering.
      static $depth = 0;
      $depth++;
      if ($depth > 20) {
        $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity @entity_type @entity_id. Aborting rendering.', array('@entity_type' => $entity->getEntityTypeId(), '@entity_id' => $entity->id()));
        return $build;
      }

      $slide = ['delta' => $delta, 'settings' => $settings];
      if ($entity->id()) {
        if ($settings['vanilla']) {
          $build['items'][$delta] = $this->manager()->getEntityTypeManager()->getViewBuilder($entity->getEntityTypeId())->view($entity, $view_mode, $langcode);
        }
        else {
          $this->buildElement($build, $entity, $langcode, $slide);
        }

        // Add the entity to cache dependencies so to clear when it is updated.
        $this->manager()->getRenderer()->addCacheableDependency($build['items'][$delta], $entity);
      }
      else {
        $this->referencedEntities = NULL;
        // This is an "auto_create" item.
        $build[$delta] = array('#markup' => $entity->label());
      }

      unset($slide);
      $depth = 0;
    }

    return $build;
  }

  /**
   * Returns slide contents.
   */
  public function buildElement(array &$build, $entity, $langcode, $slide) {
    $delta     = $slide['delta'];
    $settings  = $slide['settings'];
    $view_mode = $settings['view_mode'] ?: 'full';

    $image = [];
    $media = $this->buildMedia($entity, $langcode, $slide, $delta);

    // Main image can be separate image item from video thumbnail for highres.
    $field_image = $settings['image'];
    if ($field_image && isset($entity->$field_image)) {

      /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $file */
      $file = $entity->get($field_image);

      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item          = $file->get(0);
      $slide['item'] = $item;
      $media['uri']  = $file->referencedEntities()[0]->getFileUri();

      // Only process if the media has the expected image.
      if ($item) {
        // Array contains: alt title width height target_id and loaded TRUE.
        $translation = $entity->getTranslation($langcode)->get($field_image);
        $media = array_merge($media, $translation->getValue()[0]);
        $slide['media'] = $media;

        $image = $this->formatter->getImage($slide);
      }
    }

    // Image with responsive image, lazyLoad, and lightbox supports.
    $slide['slide'] = $image;

    // Captions if so configured.
    $this->getCaption($entity, $langcode, $settings, $slide);

    // Layouts can be builtin, or field, if so configured.
    if ($layout = $settings['layout']) {
      if (strpos($layout, 'field_') !== FALSE) {
        $settings['layout'] = $this->getFieldString($entity, $layout, $langcode);
      }
      $slide['settings']['layout'] = strip_tags($settings['layout']);
    }

    // Classes, if so configured.
    $class = $this->getFieldString($entity, $settings['class'], $langcode);
    $slide['settings']['class'] = strip_tags($class);
    $build['items'][$delta] = $slide;

    if ($settings['nav']) {
      // Thumbnail usages: asNavFor pagers, dot, arrows, photobox thumbnails.
      $slide['slide']   = empty($settings['thumbnail_style']) ? [] : $this->formatter->getThumbnail($slide);
      $slide['caption'] = $this->getFieldRenderable($entity, $settings['thumbnail_caption'], $view_mode);
      $build['thumb']['items'][$delta] = $slide;
    }
  }

  /**
   * Collects media definitions.
   */
  public function buildMedia($entity, $langcode, $slide, $delta) {
    $media = [
      'bundle'         => $entity->bundle(),
      'delta'          => $delta,
      'entity_url'     => $entity->url(),
      'id'             => $entity->id(),
      'target_bundles' => $this->getFieldSetting('handler_settings')['target_bundles'],
      'type'           => $entity->bundle(),
    ];

    return $media;
  }

  /**
   * Builds slide captions with possible multi-value fields.
   */
  public function getCaption($entity, $langcode, $settings = [], array &$slide) {
    $view_mode = $settings['view_mode'];

    // Title can be plain text, or link field.
    $field_title = $settings['title'];
    $has_title = $field_title && isset($entity->$field_title);
    if ($has_title && $title = $entity->getTranslation($langcode)->get($field_title)->getValue()) {
      if (!empty($title[0]['value']) && !isset($title[0]['uri'])) {
        // Prevents HTML-filter-enabled text from having bad markups (h2 > p).
        $slide['caption']['title']['#markup'] = Xss::filterAdmin($title[0]['value']);
      }
      elseif (isset($title[0]['uri']) && !empty($title[0]['title'])) {
        $slide['caption']['title'] = $this->getFieldRenderable($entity, $field_title, $view_mode)[0];
      }
    }

    // Other caption fields, if so configured.
    if (!empty($settings['caption'])) {
      $caption_items = [];
      foreach ($settings['caption'] as $i => $field_caption) {
        if (!isset($entity->$field_caption)) {
          continue;
        }
        $caption_items[$i] = $this->getFieldRenderable($entity, $field_caption, $view_mode);
      }
      if ($caption_items) {
        $slide['caption']['data'] = $caption_items;
      }
    }

    // Link, if so configured.
    $field_link = $settings['link'];
    if ($field_link && isset($entity->$field_link)) {
      $links = $this->getFieldRenderable($entity, $field_link, $view_mode);
      // Only simplify markups for known formatters registered by link.module.
      if ($links && in_array($links['#formatter'], ['link'])) {
        $links = [];
        foreach ($entity->$field_link as $i => $link) {
          $links[$i] = $link->view($view_mode);
        }
      }
      $slide['caption']['link'] = $links;
    }

    $slide['caption']['overlay'] = empty($settings['overlay']) ? [] : $this->getOverlay($entity, $langcode, $settings, $slide);
  }

  /**
   * Builds slide overlay placed within the caption.
   */
  public function getOverlay($entity, $langcode, $settings = [], array &$slide) {
    return [];
  }

  /**
   * Returns the string value of the fields: link or text.
   */
  public function getFieldString($entity, $field_name = '', $langcode, $formatted = FALSE) {
    $value = '';
    if ($field_name && isset($entity->$field_name)) {
      $values = $entity->getTranslation($langcode)->get($field_name)->getValue();
      if (!empty($values[0]['value'])) {
        $value = $values[0]['value'];
      }
      elseif (isset($values[0]['uri']) && !empty($values[0]['title'])) {
        $value = $values[0]['uri'];
      }
    }
    return $value;
  }

  /**
   * Returns the formatted renderable array of the field.
   */
  public function getFieldRenderable($entity, $field_name = '', $view_mode) {
    $has_field = $field_name && isset($entity->$field_name) && !empty($entity->$field_name->view($view_mode)[0]);
    return $has_field ? $entity->$field_name->view($view_mode) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element  = [];
    $admin    = $this->admin();
    $bundles  = $this->getFieldSetting('handler_settings')['target_bundles'];
    $strings  = $admin->getFieldOptions($bundles, ['text', 'string', 'list_string']);
    $texts    = $admin->getFieldOptions($bundles, ['text', 'text_long', 'string', 'string_long', 'link']);

    $definition = [
      'current_view_mode' => $this->viewMode,
      'fieldable_form'    => TRUE,
      'target_type'       => $this->getFieldSetting('target_type'),
      'target_bundles'    => $bundles,
      'classes'           => $strings,
      'captions'          => $admin->getFieldOptions($bundles),
      'images'            => $admin->getFieldOptions($bundles, ['image']),
      'links'             => $admin->getFieldOptions($bundles, ['text', 'string', 'link']),
      'layouts'           => $strings,
      'thumb_captions'    => $texts,
      'titles'            => $texts,
      'vanilla'           => TRUE,
      'multimedia'        => TRUE,
      'settings'          => $this->getSettings(),
    ];

    $admin->openingForm($element, $definition);
    $admin->imageForm($element, $definition);
    $admin->closingForm($element, $definition);

    $layout_description = $element['layout']['#description'];
    $element['layout']['#description'] = t('Create a dedicated List (text - max number 1) field related to the caption placement to have unique layout per slide with the following supported keys: top, right, bottom, left, center, center-top, etc. Be sure its formatter is Key.') . ' ' . $layout_description;

    $element['media_switch']['#options']['media'] = t('Image to iframe');
    $element['media_switch']['#description'] .= ' ' . t('Be sure the enabled fields here are not hidden/disabled at its view mode.');
    $element['image']['#description'] .= ' ' . t('For video/audio, this allows separate highres image.');
    $element['caption']['#description'] = t('Check fields to be treated as captions.');

    return $element;
  }

}
