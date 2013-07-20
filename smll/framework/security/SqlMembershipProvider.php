<?php
namespace smll\framework\security;
use smll\framework\utils\ArrayList;

use smll\framework\security\interfaces\IMembershipProvider;
use smll\framework\security\interfaces\ICryptographer;
use smll\framework\settings\interfaces\ISettingsRepository;
use smll\framework\io\db\DB;
use smll\framework\security\MembershipUser;
use smll\framework\utils\Guid;
class SqlMembershipProvider implements IMembershipProvider {
	
	private $datastore = null;
	private $settings = null;
	
	/**
	 * [Inject(smll\framework\security\interfaces\ICryptographer)]
	 * @var ICryptographer
	 */
	private $encrypter = null;
	
	public function setEncrypter(ICryptographer $encrypter) {
		$this->encrypter = $encrypter;
	}
	
	public function __construct(ISettingsRepository $settings) {
		$this->settings = $settings;
		$connectionStrings = $settings->get('connectionStrings');
		$this->datastore = new DB($connectionStrings['Default']['connectionString']);
	}
	
	public function getAllUsers() {
		$users = new ArrayList();
		$this->datastore->clearCache();
		if($this->datastore->get('memberships')) {
			$allUsers = $this->datastore->get('memberships');
			foreach($allUsers as $u) {
				$user = new MembershipUser();
				$user->setProviderIdent($u->ident);
				$user->setProviderName($u->username);
				
				$users->add($user);
			}
		}
		
		return $users;
	}
	
	public function validateUser($username, $password) {
		$user = null;
		$this->datastore->where(array('username', '=', $username));
		if($this->datastore->get('memberships')) {
			$users = $this->datastore->getResult();
			if(count($users) == 1) {
				$user = $users[0];
			}
		}
		
		if(isset($user)) {
			return $this->encrypter->checkHash($password, $user->password);
		}
		
		return false;
	}
	
	
	/**
	 * 
	 * @param unknown $username
	 * @param unknown $password
	 * @param unknown $providerUserKey
	 * @return MembershipUser
	 */
	public function createUser($username, $password, $approved = false, $providerUserKey = null) {
		$user = null;
		
		if($providerUserKey == null) {
			$providerUserKey = Guid::createNew();
		}
		
		if($this->getUser($username) == null) {
			if($this->datastore->insert('memberships', array('username' => $username, 'password' => $this->encrypter->hash($password), 'ident' => $providerUserKey))) {
				$user = new MembershipUser();
				$user->setProviderIdent($providerUserKey);
				$user->setProviderName($username);
				
				$this->datastore->insert('users', array('application' => '#1', 'ident' => $providerUserKey, 'username' => $username, 'last_active_date' => date('Y-m-d H:i:s')));
			}
		} else {
			throw new MembershipUserExistsException();
		}
		return $user;
	}
	
	
	public function getUser($user) {
		
		$userObject = null;
		$this->datastore->clearCache();
		
		
		
		if($user instanceof Guid) {
			
			$this->datastore->where(array('ident', '=', $user));
		} else if(is_string($user)) {
			$this->datastore->where(array('username', '=', $user));
		}
		if(($users = $this->datastore->get('memberships')) != false) {
			if(isset($users)) {
				$userObject = new MembershipUser();
				$userObject->setProviderIdent($users[0]->ident);
				$userObject->setProviderName($users[0]->username);
			}
		}
		
		return $userObject;
	}
}