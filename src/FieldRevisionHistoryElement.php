<?php

namespace Drupal\field_revision_history;

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Represents the Element manager.
 */
class FieldRevisionHistoryElement {

  /**
   * Help function to get widget.
   *
   * @param array $form
   *   The form array.
   * @param object $field_definition
   *   The field definition.
   * @param string $field_name
   *   The field machine name.
   */
  public function &getElementWidget(array &$form, object $field_definition, string $field_name): array {
    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
    $is_multifield = ($cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) || ($cardinality > 1);

    // Set variable $element as form field description.
    // For multi, references, typical fields need to get correct description option of field.
    $widget = &$form[$field_name]['widget'];
    if ($is_multifield) {
      if (!empty($widget['target_id'])) {
        $element = &$widget['target_id'];
      }
      else {
        $element = &$widget;
      }
    }
    else {
      if (!empty($widget[0]['target_id'])) {
        $element = &$widget[0]['target_id'];
      }
      elseif (!empty($widget[0]['value'])) {
        // Need just for title field.
        $element = &$widget[0]['value'];
      }
      elseif (!empty($widget['target_id'])) {
        // For autocomplete fields (like Tags).
        $element = &$widget['target_id'];
      }
      elseif (!empty($widget[0])) {
        $element = &$widget[0];
      }
      else {
        $element = &$widget;
      }
    }

    return $element;
  }

}
