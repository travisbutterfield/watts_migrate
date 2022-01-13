<?php

namespace Drupal\watts_migrate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * Helpers to transform embedded media tags.
 */
trait WattsMediaWysiwygTransformTrait {

  /**
   * Transform embedded media in wysiwyg content.
   *
   * @param string $wysiwyg_content
   *   The content to search and transform embedded media.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager service.
   *
   * @return string
   *   The original wysiwyg_content with embedded media in D8 format.
   */
  public function transformWysiwyg($wysiwyg_content, EntityTypeManagerInterface $entityTypeManager) {
    $pattern = '/\[\[(?<tag_info>.+?"type":"media".+?)\]\]/s';
    $media_embed_replacement_template = <<<'TEMPLATE'
<drupal-media alt="%s" data-entity-type="media" data-entity-uuid="%s" data-view-mode="%s"></drupal-media>
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
          $media_entity_uuid,
          $view_mode
        );
      }
      catch (\Exception $e) {
        \Drupal::logger('watts_migrate')->notice('Caught exception: ' . $e->getMessage() . ' while trying to process this json: ' . $matches['tag_info']);
      }
    }, $wysiwyg_content);

    // Replace captions with D9 media captions.
    $captregex = '/\[caption.+?(caption="(?<caption>.+?)"|(.*?))\](.*?)(?<pre><drupal-media.*?)(?<post>><\/drupal-media>)(?<end>.*?\[\/caption\])/s';
    $wysiwyg_content = preg_replace_callback($captregex, function ($matches) {
      return $matches['pre'] . ' data-caption="' . $matches['caption'] . '"' . $matches['post'];
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
