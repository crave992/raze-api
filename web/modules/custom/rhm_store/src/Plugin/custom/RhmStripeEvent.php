<?php

namespace Drupal\rhm_store\Plugin\custom;

class RhmStripeEvent {

  protected $stripe;

  public function __construct() {
   $stripe = $this->stripeSetKey();
  }

  /**
   * @return false
   */
  private function stripeSetKey() {
    $commerceStripeConfig = \Drupal::config('commerce_payment.commerce_payment_gateway.stripe')->get('configuration');
    $stripeKey            = $commerceStripeConfig['secret_key'];

    if (!$stripeKey) {
      return FALSE;
    }

    \Stripe\Stripe::setApiKey($stripeKey);
  }

  /**
   * @param $user
   * @return mixed|string
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function stripeGetCustomer($user) {

    $customer       = '';
    $lastResult     = NULL;
    $hasMoreResults = TRUE;
    while ($hasMoreResults) {
      $searchResults  = \Stripe\Customer::all([
        "email" => $user->getEmail(),
        "limit" => 100,
        "starting_after" => $lastResult
      ]);
      $hasMoreResults = $searchResults->has_more;
      foreach ($searchResults->autoPagingIterator() as $result) {
        $customer       = $result;
        $hasMoreResults = FALSE;
      }
      $lastResult = end($searchResults->data);
    }

    return $customer;
  }

  /**
   * @param $user
   * @param $stripeToken
   * @return false|\Stripe\Customer
   */
  public function stripeCreateCustomer($user, $stripeToken) {

    try {
      $customer = \Stripe\Customer::create([
        'name' => $user->getAccountName(),
        'email' => $user->getEmail(),
        'description' => 'Raze Customer',
        'source' => $stripeToken,
      ]);

      return $customer;
    }
    catch (\Exception $e) {
      \Drupal::logger('rhm_store')
        ->error('Exception (' . get_class($e) . '):' . $e->getMessage() . ' (ERR_CODE:1629950236)' . ' (' . __FILE__ . ':' . __LINE__ . ')');
      return FALSE;
    }
  }

  /**
   * @param $email
   * @param $stripeToken
   * @return false|\Stripe\Customer
   */
  public function stripeUpdateCustomer($email, $stripeToken) {

    try {
      $customer = \Stripe\Customer::update($stripeToken, ['email' => $email]);

      return $customer;
    }
    catch (\Exception $e) {
      \Drupal::logger('rhm_store')
        ->error('Exception (' . get_class($e) . '):' . $e->getMessage() . ' (ERR_CODE:1629950236)' . ' (' . __FILE__ . ':' . __LINE__ . ')');
      return FALSE;
    }
  }

  /**
   * @param $customer
   * @param $cardToken
   * @return \Stripe\AlipayAccount|\Stripe\BankAccount|\Stripe\BitcoinReceiver|\Stripe\Card|\Stripe\Source
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function stripeGetCard($customer, $cardToken, $format = null) {

    try {
      $source = \Stripe\Customer::retrieveSource($customer->id, $cardToken, []);

      if($source) {

        if($format === 'json') {
          $source = json_encode($source);
        }

        return $source;
      }
    }catch (\Exception $e) {
      \Drupal::logger('rhm_store')
        ->error('Exception (' . get_class($e) . '):' . $e->getMessage() . ' (ERR_CODE:1629950236)' . ' (' . __FILE__ . ':' . __LINE__ . ')');
      return FALSE;
    }
  }

  /**
   * @param $customer
   * @param $stripeToken
   * @return \Stripe\AlipayAccount|\Stripe\BankAccount|\Stripe\BitcoinReceiver|\Stripe\Card|\Stripe\Source
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function stripeCreateCard($customer, $stripeToken) {

    return \Stripe\Customer::createSource($customer->id, ['source' => $stripeToken]);
  }

  /**
   * @param $customer
   * @param $cardToken
   * @return \Stripe\AlipayAccount|\Stripe\BankAccount|\Stripe\BitcoinReceiver|\Stripe\Card|\Stripe\Source
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function stripeDeleteCard($customer, $cardToken) {

    try {
      $source = \Stripe\Customer::deleteSource($customer->id, $cardToken, []);

      if($source) {

        return $source;
      }
    }catch (\Exception $e) {
      \Drupal::logger('rhm_store')
        ->error('Exception (' . get_class($e) . '):' . $e->getMessage() . ' (ERR_CODE:1629950236)' . ' (' . __FILE__ . ':' . __LINE__ . ')');
      return FALSE;
    }
  }


  public function stripeUpdateCard($customer, $cardToken, $name, $month, $year) {

    try {
      $source = \Stripe\Customer::updateSource($customer->id, $cardToken, ['name' => $name, 'exp_month' => $month, 'exp_year' => $year]);

      if($source) {

        return ['status' => 200];
      }
    }catch (\Exception $e) {
      \Drupal::logger('rhm_store')
        ->error('Exception (' . get_class($e) . '):' . $e->getMessage() . ' (ERR_CODE:1629950236)' . ' (' . __FILE__ . ':' . __LINE__ . ')');

      return ['status' => 100, 'message' => $e->getMessage()];
    }
  }

  /**
   * @param $arg
   * @return false|\Stripe\Charge
   */
  public function stripeCreateCharge($arg) {

    try {
      $chargeObj = \Stripe\Charge::create($arg);
      return $chargeObj;
    }
    catch (\Stripe\Exception\CardException $e) {
      \Drupal::logger('rhm_store')
        ->error('StripeCardException:' . $e->getMessage() . ' (ERR_CODE:1629950280)' . ' (' . __FILE__ . ':' . __LINE__ . ')');
      return FALSE;
    }
    catch (\Exception $e) {
      \Drupal::logger('rhm_store')
        ->error('Exception (' . get_class($e) . '):' . $e->getMessage() . ' (ERR_CODE:1629950281)' . ' (' . __FILE__ . ':' . __LINE__ . ')');
      return FALSE;
    }
  }
}
