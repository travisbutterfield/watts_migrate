<?php

/**
 * NOTE: Only to be used locally, NOT on a production environment!!
 * This is a program to be run after you have connected the databases, run
 * migrate:upgrade, and exported the configuration to obtain the yaml files
 * needed for migration. After you have obtained those, running this program
 * will move the "node_complete" yaml files into this directory and alter
 * them for our custom migration.
 *
 * Usage:
 *  - SSH into DDEV with "ddev ssh"
 *  - Enter the following on the command line:
 *     "sudo chmod u+x /var/www/html/web/modules/custom/watts_migrate/
 *      config/install/script.sh; php -f /var/www/html/web/modules/custom/
 *      watts_migrate/config/install/watts_premigration.php"
 *
 * After running, remove any yaml files that were brought into the custom
 * module that you don't want to migrate. Then, either enable the module, if
 * you haven't already, or import the configs with the following:
 *   "drush config:import --partial --source=/var/www/html/web/modules/custom/
 *    watts_migrate/config/install/"
 */

$root = strstr(__DIR__,'web', true);
$configdir = new DirectoryIterator($root . 'config');
$installdir = new DirectoryIterator('/var/www/html/web/modules/custom/watts_migrate/config/install');
$mycount = null;

//Iterate through each file in the directory
foreach ($configdir as $fileinfo) {
  $name = $fileinfo->getFilename();
  if (!$fileinfo->isDot()) {
    // Remove "node_complete" from file name.
    $newname = preg_replace('/node_complete/', 'node', $name, -1, $count);
    if ($count) {
      $mycount++;
      // Save in config/install directory.
      rename($configdir->getPathname(), $installdir->getPath() . "/" . $newname);
      // Get the contents of the file.
      $contents = file_get_contents($installdir->getPath() . "/" . $newname);
      // Replace "node_complete" within the file.
      $result = preg_replace('/node_complete/', 'node', $contents);
      // Replace "entity_complete:node" within the file.
      $result = preg_replace('/entity_complete:node/', 'entity:node', $result);
      // Replace "complete" in the label.
      $result = preg_replace_callback("/label: 'Node complete (?<name>\(.*?\)')/", function ($matches)
        {
          return "label: 'Node " . $matches['name'];
        },
        $result);
      // Replace media_wysiwyg_filter with the custom process_media_with_captions plugin.
      $result = preg_replace('/plugin: media_wysiwyg_filter/', 'plugin: process_media_with_captions', $result);
      // Save the altered file
      file_put_contents($installdir->getPath() . "/" . $newname, $result);
      // Print confirmation message to terminal.
      printf("\e[42m[success]\e[49m Moved and updated $newname." . PHP_EOL);
      // Remove file extension
      $configname = preg_replace('/.yml/', '', $name);
      // Prepare variable for bash shell
      $configname = escapeshellarg($configname);
      // Execute bash script with variable
      shell_exec("/var/www/html/web/modules/custom/watts_migrate/config/install/script.sh $configname");
    }
  }
}
// Print error message if nothing was changed.
if ($mycount == 0) {
    printf("\e[41m[error]\e[49m No \"custom_node\" yaml files are available in the /config directory to be converted." . PHP_EOL);
}