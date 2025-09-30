<?php

namespace Drupal\field_revision_history;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Represents a Helper service.
 */
class FieldRevisionHistoryHelper {

  use StringTranslationTrait;

  /**
   * The date formatter service.
   *
   * @var DateFormatter
   */
  protected DateFormatter $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The translation manager.
   *
   * @var TranslationInterface
   */
  protected $stringTranslation;

  /**
   * Constructs a new object.
   *
   * @param DateFormatter $date_formatter
   *   The date formatter service.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param AccountInterface $current_user
   *   The current user manager.
   * @param TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    DateFormatter $date_formatter,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    TranslationInterface $string_translation,
  ) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Help function for getting field readable name.
   *
   * @param EntityInterface $entity
   *   The source entity.
   * @param string $field_name
   *   The machine field name.
   * @param string $langcode
   *   Langcode of current entity.
   *
   * @return string
   *   The field name.
   */
  public function getFieldReadableName(EntityInterface $entity, string $field_name, string $langcode): string {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    if (!isset($fields[$field_name])) {
      return $field_name;
    }

    $label = (string) $fields[$field_name]->getLabel();
    return $this->stringTranslation->translate($label, [], [
      'langcode' => $langcode,
    ]);
  }

  /**
   * Help function for getting revision date and user.
   *
   * @param RevisionableInterface $revision
   *   The revision entity.
   * @param EntityInterface $entity
   *   The source entity.
   * @param bool $created
   *   The value on entity created.
   *
   * @return array
   *   The render array.
   */
  public function getRevisionDate(RevisionableInterface $revision, EntityInterface $entity, bool $created = FALSE): array {
    $user = $revision->getRevisionUser();

    if ($user) {
      $account = $user->toLink($user->getDisplayName())->toString();
    }
    else {
      $account = $this->t('[User was removed]');
    }

    $date = $this->dateFormatter->format($revision->get('revision_timestamp')->value, 'short');

    if ($revision->isDefaultRevision()) {
      $message = $this->t('@action on @date by @user', [
        '@action' => $created ? 'Created' : 'Updated',
        '@date' => $date,
        '@user' => $account,
      ]);
    }
    else {
      $link = Link::fromTextAndUrl(
        $this->t('@action on @date', [
          '@action' => $created ? 'Created' : 'Updated',
          '@date' => $date,
        ]),
        $this->getUrlEntityView($revision, $entity),
      )->toString();

      $message = $this->t('@link by @user', [
        '@link' => $link,
        '@user' => $account,
      ]);
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['field-revision-history-date'],
      ],
      '#value' => $message,
    ];
  }

  /**
   * Help function for getting current revision message.
   *
   * @param RevisionableInterface $revision
   *   The revision entity.
   *
   * @return array
   *   The render array.
   */
  public function getRevisionStatusMessage(RevisionableInterface $revision): array {
    if ($revision->isDefaultRevision()) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['field-revision-history-message'],
        ],
        '#value' => $this->t('Current revision'),
      ];
    }

    // We can get situation for Workflow when revision was created but was not published yet.
    // In this case need to add information that current field value is the latest revision version.
    if ($revision->isLatestRevision()) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['field-revision-history-message'],
        ],
        '#value' => $this->t('This is previous version of revision.'),
      ];
    }

    return [];
  }

  /**
   * Help function for getting revision log message.
   *
   * @param RevisionableInterface $revision
   *   The revision entity.
   *
   * @return array
   *   The render array.
   */
  public function getRevisionLogMessage(RevisionableInterface $revision): array {
    $message = $revision->getRevisionLogMessage();
    if (!$message) {
      return [];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['field-revision-history-message'],
      ],
      '#value' => $message,
    ];
  }

  /**
   * Help function for getting revision links.
   *
   * @param RevisionableInterface $revision
   *   The revision entity.
   * @param EntityInterface $entity
   *   The source entity.
   * @param string $field_name
   *   The machine field name.
   * @param string $langcode
   *   The langcode.
   * @param bool $current
   *   The mark of current value.
   *
   * @return array
   *   The array of links.
   */
  public function getRevisionLinks(RevisionableInterface $revision, EntityInterface $entity, string $field_name, string $langcode, bool $current): array {
    $links = [];

    if ($revision->access('revert revision') || $current) {
      $links['revert_field'] = [
        'type' => 'link',
        'title' => $this->t('Restore value'),
        'url' => Url::fromUserInput('#'),
        'attributes' => [
          'class' => ['js-field-revision-history-apply-revision'],
          'data-field-revision-history' => Json::encode([
            'entity_type' => $entity->getEntityTypeId(),
            'entity_id' => $entity->id(),
            'field_name' => $field_name,
            'revision_id' => $revision->getRevisionId(),
            'langcode' => $langcode,
          ]),
        ],
      ];
    }

    if ($revision->access('view revision')) {
      $links['view'] = [
        'title' => $this->t('Entity view'),
        'url' => $this->getUrlEntityView($revision, $entity),
        'attributes' => [
          'target' => '_blank',
        ],
      ];
    }

    if ($revision->access('revert revision')) {
      $links['revert_entity'] = [
        'title' => $this->t('Entity revert'),
        'url' => $this->getUrlEntityRevert($revision, $entity),
        'attributes' => [
          'target' => '_blank',
        ],
      ];
    }

    return $links;
  }

  /**
   * Help function for getting revision log message.
   *
   * @param RevisionableInterface $revision
   *   The revision entity.
   * @param string $field_name
   *   The machine field name.
   *
   * @return array
   *   The render array.
   */
  public function getFieldValue(RevisionableInterface $revision, string $field_name): array {
    $field = $revision->get($field_name);

    if ($field->isEmpty()) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['field-revision-history-empty'],
        ],
        '#value' => $this->t('Field value has been set as empty.'),
      ];
    }

    $field_definitions = $revision->getFieldDefinitions();
    $field_type = isset($field_definitions[$field_name]) ? $field_definitions[$field_name]->getType() : '';

    $options = [
      'label' => 'hidden',
    ];
    // Change the image display to use an image style (we do not need to show the original size, as it may be too large).
    if ($field_type === 'image') {
      $options['settings'] = [
        'image_style' => 'medium',
        'image_link' => '',
      ];
    }

    return $field->view($options);
  }

  /**
   * Help function for getting previous value message.
   *
   * @return array
   *   The render array.
   */
  public function getPreviousValue(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['field-revision-history-empty'],
      ],
      '#value' => $this->t('The field value has not changed.<br />See the value in the previous revision.'),
    ];
  }

  /**
   * Help function for getting review link.
   *
   * @param RevisionableInterface $revision
   *   The revision entity.
   * @param EntityInterface $entity
   *   The source entity.
   *
   * @return Url
   *   The url object.
   */
  public function getUrlEntityView(RevisionableInterface $revision, EntityInterface $entity): Url {
    $options = [
      'node' => $entity->id(),
      'node_revision' => $revision->getRevisionId(),
    ];

    if (count($entity->getTranslationLanguages()) > 1) {
      $options['langcode'] = $entity->language()->getId();
    }

    return Url::fromRoute('entity.node.revision', $options);
  }

  /**
   * Help function for getting revert link.
   *
   * @param RevisionableInterface $revision
   *   The revision entity.
   * @param EntityInterface $entity
   *   The source entity.
   *
   * @return Url
   *   The url object.
   */
  public function getUrlEntityRevert(RevisionableInterface $revision, EntityInterface $entity): Url {
    $route = 'node.revision_revert_confirm';
    $options = [
      'node' => $entity->id(),
      'node_revision' => $revision->getRevisionId(),
    ];

    if (count($entity->getTranslationLanguages()) > 1) {
      $route = 'node.revision_revert_translation_confirm';
      $options['langcode'] = $entity->language()->getId();
    }

    return Url::fromRoute($route, $options);
  }

}
