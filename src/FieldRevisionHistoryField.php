<?php

namespace Drupal\field_revision_history;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field_revision_history\Ajax\ScrollToCommand;

/**
 * Class constructor for ajax field revision history revert.
 */
class FieldRevisionHistoryField {

  /**
   * @param array $form
   *   Form array.
   * @param FormStateInterface $form_state
   *   Form state array.
   *
   * @return AjaxResponse
   */
  public static function apply(array &$form, FormStateInterface $form_state): AjaxResponse {
    $data = Json::decode($form_state->getValue('field_revision_history_apply_data') ?? '[]') ?: [];
    if (empty($data['field_name']) || empty($data['revision_id'])) {
      return new AjaxResponse();
    }

    $field_name = $data['field_name'];
    $form_object = $form_state->getFormObject();
    $entity = $form_object->getEntity();

    try {
      $revision = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->loadRevision($data['revision_id']);
    }
    catch (\Exception $e) {
      \Drupal::logger('field_revision_history')->error($e->getMessage());
      return new AjaxResponse();
    }

    $langcode = $data['langcode'] ?? $entity->language()->getId();
    if ($revision->hasTranslation($langcode)) {
      $revision = $revision->getTranslation($langcode);
    }

    // If no field just return empty response.
    if (!$entity->hasField($field_name) || !$revision->hasField($field_name)) {
      return new AjaxResponse();
    }

    // Get value from the revision and rebuild widget.
    $values = $revision->get($field_name)->getValue();
    $entity->set($field_name, $values);

    $widget_parents = $form[$field_name]['widget']['#parents'] ?? ($form[$field_name]['#parents'] ?? [$field_name]);
    $input = $form_state->getUserInput();
    NestedArray::unsetValue($input, $widget_parents);

    $form_state->setUserInput($input);
    WidgetBase::setWidgetState(
      $form[$field_name]['widget']['#field_parents'] ?? [],
      $field_name,
      $form_state,
      ['items_count' => max(0, count($values))]
    );

    // Rebuild form.
    $form_state->setRebuild(TRUE);
    $form = \Drupal::formBuilder()->rebuildForm($form_state->getBuildInfo()['form_id'], $form_state, $form);

    // Get wrapper.
    $wrapper_selector = 'edit-' . Html::getId($field_name) . '-field-revision-history-ajax-wrapper';

    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new ReplaceCommand('#' . $wrapper_selector, $form[$field_name]));
    $response->addCommand(new ScrollToCommand($wrapper_selector));

    return $response;
  }

}
