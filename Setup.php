<?php

namespace Kieran\IdentitySteam;

use XF\Db\Schema\Create;
use XF\Db\Schema\Alter;

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

		$this->schemaManager()->alterTable('xf_user_group', function (Alter $table)
		{
			$table->addColumn('chat_rank', 'varchar', 250)->nullable()->after('banner_text');
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
                $table->addColumn('chat_rank', 'varchar', 255);
                $table->addPrimaryKey('id');
                $table->addUniqueKey(['user_id', 'identity'], 'identitysteam_users_user_id_identity');
            });
		} else if (!$this->schemaManager()->columnExists('xf_kieran_identitysteam_users', 'chat_rank')) {
			$this->schemaManager()->alterTable('xf_kieran_identitysteam_users', function (Alter $table)
			{
				$table->addColumn('chat_rank', 'varchar', 255)->nullable()->after('immunity');
			});
		}

		if (!$this->schemaManager()->columnExists('xf_user_group', 'chat_rank')) {
            $this->schemaManager()->alterTable('xf_user_group', function (Alter $table)
			{
				$table->addColumn('chat_rank', 'varchar', 50)->nullable()->after('banner_text');
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

		$this->schemaManager()->alterTable('xf_user_group', function (Alter $table)
		{
			$table->dropColumns(['chat_rank']);
		});
	}

	public function getIdentityTypeRepo()
	{
		return $this->app->repository('Kieran\Identity:IdentityType');
	}
}