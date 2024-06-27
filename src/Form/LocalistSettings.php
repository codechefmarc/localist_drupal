<?php

namespace Drupal\localist_drupal\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\localist_drupal\LocalistManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the manage Localist settings interface.
 */
class LocalistSettings extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Localist manager.
   *
   * @var \Drupal\localist_drupal\LocalistManager
   */
  protected $localistManager;

  /**
   * Constructs a SiteInformationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\localist_drupal\LocalistManager $localist_manager
   *   The Localist manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    LocalistManager $localist_manager,
  ) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->localistManager = $localist_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('localist_drupal.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'localist_drupal_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['localist_drupal.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('localist_drupal.settings');
    $groupsImported = $this->localistManager->getMigrationStatus('localist_groups') > 0;

    if (
      $config->get('enable_localist_sync') &&
      $config->get('localist_group') &&
      $groupsImported
      ) {
      $form['sync_now_button'] = [
        '#type' => 'markup',
        '#markup' => '<a class="button" href="/admin/localist/sync">Sync now</a>',
      ];
    }

    $form['enable_localist_sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Localist sync'),
      '#description' => $this->t('Once enabled, Localist data will sync events for the selected group roughly every hour.'),
      '#default_value' => $config->get('enable_localist_sync') ?: FALSE,
    ];

    $form['localist_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('Localist endpoint base URL') . $this->localistManager->getLabelStatus('localist_endpoint'),
      '#description' => $this->t('Ex: https://calendar.example.edu'),
      '#default_value' => $config->get('localist_endpoint') ?: 'https://calendar.example.edu',
      '#required' => TRUE,
    ];

    $form['groups'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Localist Group'),
      '#description' => $this->t('This module only imports Localist events from a specific group.'),
    ];

    $form['groups']['localist_group_migration'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group Migration') . $this->localistManager->getLabelStatus('localist_group_migration'),
      '#description' => $this->t('Machine name, i.e. localist_groups. See README.md on how to override with a custom group migration.'),
      '#default_value' => $config->get('localist_group_migration') ?: 'localist_groups',
      '#required' => TRUE,
    ];

    // Only show the group picker if the group migration has been run.
    if ($config->get('enable_localist_sync') && $groupsImported) {
      $term = $config->get('localist_group') ? $this->entityTypeManager->getStorage('taxonomy_term')->load($config->get('localist_group')) : NULL;

      $form['groups']['localist_group'] = [
        '#title' => $this->t('Group to sync events'),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_term',
        '#tags' => FALSE,
        '#default_value' => $term ?: NULL,
        '#selection_handler' => 'default',
        '#selection_settings' => [
          'target_bundles' => ['localist_groups'],
        ],
        '#required' => TRUE,
      ];
    }
    elseif ($config->get('enable_localist_sync') && !$groupsImported) {

      $form['groups']['no_group_sync_message'] = [
        '#type' => 'markup',
        '#markup' => '
          <p>Groups have not yet created. A selected group is required before synchronizing events.</p>
          <a class="button" href="/admin/localist/sync-groups">Create Groups</a>',
      ];
    }

    $form['localist_dependency_migrations'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Dependency Migrations'),
      '#default_value' => $config->get('localist_dependency_migrations') ?: NULL,
      '#description' => $this->t("Specify dependency migrations to run by machine name. Enter one migration per line. Ex: localist_places. See README.md on how to create additional migrations."),
    ];

    $form['localist_event_migration'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Migration'),
      '#description' => $this->t('Machine name, i.e. localist_events. This is the main migration of events and comes after the dependency migrations such as taxonomy term creation.'),
      '#default_value' => $config->get('localist_event_migration') ?: NULL,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('localist_drupal.settings');

    // Set the submitted configuration setting.
    $config->set('enable_localist_sync', $form_state->getValue('enable_localist_sync'))
      ->set('localist_endpoint', rtrim($form_state->getValue('localist_endpoint'), "/"))
      ->set('localist_group', $form_state->getValue('localist_group'))
      ->set('localist_group_migration', $form_state->getValue('localist_group_migration'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
