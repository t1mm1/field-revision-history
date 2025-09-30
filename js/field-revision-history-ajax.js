/**
 * @file
 * AJAX form update used by Field Revision History module.
 */
(function (Drupal, once) {
  'use strict';

  // Make local cache for element data.
  let applyElements = {
    triggerData: null,
    triggerButton: null,
  };

  /**
   * Help function for getting basic data  selected values.
   *
   * @returns {{triggerData: null, triggerButton: null}}
   */
  function getApplyElements() {
    if (!applyElements.triggerData || !document.body.contains(applyElements.triggerData)) {
      applyElements.triggerData = document.querySelector('[data-drupal-selector="edit-field-revision-history-apply-data"]');
    }
    if (!applyElements.triggerButton || !document.body.contains(applyElements.triggerButton)) {
      applyElements.triggerButton = document.querySelector('[data-drupal-selector="edit-field-revision-history-apply-button"]');
    }
    return applyElements;
  }

  /**
   * Build data.
   *
   * @param element
   * @returns {{}|any}
   */
  function buildDataFromElement(element) {
    const dataset = element.dataset || {};

    let updated = {};
    if (dataset.fieldRevisionHistory) {
      try {
        updated = JSON.parse(dataset.fieldRevisionHistory);
        if (updated.entity_id) {
          updated.entity_id = parseInt(updated.entity_id, 10);
        }
        if (updated.revision_id) {
          updated.revision_id = parseInt(updated.revision_id, 10);
        }
      } catch (e) {
        console.error('Invalid data-field-revision-history JSON', e);
      }
    }

    return updated;
  }

  /**
   * Apply selected data.
   *
   * @param data
   * @returns {boolean}
   */
  function applyData(data) {
    const { triggerData, triggerButton } = getApplyElements();
    if (!triggerData || !triggerButton) {
      console.warn('Apply elements not found (field_revision_history_apply_data / field_revision_history_apply_button).');
      return false;
    }
    triggerData.value = JSON.stringify(data);
    triggerButton.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
    return true;
  }

  /**
   * Drupal behavior.
   *
   * @type {{attach: function(*): void}}
   */
  Drupal.behaviors.fieldRevisionHistoryAjax = {
    attach: function (context) {
      const triggers = once('fieldRevisionHistoryAjax-trigger', context.querySelectorAll('.js-field-revision-history-apply-revision'));
      triggers.forEach((element) => {
        element.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();

          if (element.getAttribute('aria-disabled') === 'true' || element.classList.contains('is-disabled')) {
            return;
          }

          const data = buildDataFromElement(element);
          if (!data || !data.field_name || !data.revision_id) {
            console.error('Missing required data values (field, revision_id, etc).', data);
            return;
          }

          element.classList.add('is-loading');
          element.setAttribute('aria-busy', 'true');

          const result = applyData(data);
          if (!result) {
            setTimeout(() => applyData(data), 10);
          }
        });
      });
    }
  };
})(Drupal, once);
