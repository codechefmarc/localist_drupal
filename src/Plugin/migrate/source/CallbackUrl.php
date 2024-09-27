<?php

namespace Drupal\localist_drupal\Plugin\migrate\source;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Plugin\migrate\source\Url;

/**
 * Source plugin to provide a list of source URLs.
 *
 * This plugin allows the user to specify file or stream-based content (where a
 * URL, including potentially a local filepath, points to a file containing data
 * to be migrated). The source plugin itself simply manages the (potentially
 * multiple) source URLs, and works with Http and File fetcher plugins for
 * retrieving the data.
 *
 * Available configuration keys:
 * - urls: a URL, a list of URLs, or a callback that returns a list of URLs
 * - data_fetcher_plugin: id of valid DataFetcherPluginInterface - eg. http, file
 * - data_parser_plugin: id of valid DataParserPluginInterface - eg. json, xml
 *
 * Examples:
 *
 * @code
 * source:
 *   plugin: url
 *   urls: https://example.com/jsonapi/node/article
 *   data_fetcher_plugin: http
 *   data_parser_plugin: json
 *   item_selector: data
 * @endcode
 *
 * The above will import articles from a single URL endpoint.
 *
 * @code
 * source:
 *   plugin: callback_url
 *   urls:
 *      callback: my_module_get_urls_to_import
 *   data_fetcher_plugin: http
 *   data_parser_plugin: json
 *   item_selector: data
 * @endcode
 *
 * The above will call the function my_module_get_urls_to_import() which should
 * return an array of URLs or files corresponding to all data sources to import.
 *
 * @MigrateSource(
 *   id = "callback_url"
 * )
 */
class CallbackUrl extends Url {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    // Support a callback to return arrays of URLs.
    if (!empty($configuration['urls']['callback'])) {
      if (is_callable($configuration['urls']['callback'])) {
        $configuration['urls'] = $configuration['urls']['callback']($migration);
      }
      else {
        $message = 'The URL callback function is not callable.';
        // \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
        throw new InvalidPluginDefinitionException($this->getPluginId(), $message);
      }
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * Return a string representing the source URLs.
   *
   * @return string
   *   Comma-separated list of URLs being imported.
   */
  public function __toString(): string {
    return implode(', ', $this->sourceUrls);
  }

  /**
   * Returns source URLs.
   *
   * @return array
   *   The list of source Urls.
   */
  public function getSourceUrls(): array {
    return $this->sourceUrls;
  }

}
