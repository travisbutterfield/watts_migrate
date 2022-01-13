<?php

namespace Drupal\watts_migrate;

use Drupal\Core\Database\Database;

/**
 * QueryService is a temporary service used to test queries on the database.
 *
 * Routing: You can view the results at /testpage.
 */
class QueryService {

  /**
   * This function gets query data from the db.
   */
  public function getQuery() {
    $testnid = 59;

    // This block of code unserializes Layout Builder data from the D9 database.
    // It is useful for testing how to add section content to Layout Builder.
    $connect = Database::getConnection('default', 'default');
    $qd9 = $connect->select('node__layout_builder__layout', 'nlbl');
    // Test one node.
    $qd9->condition('nlbl.entity_id', $testnid)
      ->fields('nlbl', ['layout_builder__layout_section']);
    $myresult = $qd9->execute()->fetchAll();
    foreach ($myresult as $poop) {
      $test = unserialize($poop->layout_builder__layout_section);
      dump($test);
    }

    echo "D9 Layout Builder Layout Section data for Node {$testnid}.";

    echo "<hr><p>Panelizer data from the D7 database:</p>";

    /* The rest of this code is replicated in the source plugin
    WattsPanelizerNode and the GetFppDataTrait trait.
    It is just here for testing purposes. */
    $con = Database::getConnection('external', 'migrate');
    $query = $con->select('node', 'n');
    $query->innerJoin('field_data_body', 'fdb', 'n.nid = fdb.entity_id AND fdb.entity_type = :etype', [':etype' => 'node']);
    $query->innerJoin('panelizer_entity', 'pe', 'n.vid = pe.revision_id AND pe.entity_id = n.nid AND pe.entity_type = :type', [':type' => 'node']);
    $query->innerJoin('panels_display', 'pd', 'pe.did = pd.did');
    $query->fields('n', ['title', 'nid', 'vid'])
      ->fields('pe', ['did'])
      ->fields('pd', ['layout'])
      ->fields('fdb', ['body_value', 'body_format', 'body_summary']);
    // Test only one node. Otherwise, it's too much data.
    $query->condition('n.nid', $testnid);
    $results = $query->execute()->fetchAll();

    foreach ($results as $current_result) {

      dump($current_result);
      $did = $current_result->did;

      // Fetch the panes and add the result as a source property.
      $panes = $con->select('panels_pane', 'pp')
        ->fields('pp')
        ->condition('pp.did', $did)
        ->orderBy('pp.position')
        ->orderBy('pp.panel')
        ->execute()
        ->fetchAll();

      // $i = 0;
      foreach ($panes as $pane) {
        dump($pane);

        // Get the contents of Fieldable Panels Panes.
        if ($pane->type === 'fieldable_panels_pane') {

          $fpptype = $pane->subtype;

          [$subtype, $id] = explode(':', $fpptype, 2);

          // There are five different subtypes that are used to handle revision
          // locking in the database. These are: fpid, vid, uuid, vuuid, and
          // current. We will use these to get the latest revision for each fpp
          // and return the correct bundle.
          switch ($subtype) {
            case "fpid":
            case "current":
              $fpid = $id;
              $getvid = $con->select('fieldable_panels_panes_revision', 'fppr')
                ->fields('fppr', ['vid'])
                ->condition('fppr.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $vid = array_pop($getvid);
              $getbundle = $con->select('fieldable_panels_panes', 'fpp')
                ->fields('fpp', ['bundle'])
                ->condition('fpp.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $bundle = array_pop($getbundle);
              echo "bundle = $bundle <br>fpid = $fpid <br>vid = $vid<br>";
              break;

            case "vid":
              $vid = $id;
              $getfpid = $con->select('fieldable_panels_panes_revision', 'fppr')
                ->fields('fppr', ['fpid'])
                ->condition('fppr.vid', $vid)
                ->execute()
                ->fetchCol();
              $fpid = array_pop($getfpid);
              $getbundle = $con->select('fieldable_panels_panes', 'fpp')
                ->fields('fpp', ['bundle'])
                ->condition('fpp.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $bundle = array_pop($getbundle);
              echo "bundle = $bundle <br>fpid = $fpid <br>vid = $vid<br>";
              break;

            case "uuid":
              $uuid = $id;
              $getfpid = $con->select('fieldable_panels_panes', 'fpp')
                ->fields('fpp', ['fpid'])
                ->condition('fpp.uuid', $uuid)
                ->execute()
                ->fetchCol();
              $fpid = array_pop($getfpid);
              $getvid = $con->select('fieldable_panels_panes_revision', 'fppr')
                ->fields('fppr', ['vid'])
                ->condition('fppr.vid', $fpid)
                ->execute()
                ->fetchCol();
              $vid = array_pop($getvid);
              $getbundle = $con->select('fieldable_panels_panes', 'fpp')
                ->fields('fpp', ['bundle'])
                ->condition('fpp.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $bundle = array_pop($getbundle);
              echo "bundle = $bundle <br>uuid = $uuid <br>fpid = $fpid <br>vid = $vid<br>";
              break;

            case "vuuid":
              $vuuid = $id;
              $getvid = $con->select('fieldable_panels_panes_revision', 'fppr')
                ->fields('fppr', ['vid'])
                ->condition('fppr.vuuid', $vuuid)
                ->execute()
                ->fetchCol();
              $vid = array_pop($getvid);
              $getfpid = $con->select('fieldable_panels_panes_revision', 'fppr')
                ->fields('fppr', ['fpid'])
                ->condition('fppr.vuuid', $vuuid)
                ->execute()
                ->fetchCol();
              $fpid = array_pop($getfpid);
              $getbundle = $con->select('fieldable_panels_panes', 'fpp')
                ->fields('fpp', ['bundle'])
                ->condition('fpp.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $bundle = array_pop($getbundle);
              echo "bundle = $bundle <br>vuuid = $vuuid <br>fpid = $fpid <br>vid = $vid<br>";
              break;

            default:
              $fpid = NULL;
              $bundle = NULL;
              echo "An unknown fieldable_panels_pane subtype was referenced: $subtype.<br>";
          }

          // Use the retrieved bundle to access the contents of the 'text' fpps.
          if ($bundle === "text") {
            $textquery = $con->select('field_data_field_basic_text_text', 'fdfbtt');
            // Join with fpp to get the 'title' field of the pane.
            $textquery->innerJoin('fieldable_panels_panes', 'fpp', 'fdfbtt.entity_id = fpp.fpid');
            $textquery->fields('fpp', ['title'])
              ->fields('fdfbtt',
                [
                  'field_basic_text_text_value',
                  'field_basic_text_text_format',
                ],
              )
              ->condition('fdfbtt.entity_id', $fpid);
            $result = $textquery->execute()->fetchAll();
            dump($result);
          }
          if ($bundle === "hero") {
            $heroquery = $con->select('fieldable_panels_panes', 'fpp');
            $heroquery->leftJoin('field_data_field_webspark_hero_bgimg', 'hbi', 'hbi.entity_id = fpp.fpid');
            $heroquery->leftJoin('field_data_field_webspark_hero_blurb', 'hbl', 'hbl.entity_id = fpp.fpid');
            $heroquery->leftJoin('field_data_field_webspark_hero_gradbtn', 'hgb', 'hgb.entity_id = fpp.fpid');
            $heroquery->leftJoin('field_data_field_webspark_hero_height', 'hht', 'hht.entity_id = fpp.fpid');
            $heroquery->leftJoin('field_data_field_webspark_hero_primarybtn', 'hpb', 'hpb.entity_id = fpp.fpid');
            $heroquery->leftJoin('field_data_field_webspark_hero_ugradbtn', 'hub', 'hub.entity_id = fpp.fpid');
            $heroquery->leftJoin('field_data_field_webspark_jumbohero_bgimg', 'jhbi', 'jhbi.entity_id = fpp.fpid');
            $heroquery->leftJoin('field_data_field_webspark_jumbohero_blurb', 'jhbl', 'jhbl.entity_id = fpp.fpid');
            $heroquery->leftJoin('field_data_field_webspark_jumbo_position', 'jhp', 'jhp.entity_id = fpp.fpid');
            $heroquery->fields('hbi', ['field_webspark_hero_bgimg_fid'])
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
            echo "FPP hero data for pane {$fpid} on node {$testnid}.";
            dump($result);
          }
        }
        // Limit results to two panes per node, just for testing purposes.
        // if (++$i == 2) break;.
      }
    }
  }

}
