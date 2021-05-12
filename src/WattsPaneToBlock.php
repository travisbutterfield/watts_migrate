<?php

namespace Drupal\watts_migrate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create an HTML block from a node_content pane.
 */
class WattsPaneToBlock implements WattsPaneToBlockInterface, ContainerInjectionInterface {

  use WattsBoxWrapperTrait;
  use WattsCreateBlocksTrait;
  use WattsblocksLibraryTrait;

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
   * Constructs an WattsPaneToBlock object.
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
    // Child classes will need to implement the transformation logic.
    return NULL;
  }

}
