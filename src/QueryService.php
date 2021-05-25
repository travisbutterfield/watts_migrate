<?php

namespace Drupal\watts_migrate;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;

/**
 * QueryService is used to test queries on the database.
 */
class QueryService {

  /**
   * This function gets the query from the db.
   */
  public function getQuery() {
    // .......Unrelated but useful code......
    // Uncomment to turn on Layout Builder on all content types
    /*$nodes = $this->nodeTypeGetNames();
    $success = [];
    foreach ($nodes as $key => $value) {
    try {
    $entity_type = 'node';
    $content_type = $key;
    $view_type = 'default';

    \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay::load(
    "{$entity_type}.{$content_type}.{$view_type}"
    )->enableLayoutBuilder()
    ->setOverridable()
    ->save();
    $success[] = $key;
    }
    catch (EntityStorageException $e) {
      echo 'Caught exception: ',  $e->getMessage(), "\n";
      continue;
    }
    }
    $message = "Layout Builder was enabled on the following: %s";
    $types = implode(", ", $success);
    $combo = sprintf("$message", $types);
    echo "<script>alert('$combo')</script>";*/
    // ..... end of unrelated code .....
    $connect = Database::getConnection('default', 'default');

    $qd9 = $connect->select('node__layout_builder__layout', 'nlbl');

    $qd9->condition('nlbl.entity_id', 37)
      ->fields('nlbl', ['layout_builder__layout_section']);
    $myresult = $qd9->execute()->fetchField();
    $print = unserialize($myresult);
    dump($print);

    $con = Database::getConnection('external', 'migrate');

    $query = $con->select('node', 'n');
    $query->innerJoin('field_data_body', 'fdb', 'n.nid = fdb.entity_id AND fdb.entity_type = :etype', [':etype' => 'node']);
    $query->innerJoin('panelizer_entity', 'pe', 'n.vid = pe.revision_id AND pe.entity_id = n.nid AND pe.entity_type = :type', [':type' => 'node']);
    $query->innerJoin('panels_display', 'pd', 'pe.did = pd.did');
    $query->fields('n', ['title', 'nid', 'vid'])
      ->fields('pe', ['did'])
      ->fields('pd', ['layout'])
      ->fields('fdb', ['body_value', 'body_format', 'body_summary']);
    // Test one node.
    $query->condition('n.nid', 3373);
    $results = $query->execute()->fetchAll();

    dump($results);

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
              $fpid = $id;
              $getvid = $con->select('fieldable_panels_panes_revision', 'fppr')
                ->fields('fppr', ['vid'])
                ->condition('fppr.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $vid = array_pop($getvid);
              $getbundle = $con->select('fieldable_panels_panes', 'fpp')->fields('fpp', ['bundle'])
                ->condition('fpp.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $bundle = array_pop($getbundle);
              echo "bundle = $bundle <br>fpid = $fpid <br>vid = $vid";
              break;

            case "vid":
              $vid = $id;
              $getfpid = $con->select('fieldable_panels_panes_revision', 'fppr')
                ->fields('fppr', ['fpid'])
                ->condition('fppr.vid', $vid)
                ->execute()
                ->fetchCol();
              $fpid = array_pop($getfpid);
              $getbundle = $con->select('fieldable_panels_panes', 'fpp')->fields('fpp', ['bundle'])
                ->condition('fpp.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $bundle = array_pop($getbundle);
              echo "bundle = $bundle <br>fpid = $fpid <br>vid = $vid";
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
              $getbundle = $con->select('fieldable_panels_panes', 'fpp')->fields('fpp', ['bundle'])
                ->condition('fpp.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $bundle = array_pop($getbundle);
              echo "bundle = $bundle <br>uuid = $uuid <br>fpid = $fpid <br>vid = $vid";
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
              $getbundle = $con->select('fieldable_panels_panes', 'fpp')->fields('fpp', ['bundle'])
                ->condition('fpp.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $bundle = array_pop($getbundle);
              echo "bundle = $bundle <br>vuuid = $vuuid <br>fpid = $fpid <br>vid = $vid";
              break;

            case "current":
              $fpid = $id;
              $getvid = $con->select('fieldable_panels_panes_revision', 'fppr')
                ->fields('fppr', ['vid'])
                ->condition('fppr.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $vid = array_pop($getvid);
              $getbundle = $con->select('fieldable_panels_panes', 'fpp')->fields('fpp')
                ->condition('fpp.fpid', $fpid)
                ->execute()
                ->fetchCol();
              $bundle = array_pop($getbundle);
              echo "bundle = $bundle<br>fpid = $fpid<br>vid = $vid";
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
                ['field_basic_text_text_value',
                  'field_basic_text_text_format',
                ],
              )
              ->condition('fdfbtt.entity_id', $fpid);
            $result = $textquery->execute()->fetchAll();
            dump($result);
          }
        }
        // Limit results to two panes per node, just for testing purposes.
        // if (++$i == 2) break;.
      }

    }

  }

  /**
   * Returns a list of available node type names.
   *
   * This list can include types that are queued for addition or deletion.
   *
   * @return string[]
   *   An array of node type labels, keyed by the node type name.
   */
  public function nodeTypeGetNames(): array {
    return array_map(function ($bundle_info) {
      return $bundle_info['label'];
    }, \Drupal::service('entity_type.bundle.info')->getBundleInfo('node'));
  }

}
