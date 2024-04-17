<?php

namespace Drupal\currency_converter\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;

class Converter {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  public function syncCurrencies(): void {
    $config = $this->configFactory->getEditable('currency_converter.settings');
    $api_key = $config->get('api_key');

    if (!$api_key) {
      return;
    }

    $client = new Client();
    $result = $client->request('GET', 'https://api.freecurrencyapi.com/v1/latest', [
      'headers' => ['apikey' => $api_key],
    ]);

    if ($result->getStatusCode() === 200) {
      $body = $result->getBody()->getContents();
      $data = Json::decode($body)['data'];
      $config->set('currencies', $data);
      $config->save();
    }
  }

  public function convert(float $value, string $from, string $to): float|NULL {
    $config = $this->configFactory->get('currency_converter.settings');
    $currencies = $config->get('currencies');

    if (!isset($currencies[$from]) || !isset($currencies[$to])) {
      return NULL;
    }

    $currency = $currencies[$from] / $currencies[$to];

    return $value / $currency;
  }

}
