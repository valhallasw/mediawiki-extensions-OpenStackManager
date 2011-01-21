<?php

class OpenStackNovaRole {

	var $rolename;
	var $roleDN;
	var $roleInfo;
	var $global;

	function __construct( $rolename, $project=null ) {
		$this->rolename = $rolename;
		$this->project = $project;
		if ( $this->project ) {
			$this->global = false;
		} else {
			$this->global = true;
		}
		$this->connect();
		$this->fetchRoleInfo();
	}

	function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
	}

	function fetchRoleInfo() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPGlobalRoles;

		if ( $this->global ) {
			if ( isset ( $wgOpenStackManagerLDAPGlobalRoles["$this->rolename"] ) ) {
				$dn = $wgOpenStackManagerLDAPGlobalRoles["$this->rolename"];
			} else {
				# This condition would be a bug...
				$dn = '';
			}
		} else {
			$dn = $this->project->projectDN;
		}
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $dn, '(cn=' . $this->rolename . ')' );
		$this->roleInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		$this->roleDN = $this->roleInfo[0]['dn'];
	}

	function getRoleName() {
		return $this->rolename;
	}

	function getMembers() {
		$members = array();
		if ( isset( $this->roleInfo[0]['member'] ) ) {
			$memberdns = $this->roleInfo[0]['member'];
			array_shift( $memberdns );
			foreach ( $memberdns as $memberdn ) {
				$member = explode( '=', $memberdn );
				$member = explode( ',', $member[1] );
				$member = $member[0];
				$members[] = $member;
			}
		}

		return $members;
	}

	function deleteMember( $username ) {
		global $wgAuth;

		if ( isset( $this->roleInfo[0]['member'] ) ) {
			$members = $this->roleInfo[0]['member'];
			array_shift( $members );
			$user = new OpenStackNovaUser( $username );
			if ( ! $user->userDN ) {
				$wgAuth->printDebug( "Failed to find userDN in deleteMember", NONSENSITIVE );
				return false;
			}
			$index = array_search( $user->userDN, $members );
			if ( $index === false ) {
				$wgAuth->printDebug( "Failed to find userDN in member list", NONSENSITIVE );
				return false;
			}
			unset( $members[$index] );
			$values['member'] = array();
			foreach ( $members as $member ) {
				$values['member'][] = $member;
			}
			wfSuppressWarnings();
			$success = ldap_modify( $wgAuth->ldapconn, $this->roleDN, $values );
			wfRestoreWarnings();
			if ( $success ) {
				$wgAuth->printDebug( "Successfully removed $user->userDN from $this->roleDN", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $user->userDN from $this->roleDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
	}

	function addMember( $username ) {
		global $wgAuth;

		$members = array();
		if ( isset( $this->roleInfo[0]['member'] ) ) {
			$members = $this->roleInfo[0]['member'];
			array_shift( $members );
		}
		$user = new OpenStackNovaUser( $username );
		if ( ! $user->userDN ) {
			$wgAuth->printDebug( "Failed to find userDN in addMember", NONSENSITIVE );
			return false;
		}
		$members[] = $user->userDN;
		$values['member'] = $members;
		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->roleDN, $values );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added $user->userDN to $this->roleDN", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $user->userDN to $this->roleDN", NONSENSITIVE );
			return false;
		}
	}

	static function getProjectRoleByName( $rolename, $project ) {
		$role = new OpenStackNovaRole( $rolename, $project );
		if ( $role->roleInfo ) {
			return $role;
		} else {
			return null;
		}
	}

	static function getGlobalRoleByName( $rolename ) {
		$role = new OpenStackNovaRole( $rolename );
		if ( $role->roleInfo ) {
			return $role;
		} else {
			return null;
		}
	}

	static function getAllGlobalRoles() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;
		global $wgOpenStackManagerLDAPGlobalRoles;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$roles = array();
		foreach ( array_keys( $wgOpenStackManagerLDAPGlobalRoles ) as $rolename ) {
			$role = new OpenStackNovaRole( $rolename );
			array_push( $roles, $role );
		}

		return $roles;
	}

	static function createRole( $rolename, $project ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPProjectBaseDN;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$role = array();
		$role['objectclass'][] = 'groupofnames';
		$role['cn'] = $rolename;
		$roledn = 'cn=' . $rolename . ',' . $project->projectDN;
		wfSuppressWarnings();
		$success = ldap_add( $wgAuth->ldapconn, $roledn, $role );
		wfRestoreWarnings();
		# TODO: If role addition fails, find a way to fail gracefully
		# Though, if the project was added successfully, it is unlikely
		# that role addition will fail.
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added role $rolename", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add role $rolename", NONSENSITIVE );
			return false;
		}
	}

}
