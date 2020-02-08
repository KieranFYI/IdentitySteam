<?php

namespace Kieran\IdentitySteam\XF\Admin\Controller;

class UserGroup extends XFCP_UserGroup
{
	protected function userGroupSaveProcess(\XF\Entity\UserGroup $userGroup)
	{
		$form = parent::userGroupSaveProcess($userGroup);

		$input = $this->filter([
			'chat_rank' => 'str'
		]);

		$form->basicEntitySave($userGroup, $input);

		return $form;
	}
}