<?php

namespace Kieran\IdentitySteam\Entity;
    
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class User extends Entity
{

    public static function getStructure(Structure $structure)
	{
        $structure->table = 'xf_kieran_identitysteam_users';
        $structure->shortName = 'Kieran\IdentitySteam:User';
        $structure->primaryKey = 'id';
        $structure->columns = [
			'id' => ['type' => self::INT, 'autoIncrement' => true],
			'identity' => ['type' => self::BINARY, 'maxLength' => 64],
            'user_id' => ['type' => self::INT],
            'name' => ['type' => self::STR, 'maxLength' => 65],
            'flags' => ['type' => self::STR, 'maxLength' => 30],
            'immunity' => ['type' => self::INT],
            'chat_rank' => ['type' => self::STR],
        ];        
        return $structure;
    }
}