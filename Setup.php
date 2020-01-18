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
        
        $this->schemaManager()->createTable('xf_kieran_identitysteam_users', function(Create $table)
        {
            $table->addColumn('id', 'int')->autoIncrement();
			$table->addColumn('identity', 'bigint', 64);
            $table->addColumn('user_id', 'int');
            $table->addColumn('name', 'varchar', 65);
            $table->addColumn('flags', 'varchar', 30);
            $table->addColumn('immunity', 'int');
            $table->addPrimaryKey('id');
            $table->addUniqueKey(['user_id', 'identity'], 'identitysteam_users_user_id_identity');
        });
	}

	public function upgrade(array $stepParams = [])
	{

        if (!$this->schemaManager()->tableExists('xf_kieran_identitysteam_users')) {
            $this->schemaManager()->createTable('xf_kieran_identitysteam_users', function(Create $table)
            {
                $table->addColumn('id', 'int')->autoIncrement();
                $table->addColumn('identity', 'bigint', 64);
                $table->addColumn('user_id', 'int');
                $table->addColumn('name', 'varchar', 65);
                $table->addColumn('flags', 'varchar', 30);
                $table->addColumn('immunity', 'int');
                $table->addPrimaryKey('id');
                $table->addUniqueKey(['user_id', 'identity'], 'identitysteam_users_user_id_identity');
            });
        }
	}
	
	public function uninstall(array $stepParams = [])
	{
		$type = $this->getIdentityTypeRepo()->findIdentityType('steam');

		if ($type) {
			$type->delete();
		}
		$this->schemaManager()->dropTable('xf_kieran_identitysteam_users');
	}

	public function getIdentityTypeRepo()
	{
		return $this->app->repository('Kieran\Identity:IdentityType');
	}
}