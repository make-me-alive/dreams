(function (domready, Drupal) {
  'use strict';
  var a2a;
  domready(function () {
    window.Drupal.behaviors.addToAny = {
      attach: function (context, settings) {
        // If not the full document (it's probably AJAX), and window.a2a exists
        if (context !== document && a2a) {
          a2a.init_all('page'); // Init all uninitiated AddToAny instances
        }
      }
    };
  });
})(domready, Drupal);
