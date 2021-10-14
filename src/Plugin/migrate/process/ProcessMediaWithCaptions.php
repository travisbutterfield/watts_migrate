<?php

namespace Drupal\watts_migrate\Plugin\migrate\process;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\watts_migrate\WattsMediaWysiwygTransformTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Convert media and captions to D9.
 *
 * @MigrateProcessPlugin(
 *   id = "process_media_with_captions"
 * )
 *
 * Usage:
 *
 * To convert media entities to d9 and filter d7 image captions:
 *
 * @code
 * process:
 *   -
 *     plugin: process_media_with_captions
 * @endcode
 */
class ProcessMediaWithCaptions extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  use WattsMediaWysiwygTransformTrait;
  /**
   * Uuid generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\ContentEntityBase
   */
  protected $contentEntityBase;

  /**
   * The storage for the configured entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\Drupal\Core\Entity\RevisionableStorageInterface
   */
  protected $entityStorage;

  /**
   * Constructs a ProcessMediaWithCaptions plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid generator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UuidInterface $uuid, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->uuid = $uuid;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uuid'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = $this->transformWysiwyg($value, $this->entityTypeManager);
    $value_is_array = is_array($value);
    $text = (string) ($value_is_array ? $value['value'] : $value);

    if ($value_is_array) {
      $value['value'] = $text;
    }
    else {
      $value = $text;
    }
    return $value;
  }

}
