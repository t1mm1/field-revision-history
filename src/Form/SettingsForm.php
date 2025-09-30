<?php

namespace Drupal\field_revision_history\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Node temporary settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The supported entity type.
   *
   * @var array
   */
  protected array $entity_types = [
    'node',
  ];

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['field_revision_history.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'field_revision_history_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * @throws PluginNotFoundException|InvalidPluginDefinitionException
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('field_revision_history.settings');

    $field_types_options = [
      'text_with_summary' => $this->t('Text with summary (Example: text with editor)'),
      'string' => $this->t('String (Example: texfield)'),
      'string_long' => $this->t('String long (Example: simple textarea)'),
      'image' => $this->t('Images'),
      'file' => $this->t('Files'),
      'link' => $this->t('Link'),
      'entity_reference' => $this->t('Entity reference'),
    ];

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General'),
      '#open' => TRUE,
      '#weight' => -1,
    ];

    $form['general']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#description' => $this->t('Enable field revision history service'),
      '#default_value' => $config->get('enabled'),
      '#return_value' => 1,
      '#empty' => 0,
    ];

    $form['entity_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Content type settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    foreach ($this->entity_types as $entity_type) {
      $definition = $this->entityTypeManager->getDefinition($entity_type);
      if ($definition->getBundleEntityType()) {
        $entity_types = $config->get('entity_types') ?: [];
        $bundles = $this->entityTypeManager
          ->getStorage($definition->getBundleEntityType())
          ->loadMultiple();

        $form['entity_types'][$entity_type] = [
          '#type' => 'container',
          '#title' => $definition->getLabel(),
        ];
        foreach ($bundles as $bundle) {
          $field_types_enabled = $entity_types[$entity_type][$bundle->id()]['enabled'] ?? 0;
          $field_types_default = $entity_types[$entity_type][$bundle->id()]['field_types'] ?? [];

          $form['entity_types'][$entity_type][$bundle->id()] = [
            '#type' => 'details',
            '#title' => $bundle->label(),
            '#open' => $field_types_enabled ?? FALSE,
          ];

          $form['entity_types'][$entity_type][$bundle->id()]['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable for %label', ['%label' => $bundle->label()]),
            '#default_value' => $field_types_enabled,
          ];

          $form['entity_types'][$entity_type][$bundle->id()]['field_types'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Supported field types'),
            '#options' => $field_types_options,
            '#default_value' => $field_types_default,
            '#description' => $this->t('Specify the field types for the "%type" content.', ['%type' => $bundle->label()]),
            '#states' => [
              'visible' => [
                ':input[name="entity_types[' . $entity_type . '][' . $bundle->id() . '][enabled]"]' => ['checked' => TRUE],
              ],
            ],
          ];
        }
      }
    }

    return parent::buildForm($form, $form_state) + $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->config('field_revision_history.settings')
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('entity_types', $form_state->getValue('entity_types'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
