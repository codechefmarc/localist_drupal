<?php

declare(strict_types=1);

namespace Drupal\localist_drupal\Drush\Generators;

use Drupal\Core\Extension\ModuleHandlerInterface;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Drush\Commands\AutowireTrait;

#[Generator(
  name: 'localist_drupal:localist_event_migration',
  description: 'Generates a migration yml file',
  aliases: ['localist_event_migration'],
  templatePath: __DIR__,
  type: GeneratorType::MODULE_COMPONENT,
)]
class LocalistEventMigrationGenerator extends BaseGenerator {

  use AutowireTrait;

  /**
   * Illustrates how to inject a dependency into a Generator.
   */
  public function __construct(
      protected ModuleHandlerInterface $moduleHandler,
    ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->createInterviewer($vars);
    $vars['machine_name'] = $ir->askMachineName();
    $vars['migration_id'] = $ir->ask('Migration ID machine name', 'custom_localist_events');
    $vars['label'] = mb_convert_case(str_replace('_', ' ', $vars['migration_id']), MB_CASE_TITLE, 'UTF-8');
    $vars['target_bundle'] = $ir->ask('Target content type machine name', 'events');
    $vars['dest_desc_field'] = $ir->ask('Destination event description field machine name', 'body');
    $vars['dest_smart_date_field'] = $ir->ask('Destination smart date field machine name', 'field_dates');
    $assets->addFile('migrations/{migration_id}.yml', 'event-generator-template.twig');
  }

}
