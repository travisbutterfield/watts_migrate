<?php

namespace Drupal\watts_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a watts_media_wysiwyg_conversion plugin.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: watts_media_wysiwyg_conversion
 *     source: foo
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "watts_media_wysiwyg_conversion"
 * )
 *
 * @DCG
 * ContainerFactoryPluginInterface is optional here. If you have no need for
 * external services just remove it and all other stuff except transform()
 * method.
 */
class WattsMediaWysiwygConversion extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a WattsMediaWysiwygConversion plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    is_array($value) ?: $value = (array) $value;

    foreach ($value as $item) {
      if ($item) {

      }
    }
    return $this->transformWysiwyg($value, $this->entityTypeManager);
  }

  /**
   * Transform embedded media in wysiwyg content.
   *
   * @param mixed $wysiwyg_content
   *   The content to search and transform embedded media.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager service.
   *
   * @return string
   *   The original wysiwyg_content with embedded media in D8 format.
   */
  public function transformWysiwyg($wysiwyg_content, EntityTypeManagerInterface $entityTypeManager) {
    $view_mode = NULL;

    $pattern = '/\[\[(?<tag_info>.+?"type":"media".+?)\]\]/s';

    $media_embed_replacement_template = <<<'TEMPLATE'
<drupal-media
  alt="%s"
  data-caption="%s"
  data-entity-type="media"
  data-entity-uuid="%s"
  data-view-mode="%s"></drupal-media>
TEMPLATE;

    $wysiwyg_content = preg_replace_callback($pattern, function ($matches) use ($media_embed_replacement_template, $entityTypeManager) {
      $decoder = new JsonDecode(TRUE);

      try {
        $tag_info = $decoder->decode($matches['tag_info'], JsonEncoder::FORMAT);
        $view_mode = $tag_info['view_mode'];
        $media_entity_uuid = $entityTypeManager->getStorage('media')
          ->load($tag_info['fid']);

        $media_entity_uuid = $media_entity_uuid ? $media_entity_uuid->uuid() : 0;

        return sprintf($media_embed_replacement_template,
          $tag_info['fields']['field_file_image_alt_text[und][0][value]'] ?? '',
          htmlentities(stripslashes(urldecode($tag_info['fields']['field_caption[und][0][value]']))) ?? '',
          $media_entity_uuid,
          $view_mode
        );
      }
      catch (\Exception $e) {
        \Drupal::logger('watts_migrate')->notice('Caught exception: ' . $e->getMessage() . ' while trying to process this json: ' . $matches['tag_info']);
      }
    }, $wysiwyg_content);

    return $wysiwyg_content;
  }

  /**
   * Extract block_header media from wysiwyg content.
   *
   * @param string $wysiwyg_content
   *   The content to search and extract block_header media.
   *
   * @return array
   *   An array that consists of the extracted block_header and the original
   *   wysiwyg_content with the block header removed.
   */
  public function extractBlockHeader($wysiwyg_content) {
    $pattern = '/\[\[(?<tag_info>.+?"type":"media".+?)\]\]/s';
    preg_match($pattern, $wysiwyg_content, $matches);

    if ($matches['tag_info']) {
      try {
        $decoder = new JsonDecode(TRUE);
        $tag_info = $decoder->decode($matches['tag_info'], JsonEncoder::FORMAT);
        if ($tag_info['view_mode'] == 'block_header') {
          $block_header = [
            'target_id' => $tag_info['fid'],
            'alt' => $tag_info['attributes']['alt'],
          ];

          return [
            'block_header' => $block_header,
            'wysiwyg_content' => str_replace('[[' . $matches['tag_info'] . ']]', '', $wysiwyg_content),
          ];
        }

      }
      catch (\Exception $e) {
        \Drupal::logger('watts_migrate')->notice('Caught exception: ' . $e->getMessage() . ' while trying to process this json: ' . $matches['tag_info']);
      }
    }

    return [
      'block_header' => NULL,
      'wysiwyg_content' => $wysiwyg_content,
    ];
  }

}
