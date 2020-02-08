<?php

namespace Kieran\IdentitySteam\Events;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;

class UserGroup
{
    public static function structure(Manager $em, Structure &$structure)
    {
    	$structure->columns['chat_rank'] = ['type' => Entity::STR, 'nullable' => true];
    }
}