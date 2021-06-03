<?php

namespace Drupal\watts_migrate;

use Drupal\migrate\Row;

/**
 * Helper to get Fieldable Panels Panes Text data.
 */
trait GetFppDataTrait {

  /**
   * The drupal_7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Connection;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Get the Fieldable Panels Pane data.
   *
   * @param array $pane
   *   The pane being processed.
   * @param \Drupal\migrate\Row $row
   *   The row being processed.
   * @param int $i
   *   The current iteration.
   */
  public function getFppData(array $pane, Row $row, int $i) {
    $fpptype = $pane['subtype'];

    [$subtype, $id] = explode(':', $fpptype, 2);

    /* There are five different subtypes that are used to handle revision
    locking in the database. These are: fpid, vid, uuid, vuuid, and current.
    We will use these to get the latest revision for each fpp and return the
    correct bundle.
    For fpid: Reusable. Use fpid to get the vid from the current revision table.
    For uuid: Reusable. Get fpid and vid from the current revision table.
    For vid: Non-reusable. Get the fpid from the current revision table.
    For vuuid: Non-reusable. Get fpid and vid from the current revision table.
    For "current:###": Get vid from revision table, using ### as the fpid. */

    switch ($subtype) {
      case "fpid":
      case "current":
        $fpid = $id;
        $row->setSourceProperty('panes/' . $i . '/fpid', $fpid);
        $getvid = $this->d7Connection
          ->select('fieldable_panels_panes_revision', 'fppr')
          ->fields('fppr', ['vid'])
          ->condition('fppr.fpid', $fpid)
          ->execute()
          ->fetchCol();
        $vid = array_pop($getvid);
        $row->setSourceProperty('panes/' . $i . '/vid', $vid);
        $getbundle = $this->d7Connection
          ->select('fieldable_panels_panes', 'fpp')
          ->fields('fpp', ['bundle'])
          ->condition('fpp.fpid', $fpid)
          ->execute()
          ->fetchCol();
        $bundle = array_pop($getbundle);
        $row->setSourceProperty('panes/' . $i . '/bundle', $bundle);
        break;

      case "vid":
        $vid = $id;
        $row->setSourceProperty('panes/' . $i . '/vid', $vid);
        $getfpid = $this->d7Connection
          ->select('fieldable_panels_panes_revision', 'fppr')
          ->fields('fppr', ['fpid'])
          ->condition('fppr.vid', $vid)
          ->execute()
          ->fetchCol();
        $fpid = array_pop($getfpid);
        $row->setSourceProperty('panes/' . $i . '/fpid', $fpid);
        $getbundle = $this->d7Connection
          ->select('fieldable_panels_panes', 'fpp')
          ->fields('fpp', ['bundle'])
          ->condition('fpp.fpid', $fpid)
          ->execute()
          ->fetchCol();
        $bundle = array_pop($getbundle);
        $row->setSourceProperty('panes/' . $i . '/bundle', $bundle);
        break;

      case "uuid":
        $uuid = $id;
        $row->setSourceProperty('panes/' . $i . '/uuid', $uuid);
        $getfpid = $this->d7Connection
          ->select('fieldable_panels_panes', 'fpp')
          ->fields('fpp', ['fpid'])
          ->condition('fpp.uuid', $uuid)
          ->execute()
          ->fetchCol();
        $fpid = array_pop($getfpid);
        $row->setSourceProperty('panes/' . $i . '/fpid', $fpid);
        $getvid = $this->d7Connection
          ->select('fieldable_panels_panes_revision', 'fppr')
          ->fields('fppr', ['vid'])
          ->condition('fppr.vid', $fpid)
          ->execute()
          ->fetchCol();
        $vid = array_pop($getvid);
        $row->setSourceProperty('panes/' . $i . '/vid', $vid);
        $getbundle = $this->d7Connection
          ->select('fieldable_panels_panes', 'fpp')
          ->fields('fpp', ['bundle'])
          ->condition('fpp.fpid', $fpid)
          ->execute()
          ->fetchCol();
        $bundle = array_pop($getbundle);
        $row->setSourceProperty('panes/' . $i . '/bundle', $bundle);
        break;

      case "vuuid":
        $vuuid = $id;
        $row->setSourceProperty('panes/' . $i . '/vuuid', $vuuid);
        $getvid = $this->d7Connection
          ->select('fieldable_panels_panes_revision', 'fppr')
          ->fields('fppr', ['vid'])
          ->condition('fppr.vuuid', $vuuid)
          ->execute()
          ->fetchCol();
        $vid = array_pop($getvid);
        $row->setSourceProperty('panes/' . $i . '/vid', $vid);
        $getfpid = $this->d7Connection
          ->select('fieldable_panels_panes_revision', 'fppr')
          ->fields('fppr', ['fpid'])
          ->condition('fppr.vuuid', $vuuid)
          ->execute()
          ->fetchCol();
        $fpid = array_pop($getfpid);
        $row->setSourceProperty('panes/' . $i . '/fpid', $fpid);
        $getbundle = $this->d7Connection
          ->select('fieldable_panels_panes', 'fpp')
          ->fields('fpp', ['bundle'])
          ->condition('fpp.fpid', $fpid)
          ->execute()
          ->fetchCol();
        $bundle = array_pop($getbundle);
        $row->setSourceProperty('panes/' . $i . '/bundle', $bundle);
        break;

      default:
        $fpid = NULL;
        $vid = NULL;
        $bundle = NULL;
        $this->logger->notice('An unknown fieldable_panels_pane subtype was 
        referenced: %subtype', ['%subtype' => $subtype]);
    }

    // Use the retrieved bundle to access the contents of the 'text' fpp.
    if ($bundle === "text") {
      $textquery = $this->d7Connection
        ->select('field_data_field_basic_text_text', 'fdfbtt');
      // Join with fpp to get the 'title' field of the pane.
      $textquery->innerJoin(
        'fieldable_panels_panes',
        'fpp',
        'fdfbtt.entity_id = fpp.fpid'
      );
      $textquery->fields('fpp',
        ['title', 'link', 'path', 'reusable', 'admin_title']
      )
        ->fields(
          'fdfbtt',
          [
            'field_basic_text_text_value',
            'field_basic_text_text_format',
          ]
        )
        ->condition('fdfbtt.entity_id', $fpid);
      $result = $textquery->execute()->fetchAll();

      $row->setSourceProperty('panes/' . $i . '/text_fpp', $result);

    }
    // Access the contents of the 'hero and jumbohero' fpps.
    if ($bundle === "hero") {
      $heroquery = $this->d7Connection->select('fieldable_panels_panes', 'fpp');
      $heroquery->leftJoin('field_data_field_webspark_hero_bgimg', 'hbi', 'hbi.entity_id = fpp.fpid');
      $heroquery->leftJoin('field_data_field_webspark_hero_blurb', 'hbl', 'hbl.entity_id = fpp.fpid');
      $heroquery->leftJoin('field_data_field_webspark_hero_gradbtn', 'hgb', 'hgb.entity_id = fpp.fpid');
      $heroquery->leftJoin('field_data_field_webspark_hero_height', 'hht', 'hht.entity_id = fpp.fpid');
      $heroquery->leftJoin('field_data_field_webspark_hero_primarybtn', 'hpb', 'hpb.entity_id = fpp.fpid');
      $heroquery->leftJoin('field_data_field_webspark_hero_ugradbtn', 'hub', 'hub.entity_id = fpp.fpid');
      $heroquery->leftJoin('field_data_field_webspark_jumbohero_bgimg', 'jhbi', 'jhbi.entity_id = fpp.fpid');
      $heroquery->leftJoin('field_data_field_webspark_jumbohero_blurb', 'jhbl', 'jhbl.entity_id = fpp.fpid');
      $heroquery->leftJoin('field_data_field_webspark_jumbo_position', 'jhp', 'jhp.entity_id = fpp.fpid');
      $heroquery->fields('fpp',
        ['title', 'link', 'path', 'reusable', 'admin_title']
      )
        ->fields('hbi', ['field_webspark_hero_bgimg_fid'])
        ->fields('hbl', ['field_webspark_hero_blurb_value'])
        ->fields('hgb',
          [
            'field_webspark_hero_gradbtn_url',
            'field_webspark_hero_gradbtn_title',
          ]
        )
        ->fields('hht', ['field_webspark_hero_height_value'])
        ->fields('hpb',
          [
            'field_webspark_hero_primarybtn_url',
            'field_webspark_hero_primarybtn_title',
          ]
        )
        ->fields('hub',
          [
            'field_webspark_hero_ugradbtn_url',
            'field_webspark_hero_ugradbtn_title',
          ]
        )
        ->fields('jhbi', ['field_webspark_jumbohero_bgimg_fid'])
        ->fields('jhbl',
          [
            'field_webspark_jumbohero_blurb_value',
            'field_webspark_jumbohero_blurb_format',
          ]
        )
        ->fields('jhp', ['field_webspark_jumbo_position_value'])
        ->condition('hbi.entity_id', $fpid);
      $result = $heroquery->execute()->fetchAll();

      $row->setSourceProperty('panes/' . $i . '/hero_fpp', $result);
    }
    // Access the contents of the 'asu_spotlight' fpp.
    if ($bundle === "asu_spotlight") {
      $spotquery = $this->d7Connection->select('fieldable_panels_panes', 'fpp');
      $spotquery->leftJoin('field_data_field_asu_spotlight_items', 'asi', 'asi.entity_id = fpp.fpid');
      $spotquery->fields('fpp',
        ['title', 'link', 'path', 'reusable', 'admin_title']
      )
        ->fields('asi',
          [
            'field_asu_spotlight_items_title',
            'field_asu_spotlight_items_description',
            'field_asu_spotlight_items_fid',
            'field_asu_spotlight_items_link',
            'field_asu_spotlight_items_alt',
            'field_asu_spotlight_items_actionlink',
            'field_asu_spotlight_items_actiontitle',
            'field_asu_spotlight_items_actionlink2',
            'field_asu_spotlight_items_actiontitle2',
            'field_asu_spotlight_items_position',
            'field_asu_spotlight_items_overlaytextwidth',
            'field_asu_spotlight_items_color',
          ]
        )
        ->condition('asi.entity_id', $fpid)
        ->condition('asi.deleted', 0, '=');
      $result = $spotquery->execute()->fetchAll();

      $row->setSourceProperty('panes/' . $i . '/asu_spotlight_fpp', $result);
    }
    // Access the contents of the 'banner' fpp.
    if ($bundle === "banner") {
      $bannerquery = $this->d7Connection->select('fieldable_panels_panes', 'fpp');
      $bannerquery->leftJoin('field_data_field_banner_image', 'fbi', 'fbi.entity_id = fpp.fpid');
      $bannerquery->fields('fpp',
        ['title', 'link', 'path', 'reusable', 'admin_title']
      )
        ->fields('fbi', ['field_banner_image_fid'])
        ->condition('fbi.entity_id', $fpid)
        ->condition('fbi.deleted', 0, '=');
      $result = $bannerquery->execute()->fetchAll();

      $row->setSourceProperty('panes/' . $i . '/banner_fpp', $result);
    }

  }

}
