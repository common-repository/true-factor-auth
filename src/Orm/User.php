<?php

namespace TrueFactor\Orm;

use TrueFactor\Module\SmsModule;
use TrueFactor\Orm;
use TrueFactor\PhoneNumber;

class User extends Orm {

	static $prefix = '';
	static $table = 'users';

	static $pk = 'ID';
	static $cols = [
		'ID'                  => [ 'bigint', null, 1, null, null, 'AUTO_INCREMENT PRIMARY KEY' ],
		'user_login'          => [ 'varchar', 60 ],
		'user_pass'           => [ 'varchar', 255 ],
		'user_nicename'       => [ 'varchar', 50 ],
		'user_email'          => [ 'varchar', 100 ],
		'user_url'            => [ 'varchar', 100 ],
		'user_registered'     => [ 'datetime' ],
		'user_activation_key' => [ 'varchar', 1024 ],
		'user_status'         => [ 'int' ],
		'display_name'        => [ 'varchar', 250 ],
	];

	public $ID;
	public $user_login;
	public $user_pass;
	public $user_nicename;
	public $user_email;
	public $user_url;
	public $user_registered;
	public $user_activation_key;
	public $user_status;
	public $display_name;

	static function getAllByTel( $tel ) {
		$args = [
			'meta_key'   => SmsModule::get_tel_number_meta_key(),
			'meta_value' => PhoneNumber::normalize( $tel ),
		];

		return get_users( $args );
	}


	static function getByTel( $tel ) {
		$users = self::getAllByTel( $tel );

		if ( ! $users ) {
			return null;
		}

		return get_userdata( $users[0]->ID );
	}

}