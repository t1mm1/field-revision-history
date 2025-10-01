<?php

namespace Drupal\field_revision_history;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Represents a Settings service.
 */
class FieldRevisionHistorySettings {

  /**
   * Fields list of system fields array.
   */
  public array $available_system_fields = [
    'title',
  ];

  /**
   * Fields list of system fields array.
   */
  public array $unavailable_system_fields = [
    'comment',
  ];

  /**
   * Module settings.
   */
  public ImmutableConfig $settings;

  /**
   * The config service.
   *
   * @var ConfigFactoryInterface
   *   The config service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new object.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
  ) {
    $this->configFactory = $config_factory;
    $this->settings = $this->configFactory->get('field_revision_history.settings');
  }

  /**
   * Help function to check is the service enabled or not.
   *
   * @return bool
   *   Enabled or not.
   */
  public function isServiceEnabled(): bool {
    $enabled = $this->settings->get('enabled') ?? FALSE;
    if (!$enabled) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Help function to check is the current field enabled or not.
   *
   * @param EntityInterface $entity
   *   The source entity.
   * @param FieldDefinitionInterface $field_definition
   *   The source entity.
   *
   * @return bool
   *   Enabled or not.
   */
  public function isEntityFieldEnabled(EntityInterface $entity, FieldDefinitionInterface $field_definition): bool {
    $entity_types = $this->settings->get('entity_types') ?? FALSE;
    $enabled = $entity_types[$entity->getEntityTypeId()][$entity->bundle()]['enabled'] ?? FALSE;
    if (!$enabled) {
      return FALSE;
    }

    $field_name = $field_definition->getName();
    if ($field_definition->getFieldStorageDefinition()->isBaseField() &&
      !in_array($field_name, $this->available_system_fields)) {
      return FALSE;
    }

    // Skip unavailable fields.
    if (in_array($field_name, $this->unavailable_system_fields)) {
      return FALSE;
    }

    // Check field.
    $entity_types = $this->settings->get('entity_types') ?? FALSE;
    if (!in_array($field_name, $entity_types[$entity->getEntityTypeId()][$entity->bundle()]['fields'])) {
      return FALSE;
    }

    return TRUE;
  }

}
