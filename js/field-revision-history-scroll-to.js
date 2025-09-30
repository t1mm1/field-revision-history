/**
 * @file
 * AJAX commands used by Field Revision History module.
 */
(function ($, Drupal) {

  /**
   * Check visibility of element.
   *
   * @param element
   * @returns {boolean}
   */
  function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return rect.bottom > 0 && rect.top < window.innerHeight;
  }

  /**
   * Prototype for command.
   *
   * @param ajax
   * @param response
   * @param status
   */
  Drupal.AjaxCommands.prototype.scrollTo = function (ajax, response, status) {
    const id = response.element_id;
    const options = response.options || {};

    setTimeout(function() {
      const element = document.getElementById(id);
      const link = document.querySelector( '#' + id + ' a.field-revision-history-ajax');
      if (element && link && !isInViewport(link)) {
        element.scrollIntoView({
          behavior: options.behavior || 'smooth',
          block: options.block || 'start',
          inline: options.inline || 'nearest'
        });
      }
    }, 100);
  };
})(jQuery, Drupal);
