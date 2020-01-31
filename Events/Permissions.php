<?php

namespace Kieran\IdentitySteam\Events;

use XF;
use XF\App;
use XF\Entity\User;
use XF\Http\Response;
use XF\Mvc\Entity\Entity;

class Permissions {

    private static $usersToBuild = [];

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

    public static function postSaveEntity(Entity $entity) {
        $class = get_class($entity);

        $users = [];

        if (strcmp('Kieran\Identity\Entity\Identity', $class) === 0) {
            $users[] = $entity->User;
        }

        if (strcmp('XF\Entity\User', $class) === 0) {
            $users[] = $entity;
        }
        
        if (strcmp('XF\Entity\UserGroup', $class) === 0) {
            $users = array_merge($users, self::getUsersByGroup($entity->user_group_id));
        }
        
        if (strcmp('XF\Entity\PermissionEntry', $class) === 0) {
            if ($entity->user_id > 0) {
                $users[] = XF::em()->find('XF:User', $entity->user_id);
            }
    
            if ($entity->user_group_id > 0) {
                $users = array_merge($users, self::getUsersByGroup($entity->user_group_id));           
            }
        }

        foreach($users as $user) {
            if (!isset(self::$usersToBuild[$user->user_id])) {
                self::$usersToBuild[$user->user_id] = $user->user_id;
            }
        }
    }

    public static function rebuildUsers(App $app, Response &$response) {

        if (!count(self::$usersToBuild)) {
            return;
        }

        $type = self::getIdentityTypeRepo()->findIdentityType('steam');

        if ($type == null) {
            return;
        }

        $users = XF::db()->fetchAll('SELECT
			  u.user_id, u.username, pc.cache_value
			FROM
			  xf_user u
			INNER JOIN
			  xf_permission_combination pc
                ON
                  u.permission_combination_id = pc.permission_combination_id
            WHERE 
              u.user_id IN (' . implode(',', self::$usersToBuild) . ')
			ORDER BY
              u.username ASC;');

        foreach($users as $user) {

            $identities = $app->repository('Kieran\Identity:Identity')->findIdentityByUserIdByType($user['user_id'], $type->identity_type_id);
            $cache_value = json_decode($user['cache_value'], true);

            foreach ($identities as $identity) {
                
                $userAdmin = $app->finder('Kieran\IdentitySteam:User')
                    ->where('identity', $identity->identity_value)
                    ->where('user_id', $user['user_id'])
                    ->fetchOne();
                    
                if (!$userAdmin) {
                    $userAdmin = $app->em()->create('Kieran\IdentitySteam:User');
                    $userAdmin->identity = $identity->identity_value;
                    $userAdmin->user_id = $user['user_id'];
                }

                $userAdmin->name = $user['username'];

                if ($identity->status == 1 && $cache_value['identitySteam']['sync']) {
                    $userFlags = '';

                    foreach(self::$flags as $flag) {
                        if ($cache_value['identitySteam'][$flag[0]]) {
                            $userFlags .= $flag[1];
                        }
                    }

                    $userAdmin->flags = $userFlags;
                    $userAdmin->immunity = $cache_value['identitySteam']['immunity'];
                } else {
                    $userAdmin->flags = "";
                    $userAdmin->immunity = 0;
                }
                
                $userAdmin->save();
            }
        }
    }

    private static function getUsersByGroup($group_id) {
        $finder = XF::finder('XF:User');
        return iterator_to_array($finder
            ->whereSql('FIND_IN_SET(' . $finder->quote($group_id) . ', secondary_group_ids) OR user_group_id=' . $finder->quote($group_id))
            ->fetch());
    }

    private static function getIdentityTypeRepo() {
		return XF::repository('Kieran\Identity:IdentityType');
    }
}