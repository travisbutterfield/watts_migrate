<?php

namespace Drupal\watts_migrate\Plugin\MediaWysiwyg;

use Drupal\media_migration\MediaWysiwygPluginBase;

/**
 * Media WYSIWYG plugin for fieldable_panels_pane → block_content migrations.
 *
 * @MediaWysiwyg(
 *   id = "fieldable_panels_pane",
 *   label = @Translation("Fieldable Panels Pane"),
 *   entity_type_map = {
 *     "fieldable_panels_pane" = "block_content",
 *   },
 * )
 */
class FieldablePanelsPane extends MediaWysiwygPluginBase {}