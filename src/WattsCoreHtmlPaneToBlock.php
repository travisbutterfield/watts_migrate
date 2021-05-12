<?php

namespace Drupal\watts_migrate;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a block from an Watts_core_html pane.
 */
class WattsCoreHtmlPaneToBlock extends WattsPaneToBlock {
  use WattsMediaWysiwygTransformTrait;
  use WattsWysiwygTextProcessingTrait;

  /**
   * The drupal_7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Connection;

  /**
   * The entity type manager server.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a WattsPaneToBlock object.
   *
   * @param Drupal\Core\Database\Connection $database
   *   The injected database service.
   * @param Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(Connection $database, EntityTypeManager $entity_type_manager) {
    $this->d7Connection = $database;
    $this->entityTypeManager = $entity_type_manager;
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

    $body_field = $configuration['html_body'];

    // Extract media that uses the block_header view mode.
    $split_content = $this->extractBlockHeader($body_field['value']);

    // Convert D7 media to D8 media.
    $body_field['value'] = $this->transformWysiwyg($split_content['wysiwyg_content'], $this->entityTypeManager);

    // Perform text processing to update/remove inline code.
    $body_field['value'] = $this->processText($body_field['value']);

    // Get box style from the settings array if it's there. Otherwise, use the
    // value in configuration.
    $style = unserialize($record['style']);

    // Create an html block.
    return $this->createHtmlBlock($body_field, $this->entityTypeManager);
  }

}
