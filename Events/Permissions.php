<?php

namespace Kieran\IdentitySteam\Events;

use XF\Mvc\Entity\Entity;
use XF\Entity\User;

class Permissions {
 
    private static $users = [];
    private static $groups = [];

    private static $flags = [
        ["reservation", "a"],
        ["generic", "b"],
        ["kick", "c"],
        ["ban", "d"],
        ["unban", "e"],
        ["slay", "f"],
        ["changemap", "g"],
        ["cvar", "h"],
        ["config", "i"],
        ["chat", "j"],
        ["vote", "k"],
        ["password", "l"],
        ["rcon", "m"],
        ["cheats", "n"],
        ["root", "z"],
        ["custom1", "o"],
        ["custom2", "p"],
        ["custom3", "q"],
        ["custom4", "r"],
        ["custom5", "s"],
        ["custom6", "t"]
    ];

	public static function postSaveUser(Entity $entity) {
        self::rebuildUser($entity);
    }

	public static function postSaveGroup(Entity $entity) {
        $users = self::getUsersByGroup($entity->user_group_id);
        foreach($users as $user) {
            self::rebuildUser($user);
        }    
    }

	public static function postSave(Entity $entity) {
        if ($entity->user_id > 0 && !isset(self::$users[$entity->user_id])) {
            $user = $this->em->find('XF:User', $entity->user_id);
            self::rebuildUser($user);
        }

        if ($entity->user_group_id > 0 && !isset(self::$groups[$entity->user_group_id])) {
            $users = self::getUsersByGroup($entity->user_group_id);
            foreach($users as $user) {
                self::rebuildUser($user);
            }                
        }
    }

    private static function rebuildUser(User $user) {

        if (isset(self::$users[$user->user_id])) {
            return;
        }

        self::$users[$user->user_id] = true;

        $type = self::getIdentityTypeRepo()->findIdentityType('steam');

        if ($type == null) {
            return;
        }

        $identities = \XF::repository('Kieran\Identity:Identity')->findIdentityByUserIdByType($user->user_id, $type->identity_type_id);

        foreach ($identities as $identity) {
            
            $userAdmin = \XF::finder('Kieran\IdentitySteam:User')
                ->where('identity', $identity->identity_value)
                ->where('user_id', $user->user_id)
                ->fetchOne();
                
            if (!$userAdmin) {
                $userAdmin = \XF::em()->create('Kieran\IdentitySteam:User');
                $userAdmin->identity = $identity->identity_value;
                $userAdmin->user_id = $user->user_id;
            }

            $userAdmin->name = $user->username;

            if ($identity->status == 1) {
                $userFlags = '';

                foreach(self::$flags as $flag) {
                    if ($user->hasPermission('identitySteam', $flag[0])) {
                        $userFlags .= $flag[1];
                    }
                }

                $userAdmin->flags = $userFlags;
                $userAdmin->immunity = $user->hasPermission('identitySteam', 'immunity');
            } else {
                $userAdmin->flags = "";
                $userAdmin->immunity = 0;
            }
            
            $userAdmin->save();
        }
    }

    private static function getUsersByGroup($group_id) {
        $finder = \XF::finder('XF:User');
        return $finder
            ->whereSql('FIND_IN_SET(' . $finder->quote($group_id) . ', secondary_group_ids) OR user_group_id=' . $finder->quote($group_id))
            ->fetch();
    }

    private static function getIdentityTypeRepo() {
		return \XF::repository('Kieran\Identity:IdentityType');
    }
}