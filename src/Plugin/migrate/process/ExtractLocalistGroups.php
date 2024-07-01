<?php

declare(strict_types=1);

namespace Drupal\localist_drupal\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Extracts groups from Localist events to prepare for entity reference.
 *
 * @MigrateProcessPlugin(
 *   id = "extract_localist_groups",
 *   handle_multiples = TRUE
 * )
 *
 * @code
 *   field_localist_groups:
 *     plugin: extract_localist_groups
 *     source: localist_groups
 * @endcode
 */
class ExtractLocalistGroups extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    $groupsArray = [];
    foreach ($value as $groupValues) {
      $groupsArray[] = $groupValues['id'];
    }

    return $groupsArray;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple(): bool {
    return TRUE;
  }

}
