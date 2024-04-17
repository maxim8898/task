<?php

namespace Drupal\currency_converter\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class configuration form for the currency_converter module.
 */
class Settings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'currency_converter.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'currency_converter_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('currency_converter.settings');
    $allowed_values = $config->get('allowed_values') ?? [];
    $items_count = $form_state->get('items_count');

    if (!array_key_exists('allowed_values', $form_state->getStorage())) {
      $form_state->set('allowed_values', $allowed_values);
    }

    if ($items_count === NULL) {
      $form_state->set('items_count', max(count($allowed_values), 0));
    }

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('API key for access to https://freecurrencyapi.com/'),
      '#default_value' => $config->get('api_key'),
    ];

    $wrapper_id = Html::getUniqueId('currency-allowed-values-wrapper');
    $form['allowed_values'] = [
      '#field_has_data' => !empty($allowed_values),
      '#allowed_values' => $allowed_values,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    $form['allowed_values']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Currency code'),
        $this->t('Delete'),
        $this->t('Weight'),
      ],
      '#attributes' => [
        'id' => 'allowed-values-order',
        'data-field-list-table' => TRUE,
        'class' => ['allowed-values-table'],
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
      '#attached' => [
        'library' => [
          'core/drupal.fieldListKeyboardNavigation',
          'field_ui/drupal.field_ui',
        ],
      ],
    ];

    $max = $form_state->get('items_count');

    for ($delta = 0; $delta <= $max; $delta++) {
      $form['allowed_values']['table'][$delta] = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
        '#weight' => $delta,
      ];
      $form['allowed_values']['table'][$delta]['item'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#weight' => -30,
        '#default_value' => $allowed_values[$delta] ?? '',
        '#required' => $delta === 0,
      ];

      $form['allowed_values']['table'][$delta]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => "remove_row_button__$delta",
        '#id' => "remove_row_button__$delta",
        '#delta' => $delta,
        '#submit' => [[$this, 'remove']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'editAjaxCallback'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
      ];
      $form['allowed_values']['table'][$delta]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
        '#title_display' => 'invisible',
        '#delta' => 50,
        '#default_value' => 0,
        '#attributes' => ['class' => ['weight']],
      ];
    }

    $form['allowed_values']['table']['#max_delta'] = $max;
    $form['allowed_values']['add_more_allowed_values'] = [
      '#type' => 'submit',
      '#name' => 'add_more_allowed_values',
      '#value' => $this->t('Add another item'),
      '#attributes' => [
        'class' => ['field-add-more-submit'],
        'data-field-list-button' => TRUE,
      ],
      '#limit_validation_errors' => [],
      '#submit' => [[$this, 'addMore']],
      '#ajax' => [
        'callback' => [$this, 'editAjaxCallback'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding a new item...'),
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('currency_converter.settings');
    $config->set('api_key', $form_state->getValue('api_key'));

    $allowed_values = [];
    foreach ($form_state->getValues()['table'] as $element) {
      if (!empty($element['item'])) {
        $allowed_values[] = $element['item'];
      }
    }

    $config->set('allowed_values', $allowed_values);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback for add/remove actions.
   */
  public function editAjaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['allowed_values'];
  }

  /**
   * Submit handler for the Add more button.
   */
  public function addMore(array &$form, FormStateInterface $form_state) {
    $items_count = $form_state->get('items_count');
    $form_state->set('items_count', $items_count + 1);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the Remove button.
   */
  public function remove(array &$form, FormStateInterface $form_state) {
    $allowed_values = $form_state->getStorage()['allowed_values'];
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $item_to_be_removed = $element['item']['#default_value'];
    $remaining_allowed_values = array_diff($allowed_values, [$item_to_be_removed]);
    $form_state->set('allowed_values', $remaining_allowed_values);

    $user_input = $form_state->getUserInput();
    NestedArray::unsetValue($user_input, $element['#parents']);
    $table_parents = $element['#parents'];
    array_pop($table_parents);
    $new_values = array_values(NestedArray::getValue($user_input, $table_parents));
    NestedArray::setValue($user_input, $table_parents, $new_values);

    $form_state->setUserInput($user_input);
    $form_state->set('items_count', $form_state->get('items_count') - 1);

    $form_state->setRebuild();
  }
}
