(function ($, Drupal, drupalSettings) {

  "use strict";

  Drupal.behaviors.webprofiler_diff_database = {
    attach: function (context) {

      var data = drupalSettings.webprofiler_diff.data;

      var chart = c3.generate({
        data: {
          columns: data,
          type: 'bar',
          types: {
            delta: 'line'
          },
          bindto: '#chart'
        }
      });

    }
  }
})
(jQuery, Drupal, drupalSettings);
