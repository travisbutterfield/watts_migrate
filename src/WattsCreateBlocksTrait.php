<?php

namespace Drupal\watts_migrate;

use Drupal\Core\Block;

/**
 * Helpers to create Blocks.
 */
trait WattsCreateBlocksTrait {

  /**
   * Create html block.
   *
   * @param array $body_field
   *   An array with 'value' and (optional) 'format' keys.
   *
   * @return block
   *   The saved html block.
   */
  public function createHtmlBlock(array $body_field) {
    $html_block = Block::create(['type' => 'html']);
    $html_block->set('field_body', $body_field);
    $html_block->isNew();
    $html_block->save();

    return $html_block;
  }

}
