<?php

final class PhortuneCartViewController
  extends PhortuneCartController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $cart = id(new PhortuneCartQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needPurchases(true)
      ->executeOne();
    if (!$cart) {
      return new Aphront404Response();
    }

    $cart_box = $this->buildCartContents($cart);

    $charges = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withCartPHIDs(array($cart->getPHID()))
      ->execute();

    $charges_table = $this->buildChargesTable($charges);

    $account = $cart->getAccount();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Cart'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $cart_box,
        $charges_table,
      ),
      array(
        'title' => pht('Cart'),
      ));

  }
}
