<?php

namespace Drupal\watts_migrate;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create blocks from a fieldable_panels_pane (fpp) pane.
 */
class WattsFieldablePanelsPaneToBlock extends WattsPaneToBlock {
  use WattsMediaWysiwygTransformTrait;
  use WattsWysiwygTextProcessingTrait;

  /**
   * The drupal_7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs an WattsPaneToBlock object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The injected database service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity_type manager.
   */
  public function __construct(Connection $database, EntityTypeManager $entityTypeManager) {
    $this->d7Connection = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('watts_migrate.watts_pane_to_block'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function createBlock($row, $record, $configuration) {
    // Extract either the fpid or vid. Data is stored as 'fpid:%' or 'vid:%'.
    [$id_type, $id] = explode(':', $record['subtype'], 2);

    if (isset($id_type) && isset($id)) {
      switch ($id_type) {
        case 'fpid':
          // If we are working with a 'fpid' then the pane is reusable. Let's
          // get the vid for this fpid from the current revision table to ensure
          // we're getting data for the current version of the reusable pane.
          $fpid = $id;
          $vid = array_pop($this->d7Connection->select('fieldable_panels_panes', 'fpp')
            ->fields('fpp', ['vid'])
            ->condition('fpp.fpid', $id)
            ->execute()
            ->fetchCol());
          break;

        case 'vid':
          // If we are working with a 'vid' then the pane is not reusable. Let's
          // get the fpid for this vid from the revision table.
          $fpid = array_pop($this->d7Connection->select('fieldable_panels_panes_revision', 'fppr')
            ->fields('fppr', ['fpid'])
            ->condition('fppr.vid', $id)
            ->execute()
            ->fetchCol());
          $vid = $id;
          break;
      }

      // Get the pane.
      $pane = $this->d7Connection->select('fieldable_panels_panes', 'fpp')
        ->fields('fpp')
        ->condition('fpp.fpid', $fpid, '=')
        ->execute()
        ->fetchObject();

      $pane_revision = $this->d7Connection->select('fieldable_panels_panes_revision', 'fppr')
        ->fields('fppr')
        ->condition('fppr.vid', $vid, '=')
        ->execute()
        ->fetchObject();
      // Determine if this is a reusable pane and whether it already exists.
      if ($pane->reusable) {
        $label = $fpid . ': ';
        // Set a default label of fpid if there is no admin_title or title.
        $label .= $pane->admin_title ?: $pane_revision->title;
      }

      // Either this pane is not reusable or no library item block exists
      // for this item, yet.
      // Extract values that apply to all panes.
      // Get box style from the settings array if it's there. Otherwise, use the
      // value in configuration.
      $style = unserialize($record['style']);

      $title = $pane_revision->title;

      $block = NULL;

      // Process each pane type.
      // Since we ensured we have the correct vid for this pane, we can use the
      // revision data tables to get field data.
      switch ($pane->bundle) {
        case 'fieldable_panels_pane':
          $body_field_query = $this->d7Connection->select('field_revision_field_watts_fpp_body', 'fpp_body')
            ->condition('fpp_body.revision_id', $vid, '=');
          $body_field_query->addField('fpp_body', 'field_watts_fpp_body_value', 'value');
          $body_field_query->addField('fpp_body', 'field_watts_fpp_body_format', 'format');

          // Transform body field content.
          $body_field = $body_field_query->execute()->fetchAssoc();

          if (isset($body_field['value'])) {
            $body_field['value'] = $this->transformWysiwyg($body_field['value'], $this->entityTypeManager);

            // Perform text processing to update/remove inline code.
            $body_field['value'] = $this->processText($body_field['value']);

            $block = $this->createHtmlBlock($body_field);
          }
          break;

        default:
          break;
      }

      // If this pane is reusable, create a custom block from the data that
      // was created for this pane.
      if ($pane->reusable) {
        /*$library_item = $this->createBlockLibraryItem($label,
        $block, $this->entityTypeManager);
        return $this->createFromLibraryBlock($library_item,
        $this->entityTypeManager);*/
      }

      return $block;
    }
  }

}
