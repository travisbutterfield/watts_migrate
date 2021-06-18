<?php

namespace Drupal\watts_migrate;

use DOMDocument;

/**
 * Helpers to reformat/strip inline HTML.
 */
trait WattsWysiwygTextProcessingTrait {

  /**
   * Transform inline html in wysiwyg content.
   *
   * @param string $wysiwyg_content
   *   The content to search and transform inline html.
   *
   * @return string
   *   The original wysiwyg_content with transformed inline html.
   */
  public function processText($wysiwyg_content) {

    $pattern = '/';
    $pattern .= 'class=".*?(hidden).*?"|';
    $pattern .= '/';

    $matches = [];

    if (preg_match_all($pattern, $wysiwyg_content, $matches) > 0) {
      // Add a temp wrapper around the wysiwyg content.
      $wysiwyg_content = '<?xml encoding="UTF-8"><tempwrapper>' . $wysiwyg_content . '</tempwrapper>';

      // Load the content as a DOMDocument for more powerful transformation.
      $doc = new \DomDocument();
      libxml_use_internal_errors(TRUE);
      $doc->loadHtml($wysiwyg_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOENT);
      libxml_clear_errors();

      // Run the document through the transformation methods depending on the
      // matches identified.
      foreach ($matches as $key => $match_strings) {

        // Skip the first value, which contains the full pattern matches.
        if ($key > 0) {
          // Get unique values with array_unique, then remove any empty strings
          // with array_filter, and finally get the remaining match text.
          $filter = array_filter(array_unique($match_strings));
          $match = array_pop($filter);

          switch ($match) {
            case 'hidden':
              $doc = $this->transformBootstrapDisplayClasses($doc);
              break;
          }
        }
      }

      // Transform the document back to HTML.
      $wysiwyg_content = $doc->saveHtml();

      // Remove the temp wrapper and encoding from the output.
      return str_replace([
        '<?xml encoding="UTF-8">',
        '<tempwrapper>',
        '</tempwrapper>',
      ], '', $wysiwyg_content);
    }

    return $wysiwyg_content;
  }

  /**
   * Transform Bootstrap display classes.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with transformed classes.
   */
  private function transformBootstrapDisplayClasses(DOMDocument $doc) {

    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    $displaymatches = $xpath->query('//*[contains(@class, "hidden")]');

    if ($displaymatches) {
      foreach ($displaymatches as $key => $disp_wrapper) {
        // Replace bootstrap hidden/visible classes.
        $bs3_classes = [
          'hidden-sm hidden-md hidden-lg hidden-xl',
          'hidden-sm hidden-md hidden-lg',
          'hidden-xs',
          'hidden-sm',
          'hidden-md',
          'hidden-lg',
          'hidden-xl',
        ];


        $bs4_classes = [
          'd-block d-sm-none',
          'd-none d-sm-none',
          'd-none d-sm-block',
          'd-block d-sm-none d-md-block',
          'd-block d-md-none d-lg-block',
          'd-block d-lg-none d-xl-block',
          'd-block d-xl-none',
        ];

        $wrapper_classes = $disp_wrapper->attributes->getNamedItem('class')->value;
        $wrapper_classes = str_replace($bs3_classes, $bs4_classes, $wrapper_classes);
        $disp_wrapper->setAttribute('class', $wrapper_classes);

        // Replace the original element with the modified element in the doc.
        $disp_wrapper->parentNode->replaceChild($disp_wrapper, $displaymatches[$key]);
      }
    }

    return $doc;

  }

}
