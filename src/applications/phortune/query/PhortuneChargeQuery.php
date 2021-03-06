<?php

final class PhortuneChargeQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $accountPHIDs;
  private $cartPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAccountPHIDs(array $account_phids) {
    $this->accountPHIDs = $account_phids;
    return $this;
  }

  public function withCartPHIDs(array $cart_phids) {
    $this->cartPHIDs = $cart_phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhortuneCharge();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT charge.* FROM %T charge %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $charges) {
    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs(mpull($charges, 'getAccountPHID'))
      ->execute();
    $accounts = mpull($accounts, null, 'getPHID');

    foreach ($charges as $key => $charge) {
      $account = idx($accounts, $charge->getAccountPHID());
      if (!$account) {
        unset($charges[$key]);
        continue;
      }
      $charge->attachAccount($account);
    }

    return $charges;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    $where[] = $this->buildPagingClause($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'charge.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'charge.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->accountPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'charge.accountPHID IN (%Ls)',
        $this->accountPHIDs);
    }

    if ($this->cartPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'charge.cartPHID IN (%Ls)',
        $this->cartPHIDs);
    }

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
