<?php

final class PhabricatorAuthOneTimeLoginController
  extends PhabricatorAuthController {

  private $id;
  private $key;
  private $emailID;
  private $linkType;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->linkType = $data['type'];
    $this->id = $data['id'];
    $this->key = $data['key'];
    $this->emailID = idx($data, 'emailID');
  }

  public function processRequest() {
    $request = $this->getRequest();

    if ($request->getUser()->isLoggedIn()) {
      return $this->renderError(
        pht('You are already logged in.'));
    }

    $target_user = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$target_user) {
      return new Aphront404Response();
    }

    // NOTE: As a convenience to users, these one-time login URIs may also
    // be associated with an email address which will be verified when the
    // URI is used.

    // This improves the new user experience for users receiving "Welcome"
    // emails on installs that require verification: if we did not verify the
    // email, they'd immediately get roadblocked with a "Verify Your Email"
    // error and have to go back to their email account, wait for a
    // "Verification" email, and then click that link to actually get access to
    // their account. This is hugely unwieldy, and if the link was only sent
    // to the user's email in the first place we can safely verify it as a
    // side effect of login.

    // The email hashed into the URI so users can't verify some email they
    // do not own by doing this:
    //
    //  - Add some address you do not own;
    //  - request a password reset;
    //  - change the URI in the email to the address you don't own;
    //  - login via the email link; and
    //  - get a "verified" address you don't control.

    $target_email = null;
    if ($this->emailID) {
      $target_email = id(new PhabricatorUserEmail())->loadOneWhere(
        'userPHID = %s AND id = %d',
        $target_user->getPHID(),
        $this->emailID);
      if (!$target_email) {
        return new Aphront404Response();
      }
    }

    $engine = new PhabricatorAuthSessionEngine();
    $token = $engine->loadOneTimeLoginKey(
      $target_user,
      $target_email,
      $this->key);

    if (!$token) {
      return $this->newDialog()
        ->setTitle(pht('Unable to Login'))
        ->setShortTitle(pht('Login Failure'))
        ->appendParagraph(
          pht(
            'The login link you clicked is invalid, out of date, or has '.
            'already been used.'))
        ->appendParagraph(
          pht(
            'Make sure you are copy-and-pasting the entire link into '.
            'your browser. Login links are only valid for 24 hours, and '.
            'can only be used once.'))
        ->appendParagraph(
          pht('You can try again, or request a new link via email.'))
        ->addCancelButton('/login/email/', pht('Send Another Email'));
    }

    if ($request->isFormPost()) {
      // If we have an email bound into this URI, verify email so that clicking
      // the link in the "Welcome" email is good enough, without requiring users
      // to go through a second round of email verification.

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        // Nuke the token and all other outstanding password reset tokens.
        // There is no particular security benefit to destroying them all, but
        // it should reduce HackerOne reports of nebulous harm.

        PhabricatorAuthTemporaryToken::revokeTokens(
          $target_user,
          array($target_user->getPHID()),
          array(
            PhabricatorAuthSessionEngine::ONETIME_TEMPORARY_TOKEN_TYPE,
            PhabricatorAuthSessionEngine::PASSWORD_TEMPORARY_TOKEN_TYPE,
          ));

        if ($target_email) {
          id(new PhabricatorUserEditor())
            ->setActor($target_user)
            ->verifyEmail($target_user, $target_email);
        }
      unset($unguarded);

      $next = '/';
      if (!PhabricatorPasswordAuthProvider::getPasswordProvider()) {
        $next = '/settings/panel/external/';
      } else if (PhabricatorEnv::getEnvConfig('account.editable')) {

        // We're going to let the user reset their password without knowing
        // the old one. Generate a one-time token for that.
        $key = Filesystem::readRandomCharacters(16);

        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
          id(new PhabricatorAuthTemporaryToken())
            ->setObjectPHID($target_user->getPHID())
            ->setTokenType(
              PhabricatorAuthSessionEngine::PASSWORD_TEMPORARY_TOKEN_TYPE)
            ->setTokenExpires(time() + phutil_units('1 hour in seconds'))
            ->setTokenCode(PhabricatorHash::digest($key))
            ->save();
        unset($unguarded);

        $next = (string)id(new PhutilURI('/settings/panel/password/'))
          ->setQueryParams(
            array(
              'key' => $key,
            ));

        $request->setTemporaryCookie(PhabricatorCookies::COOKIE_HISEC, 'yes');
      }

      PhabricatorCookies::setNextURICookie($request, $next, $force = true);

      return $this->loginUser($target_user);
    }

    // NOTE: We need to CSRF here so attackers can't generate an email link,
    // then log a user in to an account they control via sneaky invisible
    // form submissions.

    switch ($this->linkType) {
      case PhabricatorAuthSessionEngine::ONETIME_WELCOME:
        $title = pht('Welcome to Phabricator');
        break;
      case PhabricatorAuthSessionEngine::ONETIME_RECOVER:
        $title = pht('Account Recovery');
        break;
      case PhabricatorAuthSessionEngine::ONETIME_USERNAME:
      case PhabricatorAuthSessionEngine::ONETIME_RESET:
      default:
        $title = pht('Login to Phabricator');
        break;
    }

    $body = array();
    $body[] = pht(
      'Use the button below to log in as: %s',
      phutil_tag('strong', array(), $target_user->getUsername()));

    if ($target_email && !$target_email->getIsVerified()) {
      $body[] = pht(
        'Logging in will verify %s as an email address you own.',
        phutil_tag('strong', array(), $target_email->getAddress()));

    }

    $body[] = pht(
      'After logging in you should set a password for your account, or '.
      'link your account to an external account that you can use to '.
      'authenticate in the future.');

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->addSubmitButton(pht('Login (%s)', $target_user->getUsername()))
      ->addCancelButton('/');

    foreach ($body as $paragraph) {
      $dialog->appendParagraph($paragraph);
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
