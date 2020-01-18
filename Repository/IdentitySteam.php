<?php

namespace Kieran\IdentitySteam\Repository;

use Kieran\Identity\Repository\IdentityTypeWrapper;
use Kieran\IdentitySteam\Libarys\OpenID;
use Kieran\Identity\Pub\Controller\Identity as IdentityController;

class IdentitySteam extends IdentityTypeWrapper
{

	public function actionAdd(IdentityController $controller, $returnURL) {

		$openId = new OpenID((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'], $returnURL);
		$openId->identity = 'https://steamcommunity.com/openid';
        return $controller->redirect($openId->authUrl());
	}

	public function actionValidate(IdentityController $controller, $returnURL) {
		$openId = new OpenID((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'], $returnURL);
			
		if(!$openId->mode) {
			$openId->identity = 'https://steamcommunity.com/openid';
			return $controller->redirect($openId->authUrl());
		} elseif ($openId->mode == 'cancel') {
			throw $controller->exception($controller->notFound(\XF::phrase('kieran_identity_message_error_validate', ['Steam'])));
		} else {
			if ($openId->validate()) { 
				$id = $openId->identity;
				$ptn = "/^https:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
				preg_match($ptn, $id, $matches);
				$steamid = $matches[1];
				

				$type = $controller->getIdentityTypeRepo()->findIdentityType('steam');
				$identity = $controller->getIdentityRepo()->findIdentityByValueByType($steamid, $type->identity_type_id, false);
				
				if (!$identity) {
					$identities = $controller->getIdentityRepo()->findIdentityByUserIdByType(\XF::visitor()->user_id, $type->identity_type_id);
					
                    $xml = $this->getProfileXML($identity->identity_value);

					$controller->getIdentityRepo()->addIdentity(\XF::visitor()->user_id, $type, html_entity_decode($xml->steamID), $steamid, $identities->count() ? 0 : 1);

					return $controller->redirect('account/identities');

				} else if ($identity->user_id == \XF::visitor()->user_id) {
					
					$xml = $this->getProfileXML($steamid);

                    $identity->identity_name = html_entity_decode($xml->steamID);
                    $identity->status = 0;
					$identity->save();
					return $controller->redirect('account/identities');
				} else {
					throw $controller->exception($controller->notFound(\XF::phrase('kieran_identity_message_error_alreadytaken')));
				}		
			} else {
				throw $controller->exception($controller->notFound(\XF::phrase('kieran_identity_message_error_validate', ['Steam'])));
			}
		}
	}

	public function actionUpdate($identity) {
        $xml = $this->getProfileXML($identity->identity_value);
		$identity->identity_name = html_entity_decode($xml->steamID);
		$identity->save();
    }
    
    private function getProfileXML($steamId64) {
		$ch = curl_init('https://steamcommunity.com/profiles/' . $steamId64 . '/?xml=1');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		return simplexml_load_string(curl_exec($ch), "SimpleXMLElement", LIBXML_NOCDATA);
    }

}