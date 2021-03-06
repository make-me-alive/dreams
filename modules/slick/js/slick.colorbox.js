/**
 * @file
 * Provides Colorbox integration for Image and Media fields.
 */

(function ($, Drupal, drupalSettings, window) {

  "use strict";

  Drupal.slickColorbox = Drupal.slickColorbox || {};
  var $window = $(window),
    cboxResizeTimer,
    $body = $("body");

  Drupal.behaviors.slickColorbox = {
    attach: function (context) {
      if (!$.isFunction($.colorbox) || typeof drupalSettings.colorbox === "undefined") {
        return;
      }

      // Disable Colorbox for small screens, if so configured.
      if (drupalSettings.colorbox.mobiledetect && window.matchMedia) {
        var c = drupalSettings.colorbox.mobiledevicewidth,
          mq = window.matchMedia("(max-device-width: " + c + ")");
        if (mq.matches) {
          return;
        }
      }

      // Including slick-cloned.
      $(".slick__colorbox", context).once("slick-colorbox").each(function() {
        var t = $(this),
          id = t.closest(".slick").attr("id"),
          media = t.data("media") || {},
          $slider = t.closest(".slick__slider", "#" + id + ".slick"),
          isSlick = $slider.length,
          isMedia = media.type !== "image" ? true : false,
          empty = [],
          curr,
          runtimeOptions = {
            iframe: isMedia,
            rel: media.rel || null,
            onOpen: function () {
              $body.addClass("colorbox-on colorbox-on--" + media.type);
              Drupal.slickColorbox.setMediaDimensions(empty);
              if (isSlick) {
                $slider.slick("slickPause");
              }
            },
            onLoad: function () {
              Drupal.slickColorbox.removeClasses();

              // Rebuild media data based on the current active box.
              if (isMedia) {
                Drupal.slickColorbox.setMediaDimensions(media);
                $body.addClass("colorbox-on--media");
              } else {
                $body.removeClass("colorbox-on--media");
              }

              $body.addClass("colorbox-on colorbox-on--" + media.type);

              // Remove these lines to disable slider scrolling under colorbox.
              if (isSlick) {
                curr = parseInt(t.closest(".slick__slide:not(.slick-cloned)")
                  .data("slickIndex"));
                if ($slider.parent().next(".slick").length) {
                  var $thumb = $slider.parent().next(".slick")
                    .find(".slick__slider");
                  $thumb.slick("slickGoTo", curr);
                }
                $slider.slick("slickGoTo", curr);
              }
            },
            onCleanup: function () {
              Drupal.slickColorbox.removeClasses();
            },
            onComplete: function () {
              if (media.type !== "image") {
                Drupal.slickColorbox.resize();
              }
              // Overrides colorbox_style.js when Plain style enabled.
              $('#cboxPrevious, #cboxNext', context).removeClass('element-invisible');
            },
            onClosed: function () {
              // 120 offset is to play safe for possible fixed header.
              Drupal.slickColorbox.jumpScroll("#" + id, 120);
              Drupal.slickColorbox.removeClasses();
              Drupal.slickColorbox.setMediaDimensions(empty);
            }
          };

        t.colorbox($.extend({}, drupalSettings.colorbox, runtimeOptions));
      });

      $window.on("resize", function () {
        Drupal.slickColorbox.resize(true);
      });

      $(context).on("cbox_complete", function () {
        Drupal.attachBehaviors("#cboxLoadedContent");
      });
    }
  };

  Drupal.slickColorbox.setMediaDimensions = function (media) {
    $body.data("mediaHeight", media.height);
    $body.data("mediaWidth", media.width);
  };

  Drupal.slickColorbox.removeClasses = function () {
    $body.removeClass(function (index, css) {
      return (css.match(/(^|\s)colorbox-\S+/g) || []).join(' ');
    });
  };

  Drupal.slickColorbox.jumpScroll = function (id, o) {
    if ($(id).length) {
      $("html, body").stop().animate({
        scrollTop: $(id).offset().top - o
      }, 800);
    }
  };

  // Colorbox has no responsive support so far, drop them all when it does.
  Drupal.slickColorbox.resize = function (resize) {
    if (cboxResizeTimer) {
      window.clearTimeout(cboxResizeTimer);
    }

    var o = {
      maxWidth: $body.data("mediaWidth") || drupalSettings.colorbox.maxWidth,
      maxHeight: $body.data("mediaHeight") || drupalSettings.colorbox.maxHeight
    };

    cboxResizeTimer = window.setTimeout(function () {
      if ($("#cboxOverlay").is(":visible")) {
        var $container = $("#cboxLoadedContent"),
          $iframe = $(".cboxIframe", $container);

        if ($iframe.length) {
          $container.addClass("media media--ratio");
          $iframe.attr("width", o.maxWidth).attr("height", o.maxHeight).addClass("media__ratio");
          // if (resize && $window.width() < o.maxWidth && $window.width() < 640) {
          // o.maxHeight = $iframe.height();
          // o.maxWidth = $window.width();
          // }
          $container.css({paddingBottom: (o.maxHeight / o.maxWidth) * 100 + "%", height: 0});
        } else {
          $container.removeClass("media media--ratio");
          $container.css({paddingBottom: "", height: o.maxHeight}).removeClass("media__ratio");
        }

        $.colorbox.resize({
          innerWidth: o.maxWidth,
          innerHeight: o.maxHeight
        });
      }
    }, 10);
  };

}(jQuery, Drupal, drupalSettings, this));
