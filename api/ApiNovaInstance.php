<?
class ApiNovaInstance extends ApiBase {
	var $userLDAP;
	var $userNova;
	var $params;

	public function canExecute() {
		if ( ! $this->userLDAP->exists() ) {
			$this->dieUsage( 'No credentials found for your account.', 'openstackmanager-nonovacred' );
		}
		if ( ! $this->userLDAP->inProject( $this->params['project'] ) ) {
			$this->dieUsage( 'User account is not in the project specified.', 'openstackmanager-noaccount' );
		}
		if ( ! $this->userLDAP->inRole( 'projectadmin', $this->params['project'] ) ) {
			$this->dieUsage( 'User account is not in the projectadmin role.', 'openstackmanager-needrole' );
		}
	}

	function execute() {
		$this->params = $this->extractRequestParams();

		$this->userLDAP = new OpenStackNovaUser();
		$this->canExecute();
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$this->userNova->setProject( $this->params['project'] );
		$this->userNova->setRegion( $this->params['region'] );

		switch( $this->params['subaction'] ) {
		case 'reboot':
			$success = $this->userNova->rebootInstance( $this->params['instanceid'] );
			if ( ! $success ) {
				$this->dieUsage( 'Failed to reboot instance.', 'openstackmanager-rebootinstancefailed' );
			}
			$instance = $this->userNova->getInstance( $this->params['instanceid'] );
			if ( $instance ) {
				$this->getResult()->addValue( null, $this->getModuleName(), array ( 'instancestate' => $instance->getInstanceState() ) );
			}
			break;
		case 'consoleoutput':
			$output = $this->userNova->getConsoleOutput( $this->params['instanceid'] );
			$this->getResult()->addValue( null, $this->getModuleName(), array ( 'consoleoutput' => $output ) );
			break;
		}
	}

	// Face parameter.
	public function getAllowedParams() {
		return array(
			'subaction' => array (
				ApiBase::PARAM_TYPE => array(
					'reboot',
					'consoleoutput',
				),
				ApiBase::PARAM_REQUIRED => true
			),
			'instanceid' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'project' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'region' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'token' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
		);
	}
 
	public function getParamDescription() {
		return array(
			'subaction' => 'The subaction to perform.',
			'instanceid' => 'The Nova instance ID to perform a subaction on',
			'project' => 'The project in which the instance exists',
			'region' => 'The region in which the instance exists',
			'token' => 'An edit token',
		);
	}

	public function getDescription() {
		return 'Perform actions on instances.';
	}

	public function getExamples() {
		return array(
			'api.php?action=novainstance&subaction=reboot&instanceid=eb195097-8539-4e66-b0b5-be8347d8caec&project=testing&region=mars&token=123ABC'
			=> 'Reboot instance id eb195097-8539-4e66-b0b5-be8347d8caec in project testing in region mars',
			'api.php?action=novainstance&subaction=consoleoutput&instanceid=eb195097-8539-4e66-b0b5-be8347d8caec&project=testing&region=mars&token=123ABC'
			=> 'Display console output for instance id eb195097-8539-4e66-b0b5-be8347d8caec in project testing in region mars',
		);
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return true;
	}

	public function getTokenSalt() {
		return '';
	}

	public function mustBePosted() {
		return true;
	}

}
