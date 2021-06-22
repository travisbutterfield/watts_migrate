<?php

namespace Drupal\watts_migrate\Plugin\migrate\process;

use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\redirect\RedirectRepository;
use Drupal\watts_migrate\WattsMediaWysiwygTransformTrait;
use Drupal\watts_migrate\WattsWysiwygTextProcessingTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Given a set of panes, returns a layout builder section.
 *
 * @MigrateProcessPlugin(
 *   id = "watts_panes_to_lb_section"
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
 *     plugin: watts_panes_to_lb_section
 *   -
 *     plugin: multiple_values
 * @endcode
 */
class WattsPanesToLbSection extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  use WattsMediaWysiwygTransformTrait;
  use WattsWysiwygTextProcessingTrait;
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
      $container->get('entity_type.manager'),
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

    $lbload = LayoutBuilderEntityViewDisplay::load(
      "node.{$rowconfig['nodetype']}.default");
    $lbtest = $lbload->isLayoutBuilderEnabled();
    if (!$lbtest) {
      try {
        $lbload->enableLayoutBuilder()
          ->setOverridable()
          ->save();
        $format = 'Notice: Layout Builder has been enabled on the %s content type. Now continuing with migration...';
        $print = sprintf($format, $rowconfig['nodetype']);
        \Drupal::logger('watts_migrate')->notice($print);
      }
      catch (EntityStorageException $e) {
        $format = 'An error has occurred: Layout Builder has NOT been enabled on the %s content type. Exiting migration. Please enable Layout Builder and try again.';
        $print = sprintf($format, $rowconfig['nodetype']);
        \Drupal::logger('watts_migrate')->error($print);
        exit;
      }
    }
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
          $text = $this->processText($text);
          $paneconfig['title'] = $pane['text_fpp']['0']->title;
          $paneconfig['text'] = $text;
          $paneconfig['textformat'] = $pane['text_fpp']['0']->field_basic_text_text_format ?: 'full_html';

          $component = $this->buildSectionComponent($rowconfig, $paneconfig);
          $section->appendComponent($component);
        }
        if ($paneconfig['bundle'] === 'hero' && $paneconfig['shown']) {
          $paneconfig['hero_fpp'] = $pane['hero_fpp']['0'];

          $component = $this->buildSectionComponent($rowconfig, $paneconfig);
          $section->appendComponent($component);
        }
        if ($paneconfig['bundle'] === 'banner' && $paneconfig['shown']) {
          $paneconfig['banner_fpp'] = $pane['banner_fpp']['0'];

          $component = $this->buildSectionComponent($rowconfig, $paneconfig);
          $section->appendComponent($component);
        }
        if ($paneconfig['bundle'] === 'asu_spotlight' && $paneconfig['shown']) {
          $paneconfig['asu_spotlight_fpp'] = $pane['asu_spotlight_fpp']['0'];

          $component = $this->buildSectionComponent($rowconfig, $paneconfig);
          $section->appendComponent($component);
        }
        // TODO: Add ways to look up other fpp types.
      }
      elseif ($paneconfig['type'] === 'entity_field' || $paneconfig['type'] === 'node_body') {
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

    }
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
              'info' => $paneconfig['title'],
              'type' => $paneconfig['bundle'] === 'text' ? 'text_content' : $paneconfig['bundle'],
              'field_formatted_text' => [
                // These values come from the configuration of the panel pane.
                'value' => $paneconfig['text'],
                'format' => $paneconfig['textformat'],
              ],
            ]);
          // Create Block embedded in a Section Component. Passing a serialized
          // Block entity is the key to making this work.
          $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
            'id' => 'inline_block:text_content',
            'label' => $paneconfig['title'],
            'provider' => 'layout_builder',
            'label_display' => isset($paneconfig['title']),
            'view_mode' => 'full',
            'block_serialized' => serialize($block),
            'context_mapping' => [],
          ],[
            'component_attributes' => [
              'block_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ],
              'block_title_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ],
              'block_content_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ]
            ]
          ]);
        }
        if ($paneconfig['bundle'] === 'hero') {
          $cta = Paragraph::create(['type' => 'cta']);
          $cta->set('field_cta_link', [
              'uri' => $paneconfig['hero_fpp']->field_webspark_hero_primarybtn_url,
              'title' => $paneconfig['hero_fpp']->field_webspark_hero_primarybtn_title,
              'options' => 'a:1:{s:10:"attributes";a:2:{s:6:"target";s:5:"_self";s:5:"class";s:24:"btn-default btn-gold btn";}}'
            ]
          );
          $cta->isNew();
          $cta->save();
          $sizes = ['380' => 'md', '700' => 'lg'];
          $herosize = $sizes[$paneconfig['hero_fpp']->field_webspark_hero_height_value] ?? null;
          $block = $this->entityTypeManager->getStorage('block_content')
            ->create([
              'reusable' => 0,
              'info' => 'Hero',
              'type' => $paneconfig['bundle'],
              'field_cta' => [
                'target_id' => $cta->id(),
                'target_revision_id' => $cta->getRevisionId(),
              ],
              'field_heading' => $paneconfig['hero_fpp']->title,
              'field_hero_background_color' => 'gold',
              'field_hero_size' => $herosize,
              'field_hero_unformatted_text' => $paneconfig['hero_fpp']->field_webspark_hero_blurb_value,
              'field_media' => $paneconfig['hero_fpp']->field_webspark_hero_bgimg_fid,
            ]);
          // Create Block embedded in a Section Component. Passing a serialized
          // Block entity is the key to making this work.
          $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
            'id' => 'inline_block:hero',
            'label' => 'Hero',
            'provider' => 'layout_builder',
            'label_display' => 0,
            'view_mode' => 'full',
            'reusable' => 0,
            'block_serialized' => serialize($block),
            'context_mapping' => [],
          ],[
            'component_attributes' => [
              'block_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ],
              'block_title_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ],
              'block_content_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ]
            ]
          ]);
        }
        // Migrate first slide of ASU Spotlight as a Hero.
        if ($paneconfig['bundle'] === 'asu_spotlight') {
          $link = $paneconfig['asu_spotlight_fpp']->field_asu_spotlight_items_actionlink;
          $linktest = substr($paneconfig['asu_spotlight_fpp']->field_asu_spotlight_items_actionlink, 0, 4);
          $cta = Paragraph::create(['type' => 'cta']);
          $cta->set('field_cta_link', [
              'uri' => $linktest === 'http' ? $link : 'internal:' . $link,
              'title' => $paneconfig['asu_spotlight_fpp']->field_asu_spotlight_items_actiontitle,
              'options' => 'a:1:{s:10:"attributes";a:2:{s:6:"target";s:5:"_self";s:5:"class";s:24:"btn-default btn-gold btn";}}'
            ]
          );
          $cta->isNew();
          $cta->save();
          $block = $this->entityTypeManager->getStorage('block_content')
            ->create([
              'reusable' => 0,
              'info' => 'Hero',
              'type' => 'hero',
              'field_cta' => [
                'target_id' => $cta->id(),
                'target_revision_id' => $cta->getRevisionId(),
              ],
              'field_heading' => $paneconfig['asu_spotlight_fpp']->field_asu_spotlight_items_title,
              'field_hero_background_color' => 'gold',
              'field_hero_size' => 'lg',
              'field_hero_unformatted_text' => $paneconfig['asu_spotlight_fpp']->field_asu_spotlight_items_description,
              'field_media' => $paneconfig['asu_spotlight_fpp']->field_asu_spotlight_items_fid,
            ]);
          // Create Block embedded in a Section Component. Passing a serialized
          // Block entity is the key to making this work.
          $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
            'id' => 'inline_block:hero',
            'label' => 'Hero',
            'provider' => 'layout_builder',
            'label_display' => 0,
            'view_mode' => 'full',
            'reusable' => 0,
            'block_serialized' => serialize($block),
            'context_mapping' => [],
          ],[
            'component_attributes' => [
              'block_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ],
              'block_title_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ],
              'block_content_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ]
            ]
          ]);
        }
        // Migrate ASU Title Banners as small Heroes.
        if ($paneconfig['bundle'] === 'banner') {
          $block = $this->entityTypeManager->getStorage('block_content')
            ->create([
              'reusable' => 0,
              'info' => 'Banner hero (migrated)',
              'type' => 'hero',
              'field_heading' => $paneconfig['banner_fpp']->title,
              'field_hero_background_color' => 'gold',
              'field_hero_size' => 'sm',
              'field_media' => $paneconfig['banner_fpp']->field_banner_image_fid,
            ]);
          // Create Block embedded in a Section Component. Passing a serialized
          // Block entity is the key to making this work.
          $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
            'id' => 'inline_block:hero',
            'label' => 'Hero',
            'provider' => 'layout_builder',
            'label_display' => 0,
            'view_mode' => 'full',
            'reusable' => 0,
            'block_serialized' => serialize($block),
            'context_mapping' => [],
          ],[
            'component_attributes' => [
              'block_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ],
              'block_title_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ],
              'block_content_attributes' => [
                'id' => '',
                'class' => '',
                'style' => '',
                'data' => '',
              ]
            ]
          ]);
        }
        break;

      case  'node_body':
      case 'entity_field':
        if ($paneconfig['field'] === 'body' || $paneconfig['subtype'] === 'node_body') {
          $node = $this->entityTypeManager->getStorage('node')->load($rowconfig['nid']);
          // Returns false if the field doesn't exist.
          $exists = !empty($node->body);
          if ($exists) {
            $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
              'id' => 'field_block:node:' . $rowconfig['nodetype'] . ':body',
              'label' => 'Body',
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
          $exists = !empty($node->field_featured_image);
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
            ],[
              'component_attributes' => [
                'block_attributes' => [
                  'id' => '',
                  'class' => '',
                  'style' => '',
                  'data' => '',
                ],
                'block_title_attributes' => [
                  'id' => '',
                  'class' => '',
                  'style' => '',
                  'data' => '',
                ],
                'block_content_attributes' => [
                  'id' => '',
                  'class' => '',
                  'style' => '',
                  'data' => '',
                ]
              ]
            ]);
          }
          unset($exists);
        }
        if ($paneconfig['field'] === 'field_featured_categories') {
          $node = $this->entityTypeManager->getStorage('node')
            ->load($rowconfig['nid']);
          // Returns false if the field doesn't exist.
          $exists = !empty($node->field_featured_categories);
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
        $menutitle = $paneconfig['origconfig']['override_title'] == 1 ? $paneconfig['origconfig']['override_title_text'] : NULL;
        $component = new SectionComponent($this->uuid->generate(), $paneconfig['region'], [
          'id' => 'system_menu_block:' . $subtype,
          'provider' => 'system',
          'label' => $menutitle,
          'label_display' => $paneconfig['origconfig']['override_title'] == 1 ?: 0,
          'level' => $paneconfig['origconfig']['follow'] == 'active' ? 2 : 1,
          'depth' => $paneconfig['origconfig']['depth'],
          'expand_all_items' => $paneconfig['origconfig']['expanded'],
          'context_mapping' => [],
        ],[
          'component_attributes' => [
            'block_attributes' => [
              'id' => '',
              'class' => '',
              'style' => '',
              'data' => '',
            ],
            'block_title_attributes' => [
              'id' => '',
              'class' => '',
              'style' => '',
              'data' => '',
            ],
            'block_content_attributes' => [
              'id' => '',
              'class' => '',
              'style' => '',
              'data' => '',
            ]
          ]
        ]);
        break;

      case 'node_title':
        if ($paneconfig['subtype'] === 'node_title') {
          $node = $this->entityTypeManager->getStorage('node')->load($rowconfig['nid']);
          // Returns false if the field doesn't exist.
          $exists = !empty($node->title);
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
                  "link_to_entity" => 0,
                ],
                'third_party_settings' => [],
              ],
              'context_mapping' => [
                'entity' => 'layout_builder.entity',
                'view_mode' => 'view_mode',
              ],
            ],[
              'component_attributes' => [
                'block_attributes' => [
                  'id' => '',
                  'class' => '',
                  'style' => '',
                  'data' => '',
                ],
                'block_title_attributes' => [
                  'id' => '',
                  'class' => '',
                  'style' => '',
                  'data' => '',
                ],
                'block_content_attributes' => [
                  'id' => '',
                  'class' => '',
                  'style' => '',
                  'data' => '',
                ]
              ]
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
