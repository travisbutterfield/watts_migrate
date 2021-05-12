<?php

namespace Drupal\watts_migrate;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateExecutableInterface;

/**
 * Helpers to transform blocks to Panes in a process plugin.
 */
trait WattsTransformBlockTrait {

  /**
   * Create blocks from pane.
   *
   * @param array $pane
   *   The pane to transform.
   * @param \Drupal\migrate\Row $row
   *   The row that is being processed.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   *
   * @return array
   *   The transformed block ids.
   */
  protected function transformBlocks(array $pane, Row $row, MigrateExecutableInterface $migrate_executable) {
    $blocks = [];

    $type = $pane['type'];

    // The configuration (box style) we need for fieldable panels panes is
    // stored in the 'style' column. For all other panes we need to pull
    // it from configuration.
    $type == 'fieldable_panels_pane' ?
      $configuration = unserialize($pane['style']) :
      $configuration = unserialize($pane['configuration']);

    $pane_transformer_services = [
      'node_content' => 'WattsNodeContentTransformer',
      'watts_core_html_pane' => 'WattsCoreHtmlPaneTransformer',
      'watts_core_node_list_pane' => 'WattsCoreListPaneTransformer',
      'watts_core_link_list_pane' => 'WattsCoreListPaneTransformer',
      'fieldable_panels_pane' => 'WattsFieldablePanelsPaneTransformer',
    ];

    $pane_transformer_service = $pane_transformer_services[$type] ?? NULL;

    if ($pane_transformer_service) {
      $transformed_blocks = $this->$pane_transformer_service->createblock($row, $pane, $configuration);

      if ($transformed_blocks) {
        // Convert transformed_blocks to an array if it's not already.
        $transformed_blocks = is_array($transformed_blocks) ?: [$transformed_blocks];
        foreach ($transformed_blocks as $block) {
          $blocks[] = $block;
        }

      }
    }
    else {
      $migrate_executable->saveMessage("WARNING: No pane transformer was found for pane type '{$type}' with pid {$pane['pid']}. This pane is used in the '{$pane['panel']}' panel. This pane was not migrated.", 3);
    }

    return $blocks;
  }

}
