<?php

namespace Drupal\queue_throttle\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\queue_throttle\ThrottleRate;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\queue_throttle\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The queue plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager
   *   The queue plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, QueueWorkerManagerInterface $queue_manager) {
    parent::__construct($config_factory);
    $this->queueManager = $queue_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.queue_worker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'queue_throttle_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['queue_throttle.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('queue_throttle.settings');

    $form['intro'] = [
      '#type' => 'markup',
      '#prefix' => '<p>',
      '#markup' => $this->t('Enabling throttling for a queue will disable it from running on the default cron. To schedule throttled queue processing, setup a new cron job executing the drush command that ships with this module.'),
      '#suffix' => '</p>',
    ];

    // Grab the defined cron queues.
    foreach (array_keys($this->queueManager->getDefinitions()) as $queueName) {

      $form[$queueName] = [
        '#type' => 'details',
        '#title' => $queueName,
        '#open' => $config->get($queueName . '.enabled'),
      ];

      $form[$queueName][$queueName . '_enabled'] = [
        '#title' => $this->t('Enable queue throttling'),
        '#type' => 'checkbox',
        '#default_value' => $config->get($queueName . '.enabled'),
      ];

      $form[$queueName][$queueName . '_time'] = [
        '#title' => $this->t('Queue processing time (max)'),
        '#type' => 'number',
        '#default_value' => $config->get($queueName . '.time') ?: 60,
        '#min' => 0,
        '#max' => 240,
        '#field_suffix' => $this->t('seconds'),
        '#states' => [
          'visible' => [
            ':input[name="' . $queueName . '_enabled"]' => ['checked' => TRUE],
          ],
          'required' => [
            ':input[name="' . $queueName . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form[$queueName]['throttle'] = [
        '#title' => $this->t('Queue items per unit'),
        '#type' => 'fieldset',
        '#states' => [
          'visible' => [
            ':input[name="' . $queueName . '_enabled"]' => ['checked' => TRUE],
          ],
          'required' => [
            ':input[name="' . $queueName . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form[$queueName]['throttle'][$queueName . '_items'] = [
        '#title' => $this->t('Queue items (max)'),
        '#type' => 'number',
        '#default_value' => $config->get($queueName . '.items'),
        '#min' => 0,
        '#states' => [
          'visible' => [
            ':input[name="' . $queueName . '_enabled"]' => ['checked' => TRUE],
          ],
          'required' => [
            ':input[name="' . $queueName . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form[$queueName]['throttle'][$queueName . '_unit'] = [
        '#title' => $this->t('Unit'),
        '#type' => 'select',
        '#default_value' => $config->get($queueName . '.unit'),
        '#options' => array_combine(ThrottleRate::getUnits(), ThrottleRate::getUnits()),
        '#states' => [
          'visible' => [
            ':input[name="' . $queueName . '_enabled"]' => ['checked' => TRUE],
          ],
          'required' => [
            ':input[name="' . $queueName . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('queue_throttle.settings');
    foreach (array_keys($this->queueManager->getDefinitions()) as $queueName) {
      $config->set($queueName . '.enabled', (bool) $form_state->getValue($queueName . '_enabled'));
      $config->set($queueName . '.time', (int) $form_state->getValue($queueName . '_time'));
      $config->set($queueName . '.items', (int) $form_state->getValue($queueName . '_items'));
      $config->set($queueName . '.unit', $form_state->getValue($queueName . '_unit'));
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
