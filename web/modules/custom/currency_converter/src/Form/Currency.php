<?php

namespace Drupal\currency_converter\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Currency.
 */
class Currency extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'currency';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('currency_converter.settings');
    \Drupal::service('currency_converter.converter')->convert(100, 'AUD', 'USD');
    $allowed_values = $config->get('allowed_values') ?? [];
    $currencies = $config->get('currencies') ?? [];
    $table_options = [];
    $select_options = [];

    foreach ($allowed_values as $code) {
      if (isset($currencies[$code])) {
        $table_options[] = [
          'code' => $code,
          'curr' => $currencies[$code],
        ];
        $select_options[$code] = $code;
      }
    }

    $form['currency']['exchange']['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value:'),
    ];

    $form['currency']['exchange']['from'] = [
      '#type' => 'select',
      '#title' => $this->t('From:'),
      '#options' => $select_options,
    ];

    $form['currency']['exchange']['to'] = [
      '#type' => 'select',
      '#title' => $this->t('To:'),
      '#options' => $select_options,
    ];

    $form['currency']['exchange']['info'] = [
      '#type' => 'markup',
      '#markup' => '',
      '#prefix' => '<div id="currency-exchange-info">',
      '#suffix' => '</div>',
    ];

    $form['currency']['exchange']['action'] = [
      '#type' => 'button',
      '#value' => $this->t('Exchange'),
      '#ajax' => [
        'callback' => [$this, 'exchange'],
        'effect' => 'fade',
      ],
    ];

    $form['currency']['table'] = [
      '#type' => 'table',
      '#header' => [
        'code' => $this->t('Code'),
        'curr' => $this->t('Currency'),
      ],
      '#rows' => $table_options,
      '#empty' => $this->t('No currencies has been found.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) { }

  public function exchange(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $user_input = $form_state->getUserInput();
    $content = '<p>Something went wrong</p>';
    $result = \Drupal::service('currency_converter.converter')->convert($user_input['value'], $user_input['from'], $user_input['to']);

    if ($result) {
      $content = '<p>' . round($result, 2) . ' ' . $user_input['to'] . '</p>';
    }

    return $response->addCommand(new HtmlCommand('#currency-exchange-info', $content));
  }

}
