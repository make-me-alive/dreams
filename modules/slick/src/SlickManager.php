<?php

/**
 * @file
 * Contains \Drupal\slick\SlickManager.
 */

namespace Drupal\slick;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\NestedArray;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\slick\Entity\Slick;

/**
 * Implements SlickManagerInterface.
 */
class SlickManager implements SlickManagerInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface;
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a SlickManager object
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler     = $module_handler;
    $this->renderer          = $renderer;
    $this->configFactory     = $config_factory;
    $this->cache             = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('cache.default')
    );
  }

  /**
   * Returns the entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Returns the module handler.
   */
  public function getModuleHandler() {
    return $this->moduleHandler;
  }

  /**
   * Returns the renderer.
   */
  public function getRenderer() {
    return $this->renderer;
  }

  /**
   * Returns the slick config managed by Slick UI, or any.
   */
  public function getConfigFactory($setting_name, $settings = 'slick.settings') {
    return $this->configFactory->get($settings)->get($setting_name);
  }

  /**
   * Returns a shortcut for loading a configuration entity.
   */
  public function load($id, $entity_type = 'slick') {
    return $this->entityTypeManager->getStorage($entity_type)->load($id);
  }

  /**
   * Returns a shortcut for loading multiple configuration entities.
   */
  public function loadMultiple($entity_type = 'slick', $ids = NULL) {
    return $this->entityTypeManager->getStorage($entity_type)->loadMultiple($ids);
  }

  /**
   * Returns available slick default options under group 'settings'.
   */
  public function getDefaultSettings() {
    return $this->load('default')->getOptions('settings');
  }

  /**
   * Returns slick skins registered via hook_slick_skins_info(), or defaults.
   */
  public function getSkins() {
    $skins = &drupal_static(__METHOD__, NULL);
    if (!isset($skins)) {
      $cid = 'slick:skins';
      if ($cache = $this->cache->get($cid)) {
        $skins = $cache->data;
      }
      else {
        $classes = $this->moduleHandler->invokeAll('slick_skins_info');
        $classes = array_merge(['\Drupal\slick\SlickSkin'], $classes);
        $items   = $main = $skins = $dots = $arrows = [];
        foreach ($classes as $class) {
          if (class_exists($class)) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->implementsInterface('\Drupal\slick\SlickSkinInterface')) {
              $skin   = new $class;
              $main   = $skin->skins();
              $dots   = method_exists($skin, 'dots') ? $skin->dots() : [];
              $arrows = method_exists($skin, 'arrows') ? $skin->arrows() : [];
            }
          }
          $items = ['skins' => $main, 'dots' => $dots, 'arrows' => $arrows];
          $skins = NestedArray::mergeDeep($skins, $items);
        }
        $tags = Cache::buildTags($cid, ['count:' . count($items['skins'])]);
        $this->cache->set($cid, $skins, Cache::PERMANENT, $tags);
      }
    }
    return $skins;
  }

  /**
   * Returns all available skins registered via hook_slick_skins_info().
   */
  public function getAvailableSkins() {
    $skins = &drupal_static(__METHOD__, NULL);
    if (!isset($skins)) {
      $skins = [
        'skin'      => $this->getSkinsByGroup('main'),
        'thumbnail' => $this->getSkinsByGroup('thumbnail'),
        'arrows'    => $this->getSkins()['arrows'],
        'dots'      => $this->getSkins()['dots'],
      ];
      $skins = array_filter($skins);
    }
    return $skins;
  }

  /**
   * Returns available slick skins by group.
   */
  public function getSkinsByGroup($group = '', $select = FALSE) {
    $skins = $groups = $ungroups = [];
    foreach ($this->getSkins()['skins'] as $skin => $properties) {
      $item = $select ? Html::escape($properties['name']) : $properties;
      if (!empty($group)) {
        if (isset($properties['group'])) {
          if ($properties['group'] != $group) {
            continue;
          }
          $groups[$skin] = $item;
        }
        else {
          $ungroups[$skin] = $item;
        }
      }
      $skins[$skin] = $item;
    }

    return $group ? array_merge($ungroups, $groups) : $skins;
  }

  /**
   * Returns array of needed assets suitable for #attached for the given slick.
   */
  public function attach($attach = []) {
    $attach += [
      'slick_css'  => $this->getConfigFactory('slick_css'),
      'module_css' => $this->getConfigFactory('module_css'),
      'skin'       => FALSE,
    ];

    $this->moduleHandler->alter('slick_attach_info', $attach);

    $easing = \Drupal::root() . '/libraries/easing/jquery.easing.min.js';
    if (is_file($easing)) {
      $load['library'][] = 'slick/slick.easing';
    }

    if (!empty($attach['lazy']) && $attach['lazy'] == 'blazy') {
      $load['library'][] = 'blazy/blazy';
    }

    $load['library'][] = 'slick/slick';
    $load['library'][] = 'slick/slick.load';

    // @todo redo this when colorbox has JS loader again, or just array.
    if (!empty($attach['colorbox'])) {
      $dummy = [];
      \Drupal::service('colorbox.attachment')->attach($dummy);
      $load = NestedArray::mergeDeep($load, $dummy['#attached']);
    }

    $components = ['colorbox', 'photobox', 'media', 'mousewheel'];
    foreach ($components as $component) {
      if (!empty($attach[$component])) {
        $load['library'][] = 'slick/slick.' . $component;
      }
    }

    $this->attachSkin($load, $attach);

    // Attach default JS settings to allow responsive displays have a lookup,
    // excluding wasted/trouble options, e.g.: PHP string vs JS object.
    $excludes = explode(' ', 'mobileFirst appendArrows appendDots asNavFor prevArrow nextArrow cssEaseBezier cssEaseOverride respondTo');
    $excludes = array_combine($excludes, $excludes);
    $load['drupalSettings']['slick'] = array_diff_key($this->getDefaultSettings(), $excludes);

    $this->moduleHandler->alter('slick_attach_load_info', $load, $attach);
    return $load;
  }

  /**
   * Provides skins if required.
   */
  public function attachSkin(array &$load, $attach = []) {
    if (!$attach['skin']) {
      return;
    }

    // If we do have a defined skin, load the optional Slick and module css.
    if ($attach['slick_css']) {
      $load['library'][] = 'slick/slick.css';
    }
    if ($attach['module_css']) {
      $load['library'][] = 'slick/slick.theme';
    }
    if (!empty($attach['thumbnail_hover'])) {
      $load['library'][] = 'slick/slick.dots.thumbnail';
    }
    if (!empty($attach['down_arrow'])) {
      $load['library'][] = 'slick/slick.arrow.down';
    }

    foreach ($this->getAvailableSkins() as $group => $skins) {
      $skin = $group == 'skin' ? $attach['skin'] : (isset($attach['skin_' . $group]) ? $attach['skin_' . $group] : '');
      if (!empty($skin)) {
        $provider = isset($skins[$skin]['provider']) ? $skins[$skin]['provider'] : 'slick';
        $load['library'][] = 'slick/' . $provider . '.' . $group . '.' . $skin;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function slick($build = []) {
    foreach (['options', 'optionset', 'settings'] as $key) {
      $build[$key] = isset($build[$key]) ? $build[$key] : [];
    }

    $slick = [
      '#theme'      => 'slick',
      '#items'      => [],
      '#build'      => $build,
      '#pre_render' => [[$this, 'preRenderSlick']],
    ];

    $settings          = $build['settings'];
    $suffixes[]        = count($build['items']);
    $suffixes[]        = count(array_filter($settings));
    $suffixes[]        = $settings['cache'];
    $cache['tags']     = Cache::buildTags('slick:' . $settings['id'], $suffixes, '.');
    $cache['contexts'] = ['languages'];
    $cache['max-age']  = $settings['cache'];
    $cache['keys']     = isset($settings['cache_metadata']['keys']) ? $settings['cache_metadata']['keys'] : [$settings['id']];
    $cache['keys'][]   = $settings['display'];

    $slick['#cache']   = $cache;
    return $slick;
  }

  /**
   * Builds the Slick instance as a structured array ready for ::renderer().
   */
  public function preRenderSlick($element) {
    $build = $element['#build'];
    unset($element['#build']);

    if (!isset($build['items'][0])) {
      return [];
    }

    $settings = $build['settings'];

    // Adds helper class if thumbnail on dots hover provided.
    $dots_class = [];
    if (!empty($settings['thumbnail_style']) && !empty($settings['thumbnail_hover'])) {
      $dots_class[] = 'slick-dots--thumbnail';
    }

    // Adds dots skin modifier class if provided.
    if (!empty($settings['skin_dots'])) {
      $dots_class[] = Html::cleanCssIdentifier('slick-dots--' . $settings['skin_dots']);
    }

    if ($dots_class) {
      $dots_class[] = $build['optionset']->getSetting('dotsClass');
      $js['dotsClass'] = implode(" ", $dots_class);
    }

    // Overrides common options to re-use an optionset.
    if ($settings['display'] == 'main') {
      if (!empty($settings['override'])) {
        foreach ($settings['overridables'] as $key => $override) {
          $js[$key] = empty($override) ? FALSE : TRUE;
        }
      }

      // Build the Slick grid if provided.
      if (!empty($settings['grid']) && !empty($settings['visible_slides'])) {
        $build['items'] = $this->buildGrid($build['items'], $settings);
      }
    }

    $build['options'] = isset($js) ? array_merge($build['options'], $js) : $build['options'];
    foreach (['items', 'options', 'optionset', 'settings'] as $key) {
      $element["#$key"] = $build[$key];
    }

    return $element;
  }

  /**
   * Returns items as a grid display.
   */
  public function buildGrid($build = [], array &$settings) {
    $grids = [];
    // Display all items if unslick is enforced for plain grid to lightbox.
    if (!empty($settings['unslick'])) {
      $settings['display']      = 'main';
      $settings['current_item'] = 'grid';
      $settings['count']        = 2;
      $slide['slide'] = [
        '#theme'    => 'slick_grid',
        '#items'    => $build,
        '#delta'    => 0,
        '#settings' => $settings,
      ];
      $slide['settings'] = $settings;
      $grids[0] = $slide;
    }
    else {
      // Otherwise do chunks to have a grid carousel.
      $preserve_keys     = !empty($settings['preserve_keys']);
      $grid_items        = array_chunk($build, $settings['visible_slides'], $preserve_keys);
      $settings['count'] = count($grid_items);
      foreach ($grid_items as $delta => $grid_item) {
        $slide = [];
        $slide['slide'] = [
          '#theme'    => 'slick_grid',
          '#items'    => $grid_item,
          '#delta'    => $delta,
          '#settings' => $settings,
        ];
        $slide['settings'] = $settings;
        $grids[] = $slide;
        unset($slide);
      }
    }
    return $grids;
  }

  /**
   * {@inheritdoc}
   */
  public function build($build = []) {
    foreach (['items', 'options', 'optionset', 'settings'] as $key) {
      $build[$key] = isset($build[$key]) ? $build[$key] : [];
    }

    return [
      '#theme'      => 'slick_wrapper',
      '#items'      => [],
      '#build'      => $build,
      '#pre_render' => [[$this, 'preRenderSlickWrapper']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preRenderSlickWrapper($element) {
    $build = $element['#build'];
    unset($element['#build']);

    if (!isset($build['items'][0])) {
      return [];
    }

    // One slick_theme() to serve multiple displays: main, overlay, thumbnail.
    $defaults = Slick::htmlSettings();
    $settings = $build['settings'] ? array_merge($defaults, $build['settings']) : $defaults;
    $id       = Slick::getHtmlId('slick', $settings['id']);
    $thumb_id = $id . '-thumbnail';
    $options  = $build['options'];
    $switch   = isset($settings['media_switch']) ? $settings['media_switch'] : '';

    // Additional settings.
    $build['optionset'] = $build['optionset'] ?: $this->load($settings['optionset']);
    $settings['nav']    = isset($settings['nav']) ? $settings['nav'] : (!empty($settings['optionset_thumbnail']) && isset($build['items'][1]));
    $mousewheel         = $build['optionset']->getSetting('mouseWheel');

    if ($settings['nav']) {
      $options['asNavFor'] = "#{$thumb_id}-slider";
      $optionset_thumbnail = $this->load($settings['optionset_thumbnail']);
      $mousewheel          = $optionset_thumbnail->getSetting('mouseWheel');
    }

    // Attach libraries.
    if ($switch && $switch != 'content') {
      $settings[$switch] = $switch;
    }

    $settings['mousewheel'] = !empty($options['overridables']['mouseWheel']) || $mousewheel;
    $settings['down_arrow'] = $build['optionset']->getSetting('downArrow');

    $attachments            = $this->attach($settings);
    $build['options']       = $options;
    $build['settings']      = $settings;
    $element['#settings']   = $settings;
    $element['#attached']   = empty($build['attached']) ? $attachments : NestedArray::mergeDeep($build['attached'], $attachments);

    // Build the main Slick.
    $slick[0] = $this->slick($build);

    // Build the Slick asNavFor/thumbnail.
    if ($settings['nav'] && !empty($build['thumb'])) {
      foreach (['items', 'options', 'settings'] as $key) {
        $build[$key] = isset($build['thumb'][$key]) ? $build['thumb'][$key] : [];
      }

      $settings                     = array_merge($settings, $build['settings']);
      $settings['optionset']        = $settings['optionset_thumbnail'];
      $settings['skin']             = isset($settings['skin_thumbnail']) ? $settings['skin_thumbnail'] : '';
      $settings['display']          = 'thumbnail';
      $build['options']['asNavFor'] = "#{$id}-slider";
      $build['optionset']           = $optionset_thumbnail;
      $build['settings']            = $settings;

      unset($build['thumb']);
      $slick[1] = $this->slick($build);
    }

    // Collect the slick instances.
    $element['#items'] = $slick;
    return $element;
  }

}
