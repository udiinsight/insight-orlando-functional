<?php
namespace WFPCore;

if (!class_exists('\WFPCore\WordPressContext')) {
	class WordPressContext
	{
		public function get_current_url()
		{
			if(!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
				return '';
			}
			return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		}

		public function is_frontend()
		{
			return !is_admin();
		}

		public function is_everywhere()
		{
			return true;
		}

		public function get_day_of_week()
		{
			return date('w');
		}

		public function get_logged_in_user_id()
		{
			if(!function_exists('get_current_user_id')){
				return false;
			}
			return get_current_user_id();
		}

		public function get_logged_in_user_roles()
		{
			if(!function_exists('wp_get_current_user')) {
				return [];
			}
			$user = wp_get_current_user();

			if($user) {
				return (array)$user->roles;
			}

			return [];
		}

		public function get_current_post_id()
		{
			global $post;

			if(!isset($post)) {
				return false;
			}
			return \get_queried_object_id();
		}

		public function get_post_parent()
		{

			if(!$this->get_current_post_id()) {
				return false;
			}

			$post_parent = get_post_parent($this->get_current_post_id());

			if(!$post_parent) {
				return false;
			}

			return $post_parent->ID;
		}

		public function get_post_type()
		{
			return get_post_type($this->get_current_post_id());
		}

		public function get_current_post_terms($taxonomy)
		{
			$current_post_id = $this->get_current_post_id();

			if(!$current_post_id) {
				return [];
			}

			return get_the_terms($current_post_id, $taxonomy);
		}

		function is_login(){


			if(isset($GLOBALS['pagenow'])) {
				$is_login = in_array(
					$GLOBALS['pagenow'],
					array('wp-login.php', 'wp-register.php'),
					true
				);

				return $is_login;
			}

			return false;
		}

		function is_day_of_week($days_of_week) {
			return in_array($this->get_day_of_week(), $days_of_week);
		}

		function is_mobile()
		{
			if(!function_exists('wp_is_mobile')) {
				return false;
			}

			return wp_is_mobile();
		}

		public function is_current_logged_in_user($user_ids_to_check)
		{
			$current_user_id = $this->get_logged_in_user_id();

			if(!$current_user_id) {
				return false;
			}

			return in_array($current_user_id, $user_ids_to_check);
		}

		public function is_current_logged_in_user_role($roles_to_check)
		{
			$current_user_roles = $this->get_logged_in_user_roles();

			if(!$current_user_roles) {
				return false;
			}

			foreach($current_user_roles as $current_user_role) {
				if(in_array($current_user_role, $roles_to_check)) {
					return true;
				}
			}

			return false;
		}

		public function current_url_contains($string)
		{
			$current_url = $this->get_current_url();

			return strpos($current_url, $string) !== false;
		}

		public function is_current_post($post_ids) {
			$current_post_id = $this->get_current_post_id();

			if(!$current_post_id) {
				return false;
			}

			return in_array($current_post_id, $post_ids);
		}

		public function is_current_post_parent($post_ids) {
			$current_post_parent = $this->get_post_parent();

			if(!$current_post_parent) {
				return false;
			}

			return in_array($current_post_parent, $post_ids);
		}

		public function is_current_post_type($post_types) {
			$current_post_type = $this->get_post_type();

			if(!$current_post_type) {
				return false;
			}

			return in_array($current_post_type, $post_types);
		}

		public function current_post_has_term($taxonomy_name, $term)
		{
			$current_post_termids = [];
			$current_post_terms = $this->get_current_post_terms($taxonomy_name);

			if(!is_array($current_post_terms) || empty($current_post_terms)) {
				return false;
			}

			foreach($current_post_terms as $termObj) {
				$current_post_termids[] = $termObj->term_id;
			}

			return in_array($term, $current_post_termids);
		}

		public function current_post_doesnt_have_term($taxonomy_name, $term)
		{
			$current_post_termids = [];
			$current_post_terms = $this->get_current_post_terms($taxonomy_name);

			if(!is_array($current_post_terms) || empty($current_post_terms)) {
				return true;
			}

			foreach($current_post_terms as $termObj) {
				$current_post_termids[] = $termObj->term_id;
			}

			return !in_array($term, $current_post_termids);
		}

		public function current_url_is($url)
		{
			return $this->get_current_url() === $url;
		}

	}
}
