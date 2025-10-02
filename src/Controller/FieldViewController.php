<?php

namespace Drupal\field_revision_history\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\field_revision_history\FieldRevisionHistoryHelper;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FieldViewController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The renderer service.
   *
   * @var RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The field revision history helper service.
   *
   * @var FieldRevisionHistoryHelper
   */
  protected FieldRevisionHistoryHelper $fieldRevisionHistoryHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    RendererInterface $renderer,
    FieldRevisionHistoryHelper $field_revision_history_helper,
  ) {
    $this->renderer = $renderer;
    $this->fieldRevisionHistoryHelper = $field_revision_history_helper;
  }

  /**
   * Function for field view.
   *
   * @param string $entity_type
   *   Entity type.
   * @param int $entity_id
   *   Entity id.
   *
   * @return AccessResult
   * @throws EntityMalformedException
   */
  public function access($entity_id, $entity_type): AccessResult {
    $entity = $this->entityTypeManager()
      ->getStorage($entity_type)
      ->load($entity_id);

    if (!$entity || !$entity->access('view revision')) {
      return AccessResult::forbidden()->cachePerUser();
    }

    return AccessResult::allowed()->cachePerUser();
  }

  /**
   * Function for field view.
   *
   * @param string $entity_type
   *   Entity type.
   * @param int $entity_id
   *   Entity id.
   * @param string $field_name
   *   Machine field name.
   * @param string $langcode
   *   Langcode of current entity.
   *
   * @return AjaxResponse
   * @throws EntityMalformedException
   */
  public function fieldView(
    int $entity_id,
    string $entity_type,
    string $field_name,
    string $langcode,
  ): AjaxResponse {
    try {
      $entity = $this->entityTypeManager()->getStorage($entity_type)->load($entity_id);
    }
    catch (\Exception $e) {
      $this->getLogger(self::class)->error($e);
      throw new NotFoundHttpException();
    }

    // Build content.
    $render = $this->getTableContent($entity, $field_name, $langcode);

    // Response.
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(
      $this->t('Field revision history of %field_label', [
        '%field_label' => $this->fieldRevisionHistoryHelper->getFieldReadableName($entity, $field_name, $langcode),
      ]),
      $this->renderer->renderRoot($render),
      [
        'dialogClass' => 'field-revision-history-modal',
      ]
    ));
    $response->setAttachments($render['#attached']);

    return $response;
  }

  /**
   * Help function for getting table content.
   *
   * @param EntityInterface|NodeInterface $entity
   *   The source entity.
   * @param string $field_name
   *   Machine field name.
   * @param string $langcode
   *   Langcode of current entity.
   *
   * @return array
   * @throws EntityMalformedException
   */
  public function getTableContent(
    EntityInterface|NodeInterface $entity,
    string $field_name,
    string $langcode,
  ): array {
    $rows = [];

    try {
      $entity_storage = $this->entityTypeManager()->getStorage($entity->getEntityTypeId());
      $revisions = $entity_storage->revisionIds($entity);
    }
    catch (\Exception $e) {
      $this->getLogger(self::class)->error($e);
      throw new NotFoundHttpException();
    }

    $previous_field_value = FALSE;
    $unchanged_field_value = FALSE;
    if (!empty($revisions)) {
      foreach ($revisions as $revision_id) {
        $current_field_unchanged = FALSE;
        $revision = $entity_storage->loadRevision($revision_id);

        // Get translation.
        if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
          $revision = $revision->getTranslation($langcode);
        }

        $current_field_value = $this->fieldRevisionHistoryHelper->getFieldValue($revision, $field_name);
        if ($previous_field_value && json_encode($previous_field_value) == json_encode($current_field_value)) {
          $field_display = $this->fieldRevisionHistoryHelper->getPreviousValue();
          $current_field_unchanged = TRUE;
          if (!$revision->isDefaultRevision()) {
            $unchanged_field_value = TRUE;
          }
        }
        else {
          $field_display = $current_field_value;
        }
        $previous_field_value = $current_field_value;

        // For current version show values all the time.
        if ($revision->isDefaultRevision()) {
          $field_display = $current_field_value;
        }

        $row = [
          [
            'data' => [
              'date' => $this->fieldRevisionHistoryHelper->getRevisionDate($revision, $entity, reset($revisions) == $revision->getRevisionId()),
              'status' => $this->fieldRevisionHistoryHelper->getRevisionStatusMessage($revision),
              'message' => $this->fieldRevisionHistoryHelper->getRevisionLogMessage($revision),
              'value' => $field_display,
            ],
            'class' => [
              'field-revision-history-value',
            ],
          ],
        ];

        $row[] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $this->fieldRevisionHistoryHelper->getRevisionLinks($revision, $entity, $field_name, $langcode, $revision->isDefaultRevision()),
          ],
          'class' => [
            'field-revision-history-operations',
          ],
        ];

        // Add data into rows.
        $rows[] = [
          'data' => $row,
          'class' => [
            $revision->isDefaultRevision() ? 'field-revision-history-current' : '',
            !$revision->isDefaultRevision() && $current_field_unchanged ? 'field-revision-history-unchanged' : '',
          ],
        ];
      }
    }

    // Create a link to open hidden values in the list.
    $link = [
      '#type' => 'link',
      '#title' => $this->t('Expand hidden values'),
      '#url' => Url::fromUserInput('#'),
      '#attributes' => [
        'class' => ['field-revision-history-link'],
      ],
    ];
    $caption = $this->renderer->renderRoot($link);

    // We need to reverse array to show last revision as first element.
    $rows = array_reverse($rows);

    // Return render of content.
    return [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => [
        $this->t('Values'),
        $this->t('Operations'),
      ],
      '#caption' => (count($rows) > 1 && $unchanged_field_value) ? $caption : '',
      '#attributes' => [
        'class' => [
          'field-revision-history-table',
        ],
      ],
      '#attached' => [
        'library' => [
          'field_revision_history/table',
        ],
      ],
      '#cache' => [
        'tags' => $entity->getCacheTags(),
        'max-age' => $entity->getCacheMaxAge(),
      ],
    ];
  }

}
