<?php

namespace Drupal\watts_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\migrate\Row;
use Drupal\watts_migrate\GetFppDataTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The 'watts_panelizer_node' source plugin.
 *
 * @MigrateSource(
 *   id = "watts_panelizer_node",
 *   source_module="node"
 * )
 */
class WattsPanelizerNode extends Node {
  use GetFppDataTrait;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The drupal_7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Connection;

  /**
   * Constructs an WattsPanelizerNode source plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Database\Connection $d7_database
   *   The drupal_7 database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, Connection $d7_database, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_type_manager, $module_handler);
    $this->d7Connection = $d7_database;
    $this->logger = $logger_factory->get('watts_migrate');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('watts_migrate.d7_database'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Get the default Node query.
    $query = parent::query();
    $query->innerJoin('panelizer_entity', 'pe', 'n.vid = pe.revision_id AND pe.entity_id = n.nid AND pe.entity_type = :type', [':type' => 'node']);
    $query->innerJoin('panels_display', 'pd', 'pe.did = pd.did');
    $query->fields('n', ['title', 'nid', 'vid'])
      ->fields('pe', ['did'])
      ->fields('pd', ['layout']);
    $query->condition('pe.did', 0, '<>');
    // Sometimes it is easiest to test just one node.
    // Uncomment the next line and adjust for the desired nid.
    $query->condition('n.nid', 107);
    return $query;
  }

  /**
   * {@inheritDoc}
   */
  public function prepareRow(Row $row) {
    // Always include this fragment at the beginning of every prepareRow()
    // implementation, so parent classes can ignore rows.
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;
    }

    // To prepare rows for import into fields, we're going to:
    // - Add a 'layout' source property for use during processing.
    // - Add a 'panes' source property containing query results for all panes.
    //
    // First, initialize the 'layout' source property as NULL so we can properly
    // process nodes that do not have a record in the 'panelizer_entity' table.
    $row->setSourceProperty('layout', NULL);

    // Get the Display ID for the current revision.
    $did = $this->select('panelizer_entity', 'pe')
      ->fields('pe', ['did'])
      ->condition('pe.revision_id', $row->getSourceProperty('vid'))
      ->condition('pe.entity_id', $row->getSourceProperty('nid'))
      ->condition('pe.entity_type', 'node')
      ->condition('pe.did', 0, '<>')
      ->execute()
      ->fetchField();

    if ($did) {
      // Get the Panelizer layout for this display.
      $layout = $this->select('panels_display', 'pd')
        ->fields('pd', ['layout'])
        ->condition('pd.did', $did)
        ->execute()
        ->fetchField();

      // Update the 'layout' property to its actual value.
      $row->setSourceProperty('layout', $layout);

      // Fetch the panes and add the result as a source property.
      $panes = $this->select('panels_pane', 'pp')
        ->fields('pp')
        ->condition('pp.did', $did)
        ->orderBy('pp.position')
        ->orderBy('pp.panel')
        ->execute()
        ->fetchAll();
      $row->setSourceProperty('panes', $panes);

      $i = 0;
      foreach ($panes as $pane) {
        // Get data for Fieldable Panels Panes.
        if ($pane['type'] === 'fieldable_panels_pane') {
          $this->getFppData($pane, $row, $i);
        }
        $i++;
      }
    }

    return parent::prepareRow($row);
  }

}
