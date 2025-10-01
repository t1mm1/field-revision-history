<?php

namespace Drupal\field_revision_history\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\field_revision_history\FieldRevisionHistoryElement;
use Drupal\field_revision_history\FieldRevisionHistoryHelper;
use Drupal\field_revision_history\FieldRevisionHistorySettings;
use Drupal\node\NodeInterface;

/**
 * Generic form alter hook implementation for the module.
 */
class FormAlter {

  use StringTranslationTrait;

  public function __construct(
    private RendererInterface $renderer,
    private ConfigFactoryInterface $configFactory,
    private FieldRevisionHistoryHelper $fieldRevisionHistoryHelper,
    private FieldRevisionHistoryElement $fieldRevisionHistoryElement,
    private FieldRevisionHistorySettings $fieldRevisionHistorySettings,
  ) {
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    // Check is the service available.
    $service_enabled = $this->fieldRevisionHistorySettings->isServiceEnabled();
    if (!$service_enabled) {
      return;
    }

    // Check form type.
    if (!$form_state->getFormObject() instanceof EntityFormInterface) {
      return;
    }

    $entity = $form_state->getFormObject()->getEntity();
    // If entity empty.
    if (empty($entity)) {
      return;
    }

    // Skip if entity is not ContentEntityInterface.
    if (!$entity instanceof NodeInterface) {
      return;
    }

    // Check revisions access.
    if (!$entity->access('view revision')) {
      return;
    }

    // If entity is new.
    if ($entity->isNew()) {
      return;
    }

    $entity_type = $entity->getEntityType();
    $is_revisionable = $entity_type->isRevisionable();
    // If entity doesnt support revisions.
    if (!$is_revisionable) {
      return;
    }

    // Add hidden element.
    // Need to use Form API via Drupal way.
    // We storage selected values inside hidden field and apply it on modal click action.
    $form['field_revision_history_apply_data'] = [
      '#type' => 'hidden',
      '#parents' => ['field_revision_history_apply_data'],
      '#attributes' => [
        'data-drupal-selector' => 'edit-field-revision-history-apply-data',
      ],
      '#weight' => -100,
    ];
    $form['field_revision_history_apply_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Apply revision value'),
      '#ajax' => [
        'callback' => [
          '\Drupal\field_revision_history\FieldRevisionHistoryField', 'apply',
        ],
        'event' => 'click',
        'progress' => ['type' => 'throbber'],
      ],
      '#attributes' => [
        'data-drupal-selector' => 'edit-field-revision-history-apply-button',
        'class' => [
          'visually-hidden',
        ],
      ],
      '#weight' => -99,
    ];

    // Add library for modal.
    $form['#attached']['library'][] = 'field_revision_history/modal';

    // Check all content contrib fields.
    foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
      // Check is current bundle enabled or not.
      // Skip system fields like Author, Revision log, etc.
      // Keep Title field , skip based fields.
      if (!$this->fieldRevisionHistorySettings->isEntityFieldEnabled($entity, $field_definition)) {
        continue;
      }

      // Check field in the form.
      if (empty($form[$field_name])) {
        continue;
      }

      // Create wrapper id related to the machine field name.
      $wrapper_id = 'edit-' . Html::getId($field_name) . '-field-revision-history-ajax-wrapper';

      // Check for duplicates.
      $existing_prefix = $form[$field_name]['#prefix'] ?? '';
      if (!str_contains($existing_prefix, 'id="' . $wrapper_id . '"')) {
        $form[$field_name]['#prefix'] = '<div id="' . $wrapper_id . '">' . $existing_prefix;
        $form[$field_name]['#suffix'] = ($form[$field_name]['#suffix'] ?? '') . '</div>';
      }

      // Generate link for modal field view.
      $url = Url::fromRoute('field_revision_history.field.view', [
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'field_name' => $field_name,
        'langcode' => $entity->language()->getId(),
      ]);
      $link = [
        '#type' => 'link',
        '#title' => $this->t('View or restore'),
        '#url' => $url,
        '#attributes' => [
          'class' => [
            'use-ajax',
            'field-revision-history-ajax',
          ],
          'data-dialog-type' => 'modal',
          'data-dialog-url' => $url->toString(),
        ],
      ];

      $description = $this->t('@link a past value.', [
        '@link' => $this->renderer->render($link),
      ]);

      // Get variable $element as form field description.
      // For multi, references, typical fields need to get correct description option of field.
      $element = &$this->fieldRevisionHistoryElement->getElementWidget($form, $field_definition, $field_name);

      if (!empty($element['#description'])) {
        $element['#description'] .= '<br />' . $description;
      }
      else {
        $element['#description'] = $description;
      }
    }
  }

}
