<?php

/**
 * @file
 * Contains \Drupal\slick\SlickAdmin.
 */

namespace Drupal\slick;

use Drupal\Core\Url;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormState;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\slick\SlickManagerInterface;

/**
 * Provides resusable admin functions or form elements.
 */
class SlickAdmin implements SlickAdminInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The typed config manager service.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * The slick manager service.
   *
   * @var \Drupal\slick\SlickManagerInterface.
   */
  protected $manager;

  /**
   * Constructs a SlickAdmin object.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed config service.
   * @param \Drupal\slick\SlickManagerInterface $manager
   *   The slick manager service.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository, TypedConfigManagerInterface $typed_config, SlickManagerInterface $manager) {
    $this->entityDisplayRepository = $entity_display_repository;
    $this->typedConfig = $typed_config;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static ($container->get('entity_display.repository'), $container->get('config.typed'), $container->get('slick.manager'));
  }

  /**
   * Returns shared form elements across Slick field formatter and Views.
   */
  public function openingForm(array &$form, $definition = []) {
    $path         = drupal_get_path('module', 'slick');
    $readme       = Url::fromUri('base:' . $path . '/README.txt')->toString();
    $readme_field = Url::fromUri('base:' . $path . '/src/Plugin/Field/README.txt')->toString();
    $arrows       = $this->getArrowOptions();
    $dots         = $this->getDotOptions();

    $vanilla = '';
    if (isset($definition['vanilla'])) {
      $vanilla = ' form--vanilla';
    }
    $form['opening'] = [
      '#markup' => '<div class="form--slick form--half has-tooltip' . $vanilla . '">',
      '#weight' => -110,
    ];

    $form['vanilla'] = [
      '#type'        => 'checkbox',
      '#title'       => t('Vanilla slick'),
      '#description' => t('<strong>Check</strong>:<ul><li>To ignore theme_slick_slide(), and render slick item as is as a vanilla Slick.</li><li>To disable 99% Slick features, and most of the mentioned options here, such as layouts, asNavFor, lazyLoad, et al.</li><li>Things may be broken! You are on your own.</li></ul><strong>Uncheck</strong>:<ul><li>To get consistent Slick slide markups and its advanced features -- relevant for the provided options as Slick needs to know what to style/work with.</li></ul>'),
      '#weight'      => -109,
      '#enforced'    => TRUE,
      '#access'      => isset($definition['vanilla']),
      '#wrapper_attributes' => ['class' => ['form-item--full', 'form-item--tooltip-bottom']],
    ];

    $form['optionset'] = [
      '#type'        => 'select',
      '#title'       => t('Optionset main'),
      '#options'     => $this->getOptionsetsByGroupOptions('main'),
      '#enforced'    => TRUE,
      '#description' => t('Enable slick UI module to manage the optionsets.'),
    ];

    if ($this->manager->getModuleHandler()->moduleExists('slick_ui')) {
      $form['optionset']['#description'] = t('Manage optionsets at <a href=":url" target="_blank">Slick carousel admin page</a>.', [':url' => Url::fromRoute('entity.slick.collection')->toString()]);
    }

    $form['skin'] = [
      '#type'        => 'select',
      '#title'       => t('Skin main'),
      '#options'     => $this->getSkinOptions('main'),
      '#enforced'    => TRUE,
      '#description' => t('Skins allow various layouts with just CSS. Some options below depend on a skin. However a combination of skins and options may lead to unpredictable layouts, get yourself dirty. See <a href=":url" target="_blank">SKINS section at README.txt</a> for details on Skins. Leave empty to DIY. Or use hook_slick_skins_info() and implement \Drupal\slick\SlickSkinInterface to register ones.', [':url' => $readme]),
    ];

    $form['optionset_thumbnail'] = [
      '#type'        => 'select',
      '#title'       => t('Optionset thumbnail'),
      '#options'     => $this->getOptionsetsByGroupOptions('thumbnail'),
      '#description' => t('If provided, asNavFor aka thumbnail navigation applies. Leave empty to not use thumbnail navigation.'),
      '#access'      => isset($definition['thumb_captions']),
    ];

    $form['skin_thumbnail'] = [
      '#type'        => 'select',
      '#title'       => t('Skin thumbnail'),
      '#options'     => $this->getSkinOptions('thumbnail'),
      '#description' => t('Thumbnail navigation skin. See main <a href="@url" target="_blank">README</a> for details on Skins. Leave empty to not use thumbnail navigation.', ['@url' => $readme]),
      '#access'      => isset($definition['thumb_captions']),
    ];

    $form['skin_arrows'] = [
      '#type'        => 'select',
      '#title'       => t('Skin arrows'),
      '#options'     => $arrows ?: [],
      '#enforced'    => TRUE,
      '#description' => t('Implement \Drupal\slick\SlickSkinInterface::arrows() to add your own arrows skins, in the same format as SlickSkinInterface::skins().'),
      '#access'      => count($arrows) > 0,
    ];

    $form['skin_dots'] = [
      '#type'        => 'select',
      '#title'       => t('Skin dots'),
      '#options'     => $dots ?: [],
      '#enforced'    => TRUE,
      '#description' => t('Implement \Drupal\slick\SlickSkinInterface::dots() to add your own dots skins, in the same format as SlickSkinInterface::skins().'),
      '#access'      => count($dots) > 0,
    ];

    $form['layout'] = [
      '#type'        => 'select',
      '#title'       => t('Layout'),
      '#options'     => isset($definition['layouts']) ? $this->getLayoutOptions() + $definition['layouts'] : $this->getLayoutOptions(),
      '#description' => t('Requires a skin. The builtin layouts affects the entire slides uniformly. Split half requires any skin Split. See <a href="@url" target="_blank">README</a> under "Slide layout" for more info. Leave empty to DIY.', ['@url' => $readme_field]),
      '#weight'      => 2,
    ];

    $form['thumbnail_caption'] = [
      '#type'        => 'select',
      '#title'       => t('Thumbnail caption'),
      '#options'     => isset($definition['thumb_captions']) ? $definition['thumb_captions'] : [],
      '#description' => t('Thumbnail caption maybe just title/ plain text. If Thumbnail image style is not provided, the thumbnail pagers will be just text like regular tabs.'),
      '#access'      => isset($definition['thumb_captions']),
      '#states' => [
        'visible' => [
          'select[name*="[optionset_thumbnail]"]' => ['!value' => ''],
        ],
      ],
      '#weight'      => 2,
    ];

    $form['caption'] = [
      '#type'        => 'checkboxes',
      '#title'       => t('Caption fields'),
      '#options'     => isset($definition['captions']) ? $definition['captions'] : [],
      '#description' => t('Captions will attempt to use Alt and Title attributes if enabled.'),
      '#access'      => isset($definition['captions']),
      '#weight'       => 80,
    ];

    $weight = -99;
    foreach (Element::children($form) as $key) {
      if (!isset($form[$key]['#weight'])) {
        $form[$key]['#weight'] = ++$weight;
      }
    }
  }

  /**
   * Returns re-usable image formatter form.
   */
  public function imageForm(array &$form, $definition = []) {
    $is_colorbox   = function_exists('colorbox_theme');
    $is_photobox   = function_exists('photobox_theme');
    $is_responsive = function_exists('responsive_image_get_image_dimensions');
    $image_styles  = function_exists('image_style_options') ? image_style_options(FALSE) : [];
    $photobox      = \Drupal::root() . '/libraries/photobox/photobox/jquery.photobox.js';

    if (is_file($photobox)) {
      $is_photobox = TRUE;
    }

    $form['image_style'] = [
      '#type'        => 'select',
      '#title'       => t('Image style'),
      '#options'     => $image_styles,
      '#description' => t('The main image style. If Slick media module installed, this also determines iframe sizes to have various iframe dimensions with just a single file entity view mode, relevant for a mix of image and multimedia to get a consistent display.'),
    ];

    $form['thumbnail_style'] = [
      '#type'        => 'select',
      '#title'       => t('Thumbnail style'),
      '#options'     => $image_styles,
      '#description' => t('Usages: <ol><li>If <em>Optionset thumbnail</em> provided, it is for asNavFor thumbnail navigation.</li><li>If <em>Dots with thumbnail</em> selected, displayed when hovering over dots.</li><li>Photobox thumbnail.</li><li>Custom work to build arrows with thumbnails via the provided data-thumb attributes.</li></ol>Leave empty to not use thumbnails.'),
    ];

    $form['thumbnail_hover'] = [
      '#type'        => 'checkbox',
      '#title'       => t('Dots with thumbnail'),
      '#description' => t('Dependent on a skin, dots option enabled, and Thumbnail style. If checked, dots pager are kept, and thumbnail will be hidden and only visible on mouseover, default to min-width 120px. Alternative to asNavFor aka separate thumbnails as slider.'),
      '#states' => [
        'visible' => [
          'select[name*="[thumbnail_style]"]' => ['!value' => ''],
        ],
      ],
    ];

    $form['media_switch'] = [
      '#type'        => 'select',
      '#title'       => t('Media switcher'),
      '#options'     => [
        'content' => t('Image linked to content'),
      ],
      '#description' => t('Depends on the enabled supported modules, or has known integration with Slick.<ol><li>Link to content: for aggregated small slicks.</li><li>Image to iframe: audio/video is hidden below image until toggled, otherwise iframe is always displayed, and draggable fails. Aspect ratio applies.</li><li>Colorbox.</li><li>Photobox. Be sure to select "Thumbnail style" for the overlay thumbnails.</li><li>Intense: image to fullscreen intense image.</li></ol>'),
      '#prefix' => '<h3 class="form--slick__title">' . t('Fields') . '</h3>',
    ];

    $form['responsive_image_style'] = [
      '#type'        => 'select',
      '#title'       => t('Responsive image'),
      '#options'     => $this->getResponsiveImageOptions(),
      '#description' => t('Responsive image style for the main stage image is only reasonable for large images. Not compatible with aspect ratio, yet. Leave empty to disable.'),
      '#access'      => $is_responsive && $this->getResponsiveImageOptions(),
    ];

    if ($this->manager->getModuleHandler()->moduleExists('responsive_image')) {
      $form['optionset']['#description'] .= ' ' . t('<a href=":url" target="_blank">Manage responsive image styles</a>.', [':url' => Url::fromRoute('entity.responsive_image_style.collection')->toString()]);
    }

    // http://en.wikipedia.org/wiki/List_of_common_resolutions
    $ratio = ['1:1', '4:3', '16:9', 'fluid'];
    $form['ratio'] = [
      '#type'        => 'select',
      '#title'       => t('Aspect ratio'),
      '#options'     => array_combine($ratio, $ratio),
      '#description' => t('Aspect ratio to get consistently responsive images and iframes. Required if using media entity to switch between iframe and overlay image, otherwise DIY. <a href="@dimensions" target="_blank">Image styles and video dimensions</a> must <a href="@follow" target="_blank">follow the aspect ratio</a>. If not, images will be unexpectedly resized. Choose <strong>fluid</strong> if unsure, or want to fix lazyLoad ondemand reflow and excessive height issues. <a href="@link" target="_blank">Learn more</a>, or leave empty if you care not for aspect ratio, or prefer to DIY. ', [
        '@dimensions' => '//size43.com/jqueryVideoTool.html',
        '@follow'     => '//en.wikipedia.org/wiki/Aspect_ratio_%28image%29',
        '@link'       => '//www.smashingmagazine.com/2014/02/27/making-embedded-content-work-in-responsive-design/',
      ]),
      '#states' => [
        'visible' => [
          'select[name*="[responsive_image_style]"]' => ['value' => ''],
        ],
      ],
    ];

    if (isset($definition['fieldable_form'])) {
      $form['iframe_lazy'] = [
        '#type'        => 'checkbox',
        '#title'       => t('Lazy iframe'),
        '#description' => t('Check to make the video/audio iframes truly lazyloaded, and speed up loading time. Depends on JS enabled at client side.'),
        '#states' => [
          'visible' => [
            'select[name*="[media_switch]"]' => ['value' => 'media'],
          ],
        ],
      ];

      $form['view_mode'] = [
        '#type'        => 'select',
        '#options'     => isset($definition['target_type']) ? $this->entityDisplayRepository->getViewModeOptions($definition['target_type']) : [],
        '#title'       => t('View mode'),
        '#description' => t('Required to grab the fields. Be sure the selected "View mode" is enabled, and the enabled fields here are not hidden there. Manage view modes on the <a href=":view_modes">View modes page</a>.', [':view_modes' => Url::fromRoute('entity.entity_view_mode.collection')->toString()]),
        '#access'      => isset($definition['target_type']),
      ];

      $this->fieldableForm($form, $definition);
    }

    // Optional lightbox integration.
    if ($is_colorbox || $is_photobox) {
      if ($is_colorbox) {
        $form['media_switch']['#options']['colorbox'] = t('Image to colorbox');
      }

      if ($is_photobox) {
        $form['media_switch']['#options']['photobox'] = t('Image to photobox');
      }

      // Re-use the same image style for both lightboxes.
      $form['box_style'] = [
        '#type'    => 'select',
        '#title'   => t('Lightbox image style'),
        '#options' => $image_styles,
        '#states'  => [
          'visible' => [
            'select[name*="[media_switch]"]' => [['value' => 'colorbox'], ['value' => 'photobox']],
          ],
        ],
      ];

      if (isset($definition['multimedia']) && isset($definition['fieldable_form'])) {
        $form['dimension'] = [
          '#type'        => 'textfield',
          '#title'       => t('Lightbox media dimension'),
          '#description' => t('Use WIDTHxHEIGHT, e.g.: 640x360. This allows video dimensions for the lightbox to be different from the lightbox image style.'),
          '#states'      => [
            'visible' => [
              'select[name*="[media_switch]"]' => [['value' => 'colorbox'], ['value' => 'photobox']],
            ],
          ],
        ];
      }
    }
  }

  /**
   * Returns re-usable fieldable formatter form.
   */
  public function fieldableForm(array &$form, $definition = []) {
    $form['image'] = [
      '#type'        => 'select',
      '#title'       => t('Main image'),
      '#options'     => isset($definition['images']) ? $definition['images'] : [],
      '#description' => t('Main background/stage image field.'),
      '#access'      => isset($definition['images']),
    ];

    $form['thumbnail'] = array(
      '#type'        => 'select',
      '#title'       => t('Thumbnail image'),
      '#options'     => isset($definition['thumbnails']) ? $definition['thumbnails'] : [],
      '#description' => t("Only needed if <em>Optionset thumbnail</em> is provided. Maybe the same field as the main image, only different instance. Leave empty to not use thumbnail pager."),
      '#access'      => isset($definition['thumbnails']),
    );

    $form['overlay'] = array(
      '#type'        => 'select',
      '#title'       => t('Overlay media/slicks'),
      '#options'     => isset($definition['overlays']) ? $definition['overlays'] : [],
      '#description' => t('For audio/video, be sure the display is not image. For nested slicks, use the Slick carousel formatter for this field. Zebra layout is reasonable for overlay and captions.'),
      '#access'      => isset($definition['overlays']),
    );

    $form['title'] = [
      '#type'        => 'select',
      '#title'       => t('Title'),
      '#options'     => isset($definition['titles']) ? $definition['titles'] : [],
      '#description' => t('If provided, it will bre wrapped with H2 and class .slide__title.'),
      '#access'      => isset($definition['titles']),
    ];

    $form['link'] = [
      '#type'        => 'select',
      '#title'       => t('Link'),
      '#options'     => isset($definition['links']) ? $definition['links'] : [],
      '#description' => t('Link to content: Read more, View Case Study, etc, wrapped with class .slide__link.'),
      '#access'      => isset($definition['links']),
    ];

    $form['class'] = [
      '#type'        => 'select',
      '#title'       => t('Slide class'),
      '#options'     => isset($definition['classes']) ? $definition['classes'] : [],
      '#description' => t('If provided, individual slide will have this class, e.g.: to have different background with transparent images and skin Split. Be sure its formatter is Key.'),
      '#access'      => isset($definition['classes']),
      '#weight'      => 6,
    ];

    $form['id'] = [
      '#type'         => 'textfield',
      '#title'        => t('Slick ID'),
      '#size'         => 40,
      '#maxlength'    => 255,
      '#field_prefix' => '#',
      '#enforced'     => TRUE,
      '#description'  => t("Manually define the Slick carousel container ID. <em>This ID is used for the cache identifier, so be sure it is unique</em>. Leave empty to have a guaranteed unique ID managed by the module."),
      '#access'       => isset($definition['id']),
      '#weight'       => 94,
    ];

    $form['caption']['#description'] = t('Enable any of the following fields as slide caption. These fields are treated and wrapped as captions. Be sure to make them visible at their relevant Manage display.');
  }

  /**
   * Returns re-usable grid elements across Slick field formatter and Views.
   */
  public function gridForm(array &$form, $definition = []) {
    $range = range(1, 12);
    $grid_options = array_combine($range, $range);

    $header = t('Group individual slide as block grid?<small>An older alternative to core <strong>Rows</strong> option. Only works if the total items &gt; <strong>Visible slides</strong>. <br />block grid != slidesToShow option, yet both can work in tandem.<br />block grid = Rows option, yet the first is module feature, the later core.</small>');
    $form['grid_header'] = [
      '#type'   => 'item',
      '#markup' => '<h3 class="form--slick__title">' . $header . '</h3>',
    ];

    $form['grid'] = [
      '#type'        => 'select',
      '#title'       => t('Grid large'),
      '#options'     => $grid_options,
      '#description' => t('The amount of block grid columns for large monitors 64.063em - 90em. <br /><strong>Requires</strong>:<ol><li>Visible slides,</li><li>Skin Grid for starter,</li><li>A reasonable amount of contents,</li><li>Optionset with Rows and slidesPerRow = 1.</li></ol>This is module feature, older than core Rows, and offers more flexibility. Leave empty to DIY, or to not build grids.'),
      '#enforced'    => TRUE,
    ];

    $form['grid_medium'] = [
      '#type'        => 'select',
      '#title'       => t('Grid medium'),
      '#options'     => $grid_options,
      '#description' => t('The amount of block grid columns for medium devices 40.063em - 64em.'),
    ];

    $form['grid_small'] = [
      '#type'        => 'select',
      '#title'       => t('Grid small'),
      '#options'     => $grid_options,
      '#description' => t('The amount of block grid columns for small devices 0 - 40em.'),
    ];

    $form['visible_slides'] = [
      '#type'        => 'select',
      '#title'       => t('Visible slides'),
      '#options'     => array_combine(range(1, 32), range(1, 32)),
      '#description' => t('How many items per slide displayed at a time. Required if Grid provided. Grid will not work if Views rows count &lt; <strong>Visible slides</strong>.'),
    ];

    $form['preserve_keys'] = [
      '#title'       => t('Preserve keys'),
      '#type'        => 'checkbox',
      '#description' => t('If checked, keys will be preserved. Default is FALSE which will reindex the grid chunk numerically.'),
    ];

    $grids = [
      'grid_header',
      'grid_medium',
      'grid_small',
      'visible_slides',
      'preserve_keys',
    ];

    foreach ($grids as $key) {
      $form[$key]['#enforced'] = TRUE;
      $form[$key]['#states'] = [
        'visible' => [
          'select[name$="[grid]"]' => ['!value' => ''],
        ],
      ];
    }
  }

  /**
   * Returns shared ending form elements across Slick field formatter and Views.
   */
  public function closingForm(array &$form, $definition = []) {
    $form['cache'] = [
      '#type'        => 'select',
      '#title'       => t('Cache'),
      '#options'     => $this->getCacheOptions(),
      '#weight'      => 98,
      '#enforced'    => TRUE,
      '#description' => t('Ditch all the slick logic to cached bare HTML. <ol><li><strong>Permanent</strong>: cached contents will persist (be displayed) till the next cron runs.</li><li><strong>Any number</strong>: expired by the selected expiration time, and fresh contents are fetched till the next cache rebuilt.</li></ol>A working cron job is required to clear stale cache. At any rate, cached contents will be refreshed regardless of the expiration time after the cron hits. <br />Leave it empty to disable caching.<br /><strong>Warning!</strong> Be sure no useless/ sensitive data such as Edit links as they are rendered as is regardless permissions. Only enable it when all is done, otherwise cached options will be displayed while changing them.'),
    ];

    $form['override'] = [
      '#title'       => t('Override main optionset'),
      '#type'        => 'checkbox',
      '#description' => t('If checked, the following options will override the main optionset. Useful to re-use one optionset for several different displays.'),
      '#weight'      => 99,
      '#enforced'    => TRUE,
    ];

    $form['overridables'] = [
      '#type'          => 'checkboxes',
      '#title'         => t('Overridable options'),
      '#description'   => t("Override the main optionset to re-use one. Anything dictated here will override the current main optionset. Unchecked means FALSE"),
      '#options'       => $this->getOverridableOptions(),
      '#weight'        => 100,
      '#enforced'      => TRUE,
      '#states' => [
        'visible' => [
          ':input[name$="[override]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['current_view_mode'] = [
      '#type'          => 'hidden',
      '#default_value' => isset($definition['current_view_mode']) ? $definition['current_view_mode'] : '_custom',
      '#weight'        => 100,
    ];

    $form['closing'] = [
      '#markup' => '</div>',
      '#weight' => 110,
    ];

    $this->finalizeForm($form, $definition);
  }

  /**
   * Returns re-usable logic, styling and assets across Slick fields and Views.
   */
  public function finalizeForm(array &$form, $definition = []) {
    $settings  = isset($definition['settings']) ? $definition['settings'] : [];
    $admin_css = $this->manager->getConfigFactory('admin_css');
    $excludes  = ['container', 'details', 'item', 'hidden', 'submit'];

    foreach (Element::children($form) as $key) {
      if (isset($form[$key]['#type']) && !in_array($form[$key]['#type'], $excludes)) {
        if (!isset($form[$key]['#default_value']) && isset($settings[$key])) {
          $form[$key]['#default_value'] = $settings[$key];
        }
        if (!isset($form[$key]['#attributes']) && isset($form[$key]['#description'])) {
          $form[$key]['#attributes'] = ['class' => ['is-tooltip']];
        }

        if ($admin_css) {
          if ($form[$key]['#type'] == 'checkbox' && $form[$key]['#type'] != 'checkboxes') {
            $form[$key]['#field_suffix'] = '&nbsp;';
            $form[$key]['#title_display'] = 'before';
          }
          elseif ($form[$key]['#type'] == 'checkboxes' && !empty($form[$key]['#options'])) {
            foreach ($form[$key]['#options'] as $i => $option) {
              $form[$key][$i]['#field_suffix'] = '&nbsp;';
              $form[$key][$i]['#title_display'] = 'before';
            }
          }
        }
        if ($form[$key]['#type'] == 'select' && !in_array($key, ['cache', 'optionset', 'view_mode'])) {
          if (!isset($form[$key]['#empty_option']) && !isset($form[$key]['#required'])) {
            $form[$key]['#empty_option'] = t('- None -');
          }
        }

        if (!isset($form[$key]['#enforced']) && isset($definition['vanilla'])) {
          $states['visible'][':input[name*="[vanilla]"]'] = ['checked' => FALSE];
          if (isset($form[$key]['#states'])) {
            $form[$key]['#states']['visible'][':input[name*="[vanilla]"]'] = ['checked' => FALSE];
          }
          else {
            $form[$key]['#states'] = $states;
          }
        }
      }
    }

    if ($admin_css) {
      $form['#attached']['library'][] = 'slick/slick.admin';
    }
  }

  /**
   * Returns time in interval for select options.
   */
  public function getCacheOptions() {
    $period = [0, 60, 180, 300, 600, 900, 1800, 2700, 3600, 10800, 21600, 32400, 43200, 86400];
    $period = array_map([\Drupal::service('date.formatter'), 'formatInterval'], array_combine($period, $period));
    $period[0] = '<' . t('No caching') . '>';
    return $period + [Cache::PERMANENT => t('Permanent')];
  }

  /**
   * Returns available fields for select options.
   */
  public function getFieldOptions($target_bundles = [], $allowed_field_types = [], $entity_type_id = 'media') {
    $options = [];
    $storage = $this->manager->getEntityTypeManager()->getStorage('field_config');

    foreach ($target_bundles as $bundle) {
      if ($fields = $storage->loadByProperties(['entity_type' => $entity_type_id, 'bundle' => $bundle])) {
        foreach ((array) $fields as $field_name => $field) {
          if (empty($allowed_field_types)) {
            $options[$field->getName()] = $field->getLabel();
          }
          elseif (in_array($field->getType(), $allowed_field_types)) {
            $options[$field->getName()] = $field->getLabel();
          }
        }
      }
    }

    return $options;
  }

  /**
   * Returns available slick optionsets for select options.
   */
  public function getOptionsetOptions() {
    $optionsets = [];
    $slicks = $this->manager->loadMultiple('slick');
    foreach ((array) $slicks as $slick) {
      $optionsets[$slick->id()] = Html::escape($slick->label());
    }
    asort($optionsets);
    return $optionsets;
  }

  /**
   * Returns available slick optionsets by group.
   */
  public function getOptionsetsByGroupOptions($group = '') {
    $optionsets = $groups = $ungroups = [];
    $slicks = $this->manager->loadMultiple('slick');
    foreach ($slicks as $slick) {
      $name = Html::escape($slick->label());
      $id = $slick->id();
      $current_group = $slick->getGroup();
      if (!empty($group)) {
        if ($current_group) {
          if ($current_group != $group) {
            continue;
          }
          $groups[$id] = $name;
        }
        else {
          $ungroups[$id] = $name;
        }
      }
      $optionsets[$id] = $name;
    }

    return $group ? array_merge($ungroups, $groups) : $optionsets;
  }

  /**
   * Returns overridable options to re-use one optionset.
   */
  public function getOverridableOptions() {
    $options = [
      'arrows'        => t('Arrows'),
      'autoplay'      => t('Autoplay'),
      'dots'          => t('Dots'),
      'draggable'     => t('Draggable'),
      'infinite'      => t('Infinite'),
      'mouseWheel'    => t('Mousewheel'),
      'randomize'     => t('Randomize'),
      'variableWidth' => t('variableWidth'),
    ];

    $this->manager->getModuleHandler()->alter('slick_overridable_options_info', $options);
    return $options;
  }

  /**
   * Returns available slick skins for select options.
   */
  public function getSkinOptions($group = '') {
    return $this->manager->getSkinsByGroup($group, TRUE);
  }

  /**
   * Returns supported Slick skin arrows for select options.
   */
  public function getArrowOptions() {
    $arrows = &drupal_static(__METHOD__, NULL);

    if (!isset($arrows)) {
      $arrows = [];
      if ($available_arrows = $this->manager->getSkins()['arrows']) {
        foreach ($available_arrows as $key => $properties) {
          $arrows[$key] = Html::escape($properties['name']);
        }
      }
    }
    return $arrows;
  }

  /**
   * Returns supported Slick skin dots for select options.
   */
  public function getDotOptions() {
    $dots = &drupal_static(__METHOD__, NULL);

    if (!isset($dots)) {
      $dots = [];
      if ($available_dots = $this->manager->getSkins()['dots']) {
        foreach ($available_dots as $key => $properties) {
          $dots[$key] = Html::escape($properties['name']);
        }
      }
    }
    return $dots;
  }

  /**
   * Returns default layout options for the core Image, or Views.
   */
  public function getLayoutOptions() {
    $layouts = &drupal_static(__METHOD__, NULL);

    if (!isset($layouts)) {
      $layouts = [
        'bottom'      => t('Caption bottom'),
        'top'         => t('Caption top'),
        'right'       => t('Caption right'),
        'left'        => t('Caption left'),
        'center'      => t('Caption center'),
        'center-top'  => t('Caption center top'),
        'below'       => t('Caption below the slide'),
        'stage-right' => t('Caption left, stage right'),
        'stage-left'  => t('Caption right, stage left'),
        'split-right' => t('Caption left, stage right, split half'),
        'split-left'  => t('Caption right, stage left, split half'),
        'stage-zebra' => t('Stage zebra'),
        'split-zebra' => t('Split half zebra'),
      ];
    }
    return $layouts;
  }

  /**
   * Returns Responsive image for select options.
   */
  public function getResponsiveImageOptions() {
    $options = [];
    if (!$this->manager->getModuleHandler()->moduleExists('responsive_image')) {
      return $options;
    }
    $image_styles = $this->manager->loadMultiple('responsive_image_style');
    if (!empty($image_styles)) {
      foreach ($image_styles as $machine_name => $image_style) {
        if ($image_style->hasImageStyleMappings()) {
          $options[$machine_name] = Html::escape($image_style->label());
        }
      }
    }
    return $options;
  }


  /**
   * Return the field formatter settings summary.
   */
  public function settingsSummary($plugin) {
    $summary    = $form = [];
    $form_state = new FormState();
    $settings   = $plugin->getSettings();
    $elements   = $plugin->settingsForm($form, $form_state);
    $definition = $this->typedConfig->getDefinition('field.formatter.settings.' . $plugin->getPluginId());

    foreach ($settings as $key => $setting) {
      $access  = isset($elements[$key]['#access']) ? $elements[$key]['#access'] : TRUE;
      $title   = isset($elements[$key]['#title']) ? $elements[$key]['#title'] : '';
      $vanilla = !empty($settings['vanilla']) && !isset($elements[$key]['#enforced']);
      if (is_array($setting) || empty($title) || $vanilla || !$access) {
        continue;
      }

      if ($definition['mapping'][$key]['type'] == 'boolean') {
        if (empty($setting)) {
          continue;
        }
        $setting = t('Yes');
      }
      elseif ($definition['mapping'][$key]['type'] == 'string' && empty($setting)) {
        continue;
      }
      if ($key == 'cache') {
        $setting = $this->getCacheOptions()[$setting];
      }

      if (isset($settings[$key])) {
        $summary[] = t('@title: <strong>@setting</strong>', array(
          '@title'   => $title,
          '@setting' => $setting,
        ));
      }
    }
    return $summary;
  }

}
