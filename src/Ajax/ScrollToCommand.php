<?php

namespace Drupal\field_revision_history\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Construct of ScrollToCommand ajax command.
 */
class ScrollToCommand implements CommandInterface {

  /**
   * Element ID.
   *
   * @var string
   */
  protected string $element_id;

  /**
   * Options array.
   *
   * @var array
   */
  protected array $options;

  /**
   * Construct of ScrollToCommand.
   *
   * @param string $element_id
   *   Element ID.
   * @param array $options
   *   Options array.
   */
  public function __construct(string $element_id, array $options = []) {
    $this->element_id = $element_id;
    $this->options = $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'scrollTo',
      'element_id' => $this->element_id,
      'options' => $this->options,
    ];
  }

}
