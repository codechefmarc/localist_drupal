<?php

declare(strict_types=1);

namespace Drupal\localist_drupal\Plugin\migrate_plus\data_parser;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate_plus\DataParserPluginInterface;
use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;

/**
 * Obtain JSON data for migration.
 *
 * @DataParser(
 *   id = "localist_json",
 *   title = @Translation("Localist JSON")
 * )
 */
class LocalistJson extends Json implements ContainerFactoryPluginInterface, DataParserPluginInterface {

  /**
   * Iterator over the JSON data.
   */
  protected ?\ArrayIterator $iterator = NULL;

  /**
   * The currently saved source url (as a string).
   *
   * @var string
   */
  protected $currentUrl;

  /**
   * The active url's source data.
   *
   * @var array
   */
  protected $sourceData;

  /**
   * Retrieves the JSON data and returns it as an array.
   *
   * @param string $url
   *   URL of a JSON feed.
   * @param string|int|bool $item_selector
   *   Selector within the data content at which useful data is found.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function getSourceData(string $url, string|int|bool $item_selector = '') {
    // Use cached source data if this is the first request or URL is same as the
    // last time we made the request.
    if ($this->currentUrl != $url || !$this->sourceData) {
      $response = $this->getDataFetcherPlugin()->getResponseContent($url);

      // Convert objects to associative arrays.
      $this->sourceData = json_decode($response, TRUE);

      // If json_decode() has returned NULL, it might be that the data isn't
      // valid utf8 - see http://php.net/manual/en/function.json-decode.php#86997.
      if (!$this->sourceData) {
        $utf8response = mb_convert_encoding($response, 'UTF-8');
        $this->sourceData = json_decode($utf8response, TRUE);
      }
      $this->currentUrl = $url;
    }

    // Handle Localist paging.
    $numPages = $this->sourceData['page']['total'];
    $i = 1;
    $reformattedSource = [];

    while ($i <= $numPages) {
      $url = "$url&page=$i";
      $response = $this->getDataFetcherPlugin()->getResponseContent($url);
      $this->sourceData = json_decode($response, TRUE, 512, JSON_THROW_ON_ERROR);

      // Backwards-compatibility for depth selection.
      if (is_numeric($this->itemSelector)) {
        return $this->selectByDepth($this->sourceData, (int) $item_selector);
      }

      // If the item_selector is an empty string, return all.
      if ($item_selector === '') {
        return $this->sourceData;
      }

      // Treat source data as a single object to import if itemSelector is
      // explicitly set to FALSE.
      if ($item_selector === FALSE) {
        return [$this->sourceData];
      }

      // Otherwise, we're using xpath-like selectors.
      $selectors = explode('/', trim($item_selector, '/'));
      $source_data = $this->sourceData;
      foreach ($selectors as $selector) {
        // If the item_selector is missing, return an empty array.
        if (!isset($source_data[$selector])) {
          return [];
        }
        $source_data = $source_data[$selector];
      }

      foreach ($source_data as $data) {
        $eventInstance = $data['event']['event_instances'][0]['event_instance'];
        $parentEventId = $data['event']['id'];
        // Only add dates from event instances that match the parent event ID.
        if ($parentEventId == $eventInstance['event_id']) {
          $startDate = strtotime($eventInstance['start']);
          // If no end date, event is all day - set end time at start + 23h59m.
          $endDate = $eventInstance['end'] ? strtotime($eventInstance['end']) : $startDate + 86340;
          // If no end date, event is all day - set duration to 1439m.
          $duration = $eventInstance['end'] ? ($endDate - $startDate) / 60 : 1439;
          $dates[$parentEventId][] = [
            'value' => $startDate,
            'end_value' => $endDate,
            'duration' => $duration,
          ];
        }

        // Handle URLs from Localist.
        $this->addUrlProtocol($data['event']);

        $reformattedSource[$parentEventId] = [
          'localist_data' => $data['event'],
          'instances' => $dates[$parentEventId],
        ];

      }

      $i++;

    }

    return $reformattedSource;
  }

  /**
   * Add protocol to URLs from Localist if they don't have one.
   *
   * @param array $eventData
   *   The array of event data from Localist.
   */
  private function addUrlProtocol(&$eventData) {
    foreach ($eventData as $key => $data) {
      // Only look at keys that end with 'url'.
      if (str_ends_with($key, 'url')) {
        if (empty($data)) {
          return;
        }
        if (!filter_var($data, FILTER_VALIDATE_URL) && strpos($data, 'http') === FALSE) {
          $eventData[$key] = 'https://' . $data;
        }
      }
    }
  }

}
