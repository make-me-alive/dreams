<?php

/**
 * @file
 * Contains \Drupal\slick\Plugin\Field\FieldFormatter\SlickImageFormatter.
 */

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
use Drupal\slick\SlickDefault;
use Drupal\slick\SlickFormatterInterface;
use Drupal\slick\SlickFormatterTrait;

/**
 * Plugin implementation of the 'slick image' formatter.
 *
 * @FieldFormatter(
 *   id = "slick_image",
 *   label = @Translation("Slick carousel"),
 *   description = @Translation("Display the images as a Slick carousel."),
 *   field_types = {"image"},
 *   quickedit = {"editor" = "disabled"}
 * )
 */
class SlickImageFormatter extends ImageFormatterBase implements ContainerFactoryPluginInterface {
  use SlickFormatterTrait;

  /**
   * The slick field formatter manager.
   *
   * @var \Drupal\slick\SlickFormatterInterface.
   */
  protected $formatter;

  /**
   * Constructs a SlickImageFormatter instance.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, SlickFormatterInterface $formatter) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->formatter = $formatter;
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
      $container->get('slick.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return SlickDefault::extendedSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (!isset($items[0])) {
      return [];
    }

    $build = $this->formatter->buildSettings($items, $langcode, $this->getSettings());
    $build += $this->buildElements($files, $build['settings']);

    return $this->manager()->build($build);
  }

  /**
   * Build the slick carousel elements.
   */
  public function buildElements($files, $settings = []) {
    $build  = [];
    $medium = $this->formatter->setDimensions($files[0]->_referringItem, $settings['image_style'], $files[0]->getFileUri());

    foreach ($files as $key => $file) {
      /* @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item = $file->_referringItem;
      $slide = array();
      $media = ['delta' => $key, 'uri' => $file->getFileUri(), 'type' => 'image'];
      $slide['item'] = $item;
      $slide['settings'] = $settings;
      $slide['media'] = array_merge($media, $medium);

      if (!empty($settings['caption'])) {
        foreach ($settings['caption'] as $caption) {
          $slide['caption'][$caption] = empty($item->$caption) ? [] : ['#markup' => Xss::filterAdmin($item->$caption)];
        }
      }

      // Image with responsive image, lazyLoad, and lightbox supports.
      $slide['slide'] = $this->formatter->getImage($slide);
      $build['items'][$key] = $slide;

      if ($settings['nav']) {
        $caption = $settings['thumbnail_caption'];
        $slide['caption'] = empty($item->$caption) ? [] : ['#markup' => Xss::filterAdmin($item->$caption)];

        // Thumbnail usages: asNavFor pagers, dot, arrows, photobox thumbnails.
        $slide['slide'] = empty($settings['thumbnail_style']) ? [] : $this->formatter->getThumbnail($slide);
        $build['thumb']['items'][$key] = $slide;
      }
      unset($slide);
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $captions   = ['title' => t('Title'), 'alt' => t('Alt')];
    $definition = [
      'current_view_mode' => $this->viewMode,
      'captions'          => $captions,
      'thumb_captions'    => $captions,
      'settings'          => $this->getSettings(),
    ];

    $this->admin()->openingForm($element, $definition);
    $this->admin()->imageForm($element, $definition);
    $this->admin()->closingForm($element, $definition);
    return $element;
  }

}
