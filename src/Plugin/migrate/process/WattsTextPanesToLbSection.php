<?php

namespace Drupal\watts_migrate\Plugin\migrate\process;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\watts_migrate\WattsMediaWysiwygTransformTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Given a set of panes, returns a layout builder section.
 *
 * @MigrateProcessPlugin(
 *   id = "watts_text_panes_to_lb_section"
 * )
 *
 * Usage:
 *
 * To convert an array of panes to blocks laid out in a layout builder
 * section, do the following:
 *
 * @code
 * layout_builder__layout:
 *   -
 *     plugin: skip_on_empty
 *     method: process
 *     source: panes
 *   -
 *     plugin: single_value
 *   -
 *     plugin: watts_text_panes_to_lb_section
 *   -
 *     plugin: multiple_values
 * @endcode
 */
class WattsTextPanesToLbSection extends ProcessPluginBase implements ContainerFactoryPluginInterface {
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
   * Constructs a WattsPanesToLbSection plugin.
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
          $container->get('entity_type.manager')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $layout = $row->getSourceProperty('layout');
    $layout = $layout === 'beast' ? 'radix_beast' : $layout;
    $layout = $layout === 'beast2' ? 'radix_beast' : $layout;
    $rowconfig = [];
    $rowconfig['layout'] = $layout;
    $rowconfig['nid'] = $row->getSourceProperty('nid');
    $rowconfig['value'] = $row->getSourceProperty('panes');
    $rowconfig['nodetype'] = $row->getSourceProperty('type');

    // Create blocks from each pane and wrap them in
    // SectionComponents to be assigned to the overall Section.
    $section = new Section($layout);

    $i = 0;
    foreach ($value as $pane) {
      $origconfig = $pane['configuration'];
      $origconfig = unserialize($origconfig);
      $paneconfig = [];
      $paneconfig['origconfig'] = $origconfig;
      $paneconfig['shown'] = $pane['shown'];
      $paneconfig['region'] = $pane['panel'];
      $paneconfig['bundle'] = $pane['bundle'];
      $paneconfig['type'] = $pane['type'];
      $paneconfig['subtype'] = $pane['subtype'];

      if ($paneconfig['type'] === 'fieldable_panels_pane') {

        // $fpptypes = ["asu_spotlight", "banner", "banners_ws2", "basic_file",
        // "fieldable_panels_pane", "hero", "image", "jumbohero", "map",
        // "quick_links", "table", "text", "uto_carousel", "video"];
        if ($paneconfig['bundle'] === "text" && $paneconfig['shown']) {
          $text = $pane['text_fpp']['0']->field_basic_text_text_value;
          // Convert D7 media to D8 media.
          $text = $this->transformWysiwyg($text, $this->entityTypeManager);
          $paneconfig['title'] = $pane['text_fpp']['0']->title;
          $paneconfig['text'] = $text;
          $paneconfig['maketitle'] = substr($text, 0, 20);
          $paneconfig['titletest'] = $paneconfig['title'] ?? $paneconfig['maketitle'] . '...';
          $paneconfig['textformat'] = $pane['text_fpp']['0']->field_basic_text_text_format ?: 'full_html';

          $component = $this->buildSectionComponent($rowconfig, $paneconfig);
          $section->appendComponent($component);
        }
        else {
          // TODO: Add ways to look up other fpp types, not just text.
          continue;
        }
      }
      elseif ($paneconfig['type'] === 'entity_field') {
        [$paneconfig['entity'], $paneconfig['field']] = explode(':',
          $paneconfig['subtype'], 2);
        $component = $this->buildSectionComponent($rowconfig, $paneconfig);
        if ($component) {
          $section->appendComponent($component);
        }
      }
      elseif ($paneconfig['type'] === 'menu_tree' || $paneconfig['type'] === 'node_title') {
        $component = $this->buildSectionComponent($rowconfig, $paneconfig);
        $section->appendComponent($component);
      }
      $i++;
      unset($paneconfig);
    }
    unset($rowconfig);
    return $section;
  }

  /**
   * Build a Section Component.
   *
   * @param array $rowconfig
   *   An array of config items from the row.
   * @param array $paneconfig
   *   An array of config items from the pane.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   The Section Component placed as a block in layout builder.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function buildSectionComponent(array $rowconfig, array $paneconfig): ?SectionComponent {
    $component = NULL;
    switch ($paneconfig['type']) {
      case 'fieldable_panels_pane':
        if ($paneconfig['bundle'] === 'text') {
          $block = $this->entityTypeManager->getStorage('block_content')
            ->create([
              'reusable' => 0,
              'info' => $paneconfig['titletest'],
              'type' => $paneconfig['bundle'],
              'field_basic_text_text' => [
                // These values come from the configuration of the panel pane.
                'value' => $paneconfig['text'],
                'format' => $paneconfig['textformat'],
              ],
            ]);
          // Create Block embedded in a Section Component. Passing a serialized
          // Block entity is the key to making this work.
          $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
            'id' => 'inline_block:text',
            'label' => $paneconfig['titletest'],
            'label_display' => isset($paneconfig['title']),
            'block_serialized' => serialize($block),
            'context_mapping' => [],
          ]);
        }
        break;

      case  'node_body':
      case 'entity_field':
        if ($paneconfig['field'] === 'body' || $paneconfig['subtype'] === 'node_body') {
          $node = $this->entityTypeManager->getStorage('node')->load($rowconfig['nid']);
          // Returns false if the field doesn't exist.
          $exists = !empty($node->get('body')->value);
          if ($exists) {
            $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
              'id' => 'field_block:node:' . $rowconfig['nodetype'] . ':body',
              'label' => $paneconfig['titletest'],
              'provider' => 'layout_builder',
              'label_display' => 0,
              'formatter' => [
                'label' => 'hidden',
                'type' => 'text_default',
                'settings' => [],
              ],
              'context_mapping' => [
                'entity' => 'layout_builder.entity',
                'view_mode' => 'view_mode',
              ],
            ]);
          }
        }
        if ($paneconfig['field'] === 'field_featured_image') {
          $node = $this->entityTypeManager->getStorage('node')->load($rowconfig['nid']);
          // Returns false if the field doesn't exist.
          $exists = !empty($node->get('field_featured_image')->value);
          if ($exists) {
            $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
              'id' => 'field_block:node:' . $rowconfig['nodetype'] . ':' . 'field_featured_image',
              'label' => "Primary Image",
              'provider' => 'layout_builder',
              'label_display' => 0,
              'formatter' => [
                'label' => 'hidden',
                'type' => 'entity_reference_entity_view',
                'settings' => [
                  'view_mode' => 'default',
                ],
              ],
              'third_party_settings' => [],
              'context_mapping' => [
                'entity' => 'layout_builder.entity',
                'view_mode' => 'view_mode',
              ],
            ]);
          }
          unset($exists);
        }
        if ($paneconfig['field'] === 'field_featured_categories') {
          $node = $this->entityTypeManager->getStorage('node')
            ->load($rowconfig['nid']);
          // Returns false if the field doesn't exist.
          $exists = !empty($node->get('field_featured_categories')->value);
          if ($exists) {
            $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
              'id' => 'field_block:node:' . $rowconfig['nodetype'] . ':' . 'field_featured_categories',
              'label' => "Categories",
              'provider' => 'layout_builder',
              'label_display' => 0,
              'formatter' => [
                'label' => 'above',
                'type' => 'entity_reference_label',
                'settings' => [
                  'link' => 1,
                ],
                'third_party_settings' => [],
              ],
              'context_mapping' => [
                'entity' => 'layout_builder.entity',
                'view_mode' => 'view_mode',
              ],
            ]);
          }
          unset($exists);
        }
        break;
      case 'menu_tree':
        $subtype = $paneconfig['subtype'] === 'main-menu' ? 'main' : $paneconfig['subtype'];
        $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
          'id' => 'system_menu_block:' . $subtype,
          'provider' => 'system',
          'label_display' => 'visible',
          'level' => $paneconfig['origconfig']['level'],
          'depth' => $paneconfig['origconfig']['depth'],
          'expand_all_items' => $paneconfig['origconfig']['expanded'],
          'context_mapping' => [],
        ]);
        break;
      case 'node_title':
        if ($paneconfig['subtype'] === 'node_title') {
          $node = $this->entityTypeManager->getStorage('node')->load($rowconfig['nid']);
          // Returns false if the field doesn't exist.
          $exists = !empty($node->get('title')->value);
          if ($exists) {
            $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
              'id' => 'field_block:node:' . $rowconfig['nodetype'] . ':title',
              'label' => "Title",
              'provider' => 'layout_builder',
              'label_display' => 0,
              'formatter' => [
                'label' => 'hidden',
                'type' => 'string',
                'settings' => [
                  "link_to_entity" => 0
                ],
                'third_party_settings' => []
              ],
              'context_mapping' => [
                'entity' => 'layout_builder.entity',
                'view_mode' => 'view_mode',
              ],
            ]);
          }
        }
        break;
    }

    if (isset($component)) {
      return $component;
    }
    else {
      $format = 'There was no section component built for the following empty panel: node %s; type: %s; field: %s; region: %s.';
      $print = sprintf($format, $rowconfig['nid'], $rowconfig['nodetype'], $paneconfig['field'], $paneconfig['region']);
      \Drupal::logger('watts_migrate')->notice($print);
      return NULL;
    }
  }

}
