<?php

final class PhabricatorFilesApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/file/';
  }

  public function getName() {
    return pht('Files');
  }

  public function getShortDescription() {
    return 'Store and Share Files';
  }

  public function getIconName() {
    return 'files';
  }

  public function getTitleGlyph() {
    return "\xE2\x87\xAA";
  }

  public function getFlavorText() {
    return pht('Blob store for Pokemon pictures.');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function canUninstall() {
    return false;
  }

  public function getRemarkupRules() {
    return array(
      new PhabricatorEmbedFileRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/F(?P<id>[1-9]\d*)' => 'PhabricatorFileShortcutController',
      '/file/' => array(
        '(query/(?P<key>[^/]+)/)?' => 'PhabricatorFileListController',
        'upload/' => 'PhabricatorFileUploadController',
        'dropupload/' => 'PhabricatorFileDropUploadController',
        'compose/' => 'PhabricatorFileComposeController',
        'comment/(?P<id>[1-9]\d*)/' => 'PhabricatorFileCommentController',
        'delete/(?P<id>[1-9]\d*)/' => 'PhabricatorFileDeleteController',
        'edit/(?P<id>[1-9]\d*)/' => 'PhabricatorFileEditController',
        'info/(?P<phid>[^/]+)/' => 'PhabricatorFileInfoController',
        'data/(?P<key>[^/]+)/(?P<phid>[^/]+)/(?P<token>[^/]+)/.*'
          => 'PhabricatorFileDataController',
        'data/(?P<key>[^/]+)/(?P<phid>[^/]+)/.*'
          => 'PhabricatorFileDataController',
        'proxy/' => 'PhabricatorFileProxyController',
        'xform/(?P<transform>[^/]+)/(?P<phid>[^/]+)/(?P<key>[^/]+)/'
          => 'PhabricatorFileTransformController',
        'uploaddialog/' => 'PhabricatorFileUploadDialogController',
        'download/(?P<phid>[^/]+)/' => 'PhabricatorFileDialogController',
      ),
    );
  }

}
