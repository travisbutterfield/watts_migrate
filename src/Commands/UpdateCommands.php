<?php

namespace Drupal\watts_migrate\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://git.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://git.drupalcode.org/devel/tree/drush.services.yml
 */
class UpdateCommands extends DrushCommands {

  /**
   * The key value store to use.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueStore;

  /**
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value store to use.
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory) {
    $this->keyValueStore = $key_value_factory;
  }

  /**
   * Corrects a field storage configuration. See https://www.drupal.org/project/drupal/issues/2916266 for more info
   *
   * @command update:correct-field-config-storage
   *
   * @param string $entity_type
   *   Entity type
   * @param string $bundle
   *   Bundle name
   * @param string $field_name
   *   Field name
   */
  public function correctFieldStorageConfig($entity_type, $bundle, $field_name) {
    $field_map_kv_store = $this->keyValueStore->get('entity.definitions.bundle_field_map');
    $map = $field_map_kv_store->get($entity_type);
    unset($map[$field_name]['bundles'][$bundle]);
    $field_map_kv_store->set($entity_type, $map);
  }
}