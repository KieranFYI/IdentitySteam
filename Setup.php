<?php

namespace Kieran\IdentitySteam;

use XF\Db\Schema\Create;

class Setup extends \XF\AddOn\AbstractSetup
{
	use \XF\AddOn\StepRunnerInstallTrait;
	use \XF\AddOn\StepRunnerUpgradeTrait;
	use \XF\AddOn\StepRunnerUninstallTrait;
	
	public function installStep1(array $stepParams = [])
	{
		$this->getIdentityTypeRepo()->addIdentityType('steam', 'Steam', 'Kieran\\IdentitySteam:IdentitySteam');
	}

	public function upgrade(array $stepParams = [])
	{
	}
	
	public function uninstall(array $stepParams = [])
	{
		$type = $this->getIdentityTypeRepo()->findIdentityType('steam');

		if ($type) {
			$type->delete();
		}
	}

	public function getIdentityTypeRepo()
	{
		return $this->app->repository('Kieran\Identity:IdentityType');
	}
}