<?php

namespace Drupal\watts_migrate\Plugin\MediaWysiwyg;

use Drupal\media_migration\MediaWysiwygPluginBase;
use Drupal\migrate\Row;

/**
 * FieldCollection Media WYSIWYG plugin.
 *
 * @MediaWysiwyg(
 *   id = "fieldable_panels_pane",
 *   label = @Translation("Fieldable Panels Pane"),
 *   description = @Translation("Fieldable Panels Pane plugin.")
 * )
 */
class FieldablePanelsPaneItem extends MediaWysiwygPluginBase {

  /**
   * {@inheritdoc}
   */
  public function process(array $migrations, Row $row, array $migration_plugin_ids = []) {
    foreach (['d7_fieldable_panels_pane',
      'd7_fieldable_panels_pane_revision',
    ] as $migration_id) {
      $fieldable_panels_pane = $row->getSourceProperty('bundle');
      $derived_migration = $migration_id . ':' . $fieldable_panels_pane;
      if (isset($migrations[$derived_migration])) {
        $migrations = $this->appendProcessor($migrations, $derived_migration, $row->getSourceProperty('field_name'));
      }
    }

    return $migrations;
  }

}
