<?php

namespace Drupal\watts_migrate;

use Drupal\migrate\Row;

/**
 * An interface for creating a block from Pane data.
 */
interface WattsPaneToBlockInterface {

  /**
   * Create a block entity from a Pane DB record.
   *
   * @param \Drupal\migrate\Row $row
   *   The current row being processed.
   * @param array $record
   *   The pane record from the Drupal 7 DB.
   * @param object $configuration
   *   The unserialized configuration object from the record.
   *
   * @return \Drupal\blocks\Entity\block[]|\Drupal\blocks\Entity\block|null
   *   A block, an array of blocks, or null.
   */
  public function createblock(Row $row, array $record, object $configuration);

}
