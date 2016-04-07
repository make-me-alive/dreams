<?php

/**
 * @file
 * Contains \Drupal\slick\SlickSkin.
 */

namespace Drupal\slick;

/**
 * Implements SlickSkinInterface.
 */
class SlickSkin implements SlickSkinInterface {

  /**
   * {@inheritdoc}
   */
  public function skins() {
    $skins = [
      'default' => [
        'name' => t('Default'),
        'group' => 'main',
        'provider' => 'slick',
        'css' => [
          'theme' => [
            'css/theme/slick.theme--default.css' => [],
          ],
        ],
      ],
      'asnavfor' => [
        'name' => t('Thumbnail: asNavFor'),
        'group' => 'thumbnail',
        'provider' => 'slick',
        'css' => [
          'theme' => [
            'css/theme/slick.theme--asnavfor.css' => [],
          ],
        ],
        'description' => t('Affected thumbnail navigation only.'),
      ],
      'classic' => [
        'name' => t('Classic'),
        'group' => 'main',
        'provider' => 'slick',
        'description' => t('Adds dark background color over white caption, only good for slider (single slide visible), not carousel (multiple slides visible), where small captions are placed over images.'),
        'css' => [
          'theme' => [
            'css/theme/slick.theme--classic.css' => [],
          ],
        ],
      ],
      'fullscreen' => [
        'name' => t('Full screen'),
        'group' => 'main',
        'provider' => 'slick',
        'description' => t('Adds full screen display, works best with 1 slidesToShow.'),
        'css' => [
          'theme' => [
            'css/theme/slick.theme--full.css' => [],
            'css/theme/slick.theme--fullscreen.css' => [],
          ],
        ],
      ],
      'fullwidth' => [
        'name' => t('Full width'),
        'group' => 'main',
        'provider' => 'slick',
        'description' => t('Adds .slide__constrained wrapper to hold caption overlay within the max-container.'),
        'css' => [
          'theme' => [
            'css/theme/slick.theme--full.css' => [],
            'css/theme/slick.theme--fullwidth.css' => [],
          ],
        ],
      ],
      'grid' => [
        'name' => t('Grid'),
        'group' => 'main',
        'provider' => 'slick',
        'description' => t('The last grid carousel. Use slidesToShow > 1 to have more grid combination, only if you have considerable amount of grids, otherwise 1.'),
        'css' => [
          'theme' => [
            'css/theme/slick.theme--grid.css' => [],
          ],
        ],
      ],
      'split' => [
        'name' => t('Split'),
        'group' => 'main',
        'provider' => 'slick',
        'description' => t('Puts image and caption side by side, related to slide layout options.'),
        'css' => [
          'theme' => [
            'css/theme/slick.theme--split.css' => [],
          ],
        ],
      ],
    ];

    return $skins;
  }

}
