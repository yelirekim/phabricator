<?php

final class PhabricatorDaemonManagementStopWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('stop')
      ->setSynopsis(
        pht(
          'Stop all running daemons, or specific daemons identified by PIDs. '.
          'Use **phd status** to find PIDs.'))
      ->setArguments(
        array(
          array(
            'name' => 'graceful',
            'param' => 'seconds',
            'help' => pht(
              'Grace period for daemons to attempt a clean shutdown, in '.
              'seconds. Defaults to __15__ seconds.'),
            'default' => 15,
          ),
          array(
            'name' => 'pids',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $pids = $args->getArg('pids');
    $graceful = $args->getArg('graceful');
    return $this->executeStopCommand($pids, $graceful);
  }

}
