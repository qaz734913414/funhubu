<?php
namespace Addon\Controller;

use Think\Controller;

class OperateController extends Controller {
	//用户订阅
	public function subscribe($weObj){
		$openId = $weObj->getRevFrom();
		$msgType = $weObj->getRev()->getRevType();
		if($msgType != \Weixin\Common\Wechat::MSGTYPE_EVENT){
			return;
		}

		$group = new \Addon\Model\GroupModel();
		$list = $group->getGroup()->getGroupList();
		foreach($list as $key=>$value){
			if($key > 2){
				$data .= $value ."\n";
			}
		}
		$mc = S(array('type'=>'memcached'));
		$mc->set($openId.'_do', 'Addon/Operate/group', 600);
		return array(
			'type' => 'text',
			'data' => "为了更好的用户体验，请选择你的身份:\n".$data,
		);
	}

	//用户分组
	public function group($weObj){
		$openId = $weObj->getRevFrom();
		$target = trim($weObj->getRevContent());
		$group = new \Addon\Model\GroupModel();
		$groupId = $group->getGroupId($target);

		$mc = S(array('type'=>'memcached'));
		//未按要求选择分组
		if(empty($groupId)){
			//返回错误信息
			$count = (int)$mc->get($openId.'_error');
			switch($count){
				case 0:
					$data = '别闹，请按要求选择身份:';
					break;
				case 1:
					$data = '三次错误将被永久锁定！请谨慎选择:';
					break;
				case 2:
					$data = '您的帐号已经被锁定！如有任何异议请联系管理员';
					break;
			}
			$count++;
			if($count < 3){
				$mc->set($openId.'_error', $count, 600);
			}else{
				$user = D('Weixin/User');
				$user->lockUser($openId);
				$mc->rm($openId.'_do');
				$mc->rm($openId.'_error');
			}
			return array(
				'type' => 'text',
				'data' => $data,
			);
		}
		$res = $weObj->updateGroupMembers($groupId, $openId);
		if($res){
			$data = '选择身份成功';
		}else{
			$data = '选择身份失败';
		}
		$mc->rm($openId .'_do');
		$mc->rm($openId.'_error');
		return array(
			'type' => 'text',
			'data' => $data,
		);
	}

	//用户取消订阅
	public function unsubscribe($weObj){
		$openId = $weObj->getRevFrom();
		$mc = S(array('type'=>'memcached'));
		$mc->rm($openId .'_do');
        $mc->rm($openId.'_error');
		$mc->rm($openId.'_data');
	}
}
